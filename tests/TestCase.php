<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Ai\Embeddings;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Pages render the Vite tags in app.blade.php. Tests care about what the server sends,
        // not compiled JS, so don't make every test run depend on `npm run build`.
        $this->withoutVite();

        // Creating a vehicle, lead or note indexes it, which calls the embedding model. Tests
        // must never depend on a model actually running (or on the GPU driver that runs it), so
        // every test gets fake embeddings unless it sets up its own.
        Embeddings::fake();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
