<?php

use App\Models\Chunk;
use App\Models\Lead;
use App\Models\User;
use App\Services\Knowledge\HybridRetriever;
use Laravel\Ai\Embeddings;

beforeEach(fn () => Embeddings::fake());

/**
 * Embeddings are faked (random vectors), so these tests exercise the keyword half, the fusion
 * and the access rules. Retrieval *quality* is measured against the real model by showroom:eval.
 */
function documentChunk(string $heading, string $content, int $position = 0): Chunk
{
    return Chunk::create([
        'source_type' => 'document',
        'source_id' => 1,
        'position' => $position,
        'heading' => $heading,
        'content' => $content,
        'token_estimate' => 10,
        'content_hash' => hash('sha256', $heading.$content),
        'embedding' => array_fill(0, 768, 0.01),
    ]);
}

it('ranks results that both searches agree on above results from only one', function () {
    $fused = HybridRetriever::fuse([10, 20, 30], [30, 40]);

    // 30 is third in one list and first in the other, so it beats 10, which is only in one.
    expect(array_key_first($fused))->toBe(30)
        ->and($fused[30])->toEqualWithDelta(1 / 63 + 1 / 61, 1e-9)
        ->and(array_keys($fused))->toHaveCount(4);
});

it('finds passages by exact policy code and wording', function () {
    $offer = documentChunk('Trade-in policy (F-204): Offer validity', 'An appraisal offer is valid for 7 days or 500 km, whichever comes first.');
    documentChunk('Deposit policy (S-110): Refunds', 'Deposits are refundable within 48 hours.', 1);

    $results = app(HybridRetriever::class)->search('how many days is an appraisal offer valid?', User::factory()->create());

    expect($results[0]->chunk->id)->toBe($offer->id)
        ->and($results[0]->keywordRank)->toBe(1)
        ->and($results[0]->url())->toEndWith('#passage-0');
});

it("never retrieves another rep's customer notes", function () {
    $rep = User::factory()->create();
    Lead::factory()->assignedTo($rep)->create(['interest' => 'Minivan with sliding doors for the twins']);
    $theirs = Lead::factory()->create(['interest' => 'Pickup that can tow a pontoon boat to the cottage']);

    $retriever = app(HybridRetriever::class);
    $leadIds = fn (User $user) => collect($retriever->search('tow a pontoon boat', $user, 20))
        ->map(fn ($r) => $r->chunk->lead_id)
        ->filter()
        ->unique()
        ->values()
        ->all();

    expect($leadIds($rep))->not->toContain($theirs->id)
        ->and($leadIds(User::factory()->manager()->create()))->toContain($theirs->id);
});

it('can be limited to some kinds of source', function () {
    documentChunk('Warranty (W-120)', 'Powertrain warranty covers 5 years.');
    Lead::factory()->create(['interest' => 'Wants a long powertrain warranty']);

    $types = collect(app(HybridRetriever::class)->search('powertrain warranty', User::factory()->manager()->create(), 10, ['document']))
        ->map(fn ($r) => $r->chunk->source_type)
        ->unique()
        ->all();

    expect($types)->toBe(['document']);
});

it('returns nothing for an empty question', function () {
    expect(app(HybridRetriever::class)->search('   ', User::factory()->create()))->toBe([]);
});
