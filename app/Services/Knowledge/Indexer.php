<?php

namespace App\Services\Knowledge;

use App\Contracts\Indexable;
use App\Models\Chunk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the chunks table in step with the things it describes.
 *
 * Re-indexing is cheap when little has changed: each chunk is keyed by a hash of its text, and
 * any chunk whose text is unchanged keeps its existing embedding. Editing one timeline note
 * re-embeds one chunk, not the whole lead.
 */
class Indexer
{
    public function __construct(
        private readonly Embedder $embedder,
        private readonly PiiMasker $masker,
    ) {}

    /**
     * @param  Indexable&Model  $source
     */
    public function index(Indexable $source): int
    {
        return $this->indexMany([$source]);
    }

    /**
     * Index many sources, sharing embedding batches between them. Returns how many chunks
     * needed a fresh embedding.
     *
     * @param  iterable<Indexable&Model>  $sources
     */
    public function indexMany(iterable $sources): int
    {
        $plans = [];
        $toEmbed = [];

        foreach ($sources as $source) {
            $drafts = array_map(
                fn (ChunkDraft $d) => new ChunkDraft($this->masker->mask($d->content), $d->heading),
                $source->toChunks(),
            );

            $existing = Chunk::query()
                ->where('source_type', $source->getMorphClass())
                ->where('source_id', $source->getKey())
                ->get(['id', 'content_hash', 'embedding'])
                ->keyBy('content_hash');

            foreach ($drafts as $draft) {
                if (! $existing->has($draft->hash())) {
                    $toEmbed[$draft->hash()] = $draft->textForEmbedding();
                }
            }

            $plans[] = [$source, $drafts, $existing];
        }

        $vectors = $toEmbed === []
            ? []
            : array_combine(array_keys($toEmbed), $this->embedder->documents(array_values($toEmbed)));

        foreach ($plans as [$source, $drafts, $existing]) {
            $this->write($source, $drafts, $existing, $vectors);
        }

        return count($toEmbed);
    }

    /**
     * @param  Indexable&Model  $source
     */
    public function forget(Indexable $source): void
    {
        Chunk::query()
            ->where('source_type', $source->getMorphClass())
            ->where('source_id', $source->getKey())
            ->delete();
    }

    /**
     * Replace a source's chunks in one transaction, so a search never sees half an update.
     *
     * @param  Indexable&Model  $source
     * @param  list<ChunkDraft>  $drafts
     * @param  Collection<string, Chunk>  $existing
     * @param  array<string, array<int, float>>  $vectors
     */
    private function write(Indexable $source, array $drafts, $existing, array $vectors): void
    {
        DB::transaction(function () use ($source, $drafts, $existing, $vectors) {
            $this->forget($source);

            foreach ($drafts as $position => $draft) {
                Chunk::create([
                    'source_type' => $source->getMorphClass(),
                    'source_id' => $source->getKey(),
                    'lead_id' => $source->indexLeadId(),
                    'position' => $position,
                    'heading' => $draft->heading,
                    'content' => $draft->content,
                    'token_estimate' => $draft->tokenEstimate(),
                    'content_hash' => $draft->hash(),
                    'embedding' => $vectors[$draft->hash()] ?? $existing->get($draft->hash())?->embedding,
                ]);
            }
        });
    }
}
