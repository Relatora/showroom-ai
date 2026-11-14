<?php

namespace App\Jobs\Knowledge;

use App\Contracts\Indexable;
use App\Services\Knowledge\Indexer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds the chunks for one vehicle, lead or timeline entry.
 *
 * Unique per source while queued: five quick edits to the same note collapse into one re-index.
 */
class IndexSource implements ShouldBeUnique, ShouldQueue
{
    use AiQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60];

    public function __construct(public Model&Indexable $source)
    {
        $this->onAiQueue();
    }

    public function uniqueId(): string
    {
        return $this->source->getMorphClass().':'.$this->source->getKey();
    }

    public function handle(Indexer $indexer): void
    {
        $indexer->index($this->source);
    }
}
