<?php

namespace App\Jobs\Knowledge;

use App\Models\Document;
use App\Services\Knowledge\Indexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Extracts, chunks and embeds one uploaded document, updating its status as it goes so the
 * knowledge base page can show progress.
 */
class IndexDocument implements ShouldQueue
{
    use AiQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 600;

    public function __construct(public Document $document)
    {
        $this->onAiQueue();
    }

    public function handle(Indexer $indexer): void
    {
        $this->document->markAs('indexing');

        $indexer->index($this->document);

        $this->document->forceFill([
            'status' => 'ready',
            'error' => null,
            'chunks_count' => $this->document->chunks()->count(),
            'indexed_at' => now(),
        ])->save();
    }

    /**
     * Shown on the knowledge base page, so it should read like advice, not a stack trace.
     */
    public function failed(Throwable $e): void
    {
        $this->document->markAs('failed', str($e->getMessage())->limit(300)->toString());
    }
}
