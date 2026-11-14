<?php

namespace App\Console\Commands;

use App\Contracts\Indexable;
use App\Enums\ActivityType;
use App\Jobs\Knowledge\IndexDocument;
use App\Models\Activity;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Lead;
use App\Models\Vehicle;
use App\Services\Knowledge\Indexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Builds (or rebuilds) the assistant's whole index: vehicles, leads, timeline entries and
 * uploaded documents.
 *
 * Day to day the observers keep things current. This is for first setup, after a seed, or
 * after changing the chunking or the embedding model. Unchanged text keeps its embedding, so
 * running it twice in a row is quick.
 */
#[Signature('showroom:index
    {--fresh : Delete every chunk first and embed everything again}
    {--only= : Just one kind: vehicles, leads, activities or documents}')]
#[Description('Index CRM data and documents for the assistant')]
class IndexKnowledge extends Command
{
    public function handle(Indexer $indexer): int
    {
        if ($this->option('fresh')) {
            Chunk::query()->delete();
            $this->components->warn('Cleared the index.');
        }

        $only = $this->option('only');

        $sets = [
            'vehicles' => Vehicle::query(),
            'leads' => Lead::query()->with(['customer', 'assignee', 'vehicle']),
            'activities' => Activity::query()
                ->where('type', '!=', ActivityType::StageChange)
                ->with(['lead.customer', 'user']),
        ];

        foreach ($sets as $name => $query) {
            if ($only === null || $only === $name) {
                $this->indexSet($name, $query, $indexer);
            }
        }

        if ($only === null || $only === 'documents') {
            $this->indexDocuments($indexer);
        }

        $this->newLine();
        $this->components->info(sprintf('Index holds %s chunks.', number_format(Chunk::count())));

        return self::SUCCESS;
    }

    /**
     * @param  Builder<covariant Model>  $query
     */
    private function indexSet(string $name, Builder $query, Indexer $indexer): void
    {
        $total = $query->count();
        $embedded = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->setFormat(' %message% %current%/%max% [%bar%] %elapsed:6s%');
        $bar->setMessage(str_pad(ucfirst($name), 11));

        // 64 sources at a time share embedding batches, which is where the time goes.
        $query->chunkById(64, function ($models) use ($indexer, $bar, &$embedded) {
            /** @var Collection<int, Indexable&Model> $models */
            $embedded += $indexer->indexMany($models);
            $bar->advance(count($models));
        });

        $bar->finish();
        $this->line(" <fg=gray>{$embedded} newly embedded</>");
    }

    /**
     * Documents are extracted and embedded one at a time, in this process, so errors show up
     * right here instead of in Horizon.
     */
    private function indexDocuments(Indexer $indexer): void
    {
        $documents = Document::all();

        foreach ($documents as $document) {
            try {
                (new IndexDocument($document))->handle($indexer);
                $this->components->twoColumnDetail($document->title, "<fg=green>{$document->chunks_count} chunks</>");
            } catch (\Throwable $e) {
                $document->markAs('failed', $e->getMessage());
                $this->components->twoColumnDetail($document->title, '<fg=red>failed</>');
                $this->components->error($e->getMessage());
            }
        }
    }
}
