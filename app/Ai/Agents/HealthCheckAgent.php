<?php

namespace App\Ai\Agents;

use App\Ai\Concerns\UsesLocalModelOptions;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasProviderOptions;
use Laravel\Ai\Promptable;

/**
 * The smallest possible agent, used by showroom:ai-check to prove the chat model answers.
 */
#[MaxTokens(10)]
#[Temperature(0)]
#[Timeout(180)]
class HealthCheckAgent implements Agent, HasProviderOptions
{
    use Promptable, UsesLocalModelOptions;

    public function instructions(): string
    {
        return 'You are a health check. Follow instructions exactly.';
    }
}
