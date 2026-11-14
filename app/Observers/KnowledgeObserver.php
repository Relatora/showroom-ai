<?php

namespace App\Observers;

use App\Contracts\Indexable;
use App\Jobs\Knowledge\IndexSource;
use App\Models\Chunk;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps the assistant's knowledge current: when a vehicle, lead or timeline entry changes, its
 * chunks are rebuilt on the AI queue. Nothing slow happens inside the web request.
 *
 * Dispatched after the transaction commits, so the job never reads a row that got rolled back.
 */
class KnowledgeObserver
{
    /**
     * Lead fields that end up in its chunk. A lead is touched on every call (last_contacted_at),
     * and re-indexing on those would just burn embeddings.
     */
    private const LEAD_FIELDS = ['interest', 'trade_in', 'budget_max', 'stage', 'temperature', 'vehicle_id', 'assigned_user_id', 'lost_reason'];

    public function created(Model&Indexable $model): void
    {
        IndexSource::dispatch($model)->afterCommit();
    }

    public function updated(Model&Indexable $model): void
    {
        if ($model instanceof Lead && ! $model->wasChanged(self::LEAD_FIELDS)) {
            return;
        }

        IndexSource::dispatch($model)->afterCommit();
    }

    public function deleted(Model&Indexable $model): void
    {
        Chunk::query()
            ->where('source_type', $model->getMorphClass())
            ->where('source_id', $model->getKey())
            ->delete();
    }
}
