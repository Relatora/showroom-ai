<?php

namespace App\Jobs\Knowledge;

/**
 * Puts a job on the AI queue (a separate Horizon supervisor with long timeouts, see
 * config/horizon.php), so slow model work never holds up everyday jobs.
 */
trait AiQueue
{
    protected function onAiQueue(): void
    {
        $this->onConnection('redis-ai');
        $this->onQueue('ai');
    }
}
