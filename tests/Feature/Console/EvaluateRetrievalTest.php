<?php

use App\Models\Chunk;
use Illuminate\Support\Facades\File;
use Laravel\Ai\Embeddings;

/**
 * The real numbers come from running the command against the real model (see evals/results.md).
 * This only checks the plumbing: questions in, ranks and metrics out, exit code from the bar.
 */
beforeEach(function () {
    Embeddings::fake();

    $this->questionsFile = 'storage/framework/testing/eval-questions.json';
    File::ensureDirectoryExists(base_path('storage/framework/testing'));
    File::put(base_path($this->questionsFile), json_encode(['questions' => [
        ['question' => 'How long is a trade-in offer valid?', 'document' => 'F-204', 'section' => 'How long an offer is valid'],
        ['question' => 'Is S1046 still available?', 'stock' => 'S1046'],
    ]]));

    foreach ([
        ['document', 'Trade-in Appraisal Policy (F-204): How long an offer is valid', 'A written trade-in offer is valid for 7 days.'],
        ['vehicle', 'Inventory: 2021 Chevrolet Equinox EV 2LT (S1046)', 'Stock S1046. Status: Available.'],
    ] as $i => [$type, $heading, $content]) {
        Chunk::create([
            'source_type' => $type, 'source_id' => $i + 1, 'position' => 0, 'heading' => $heading,
            'content' => $content, 'token_estimate' => 10, 'content_hash' => (string) $i,
            'embedding' => array_fill(0, 768, 0.01),
        ]);
    }
});

afterEach(fn () => File::delete(base_path($this->questionsFile)));

it('reports the hybrid hit rate and fails below the bar', function () {
    // Faked embeddings make vector ranks random, but with two chunks every search ranks both,
    // and keyword plus identifier search put the right one first.
    $this->artisan('showroom:eval', ['--questions' => $this->questionsFile, '--min' => '1.0'])
        ->expectsOutputToContain('Hybrid hit@5 is 1.00')
        ->assertSuccessful();

    $this->artisan('showroom:eval', ['--questions' => $this->questionsFile, '--k' => '1', '--min' => '1.01'])
        ->assertFailed();
});

it('refuses to run against an empty index', function () {
    Chunk::query()->delete();

    $this->artisan('showroom:eval', ['--questions' => $this->questionsFile])
        ->expectsOutputToContain('The index is empty')
        ->assertFailed();
});
