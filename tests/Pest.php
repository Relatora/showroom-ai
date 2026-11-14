<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test case
|--------------------------------------------------------------------------
|
| Feature tests boot the full app and run against the real Postgres
| "testing" database (Sail creates it). We need Postgres rather than SQLite
| because the RAG features depend on pgvector and Postgres full-text search.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Splits a server-sent event stream into [event name, decoded data] pairs.
 *
 * @return list<array{0: string, 1: mixed}>
 */
function sseEvents(string $body): array
{
    preg_match_all('/^event: (\w+)\ndata: (.*)$/m', $body, $matches, PREG_SET_ORDER);

    return array_map(fn (array $m) => [$m[1], json_decode($m[2], true)], $matches);
}

/**
 * All the "delta" text in a stream, joined back together.
 */
function sseText(string $body): string
{
    return collect(sseEvents($body))->where(0, 'delta')->pluck('1.text')->join('');
}
