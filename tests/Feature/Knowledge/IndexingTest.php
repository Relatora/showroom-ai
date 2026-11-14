<?php

use App\Enums\ActivityType;
use App\Jobs\Knowledge\IndexSource;
use App\Models\Activity;
use App\Models\Chunk;
use App\Models\Lead;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Knowledge\Indexer;
use Illuminate\Support\Facades\Queue;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;

beforeEach(fn () => Embeddings::fake());

it('indexes a vehicle with its specs in plain words', function () {
    $vehicle = Vehicle::factory()->create(['towing_lb' => 3500, 'seats' => 8]);

    $chunk = Chunk::where('source_type', 'vehicle')->where('source_id', $vehicle->id)->sole();

    expect($chunk->content)->toContain('tows up to 3,500 lb')->toContain('8 seats')
        ->and($chunk->embedding)->toHaveCount(768)
        ->and($chunk->lead_id)->toBeNull();
});

it('masks contact details before anything is embedded', function () {
    $lead = Lead::factory()->create();
    Activity::factory()->for($lead)->create([
        'type' => ActivityType::Call,
        'body' => 'Call back on 416-555-0123 or email zed@example.com',
    ]);

    $chunk = Chunk::where('source_type', 'activity')->latest('id')->first();

    expect($chunk->content)->toContain('[phone]')->toContain('[email]')->not->toContain('416-555-0123');
    Embeddings::assertNotGenerated(fn (EmbeddingsPrompt $p) => $p->contains('416-555-0123'));
});

it('skips stage changes, which are just bookkeeping', function () {
    $activity = Activity::factory()->create(['type' => ActivityType::StageChange, 'body' => 'Moved to Won.']);

    expect(Chunk::where('source_type', 'activity')->where('source_id', $activity->id)->exists())->toBeFalse();
});

it('reuses embeddings for text that has not changed', function () {
    $vehicle = Vehicle::factory()->create();

    // The observer already indexed it, so running again embeds nothing new.
    expect(app(Indexer::class)->index($vehicle))->toBe(0);
});

it('re-indexes a lead only when something in its summary changes', function () {
    $lead = Lead::factory()->create();
    Queue::fake();

    $lead->update(['last_contacted_at' => now()]);
    Queue::assertNotPushed(IndexSource::class);

    $lead->update(['interest' => 'Now wants an EV instead.']);
    Queue::assertPushed(IndexSource::class);
});

it('removes chunks when the source is deleted', function () {
    $vehicle = Vehicle::factory()->create();
    $vehicle->delete();

    expect(Chunk::where('source_type', 'vehicle')->count())->toBe(0);
});

it("hides another rep's lead notes from retrieval but shares stock with everyone", function () {
    $rep = User::factory()->create();
    $mine = Lead::factory()->assignedTo($rep)->create();
    $theirs = Lead::factory()->create();
    Vehicle::factory()->create();

    $visibleLeadIds = Chunk::visibleTo($rep)->whereNotNull('lead_id')->distinct()->pluck('lead_id')->all();
    $managerSees = Chunk::visibleTo(User::factory()->manager()->create())
        ->whereIn('lead_id', [$mine->id, $theirs->id])
        ->distinct()
        ->count('lead_id');

    expect($visibleLeadIds)->toBe([$mine->id])
        ->and(Chunk::visibleTo($rep)->where('source_type', 'vehicle')->count())->toBe(1)
        ->and($managerSees)->toBe(2);
});
