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
