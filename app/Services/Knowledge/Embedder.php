<?php

namespace App\Services\Knowledge;

use App\Ai\LocalModelOptions;
use Laravel\Ai\Embeddings;

/**
 * Turns text into vectors, in batches.
 *
 * One request per chunk would spend most of its time on HTTP overhead; batching 32 at a time
 * keeps a full re-index of the demo dealership to a minute or two even on a CPU.
 */
class Embedder
{
    private const BATCH_SIZE = 32;

    /**
     * nomic-embed-text was trained with task prefixes, and retrieval is noticeably better when
     * documents and questions are labelled as such.
     */
    public const DOCUMENT_PREFIX = 'search_document: ';

    public const QUERY_PREFIX = 'search_query: ';

    /**
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    public function documents(array $texts): array
    {
        return $this->embed(array_map(fn (string $t) => self::DOCUMENT_PREFIX.$t, $texts));
    }

    /**
     * @return array<int, float>
     */
    public function query(string $question): array
    {
        return $this->embed([self::QUERY_PREFIX.$question])[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<array<int, float>>
     */
    private function embed(array $texts): array
    {
        $vectors = [];

        foreach (array_chunk($texts, self::BATCH_SIZE) as $batch) {
            $response = Embeddings::for($batch)
                ->withProviderOptions(LocalModelOptions::embeddings())
                ->timeout(120)
                ->generate();

            array_push($vectors, ...$response->embeddings);
        }

        return $vectors;
    }
}
