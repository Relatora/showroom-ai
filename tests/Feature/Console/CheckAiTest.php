<?php

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Embeddings;

it('passes when both models respond', function () {
    Embeddings::fake();
    AnonymousAgent::fake(['ready']);

    $this->artisan('showroom:ai-check')
        ->expectsOutputToContain('good to go')
        ->assertSuccessful();
});

it('catches an embedding model with the wrong vector size', function () {
    // A 384-dimension model would sail through embedding and then fail on every insert into
    // our vector(768) column. The check should flag it up front.
    Embeddings::fake([[array_fill(0, 384, 0.1)]]);
    AnonymousAgent::fake(['ready']);

    $this->artisan('showroom:ai-check')
        ->expectsOutputToContain('database expects 768')
        ->assertFailed();
});
