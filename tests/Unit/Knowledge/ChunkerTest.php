<?php

use App\Services\Knowledge\Chunker;

it('splits on markdown headings and keeps the heading with each chunk', function () {
    $chunks = (new Chunker)->split("# Trade-in policy\n\nIntro text.\n\n## Offer validity\n\nOffers last 7 days.", 'Trade-in policy');

    expect($chunks)->toHaveCount(2)
        ->and($chunks[1]->heading)->toBe('Trade-in policy: Offer validity')
        ->and($chunks[1]->content)->toBe('Offers last 7 days.');
});

it('recognises the numbered and all-caps headings PDFs tend to have', function () {
    $chunks = (new Chunker)->split("1. Deposits\nA deposit holds the car.\n\nREFUNDS\nRefunds take five days.");

    expect(array_map(fn ($c) => $c->heading, $chunks))->toBe(['1. Deposits', 'Refunds']);
});

it('packs long sections into size-limited chunks with overlap', function () {
    $paragraph = trim(str_repeat('The customer wants a hybrid with three rows of seats. ', 12));
    $chunks = (new Chunker(maxChars: 800, overlapChars: 120))->split(implode("\n\n", array_fill(0, 6, $paragraph)));

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk->content))->toBeLessThanOrEqual(800 + 120 + 2);
    }

    // Each chunk after the first starts with the tail of the one before it.
    expect($chunks[1]->content)->toStartWith('The customer wants');
});
