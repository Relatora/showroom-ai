<?php

namespace App\Ai\Concerns;

use App\Ai\LocalModelOptions;
use Laravel\Ai\Enums\Lab;

/**
 * Gives an agent the Ollama runtime options from config (for example forcing CPU inference).
 * Every agent in the app uses this, so the setting is honoured everywhere.
 */
trait UsesLocalModelOptions
{
    /**
     * @return array<string, mixed>
     */
    public function providerOptions(Lab|string $provider): array
    {
        return LocalModelOptions::chat();
    }
}
