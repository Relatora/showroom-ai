<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Laravel\Ai\Embeddings;
use Throwable;

use function Laravel\Ai\agent;

/**
 * A quick smoke test for the AI setup: can we embed text, and can the chat model answer?
 *
 * Most "the assistant is broken" reports come down to one of three things: Ollama isn't
 * running, a model hasn't been pulled, or the embedding size doesn't match the database
 * column. This checks all three and says which one it is, so nobody has to dig through a
 * stack trace to find out.
 */
#[Signature('showroom:ai-check')]
#[Description('Check that the embedding and chat models respond')]
class CheckAi extends Command
{
    public function handle(): int
    {
        $provider = (string) config('ai.default');
        $chatModel = (string) config("ai.providers.{$provider}.models.text.default");
        $embedModel = (string) config("ai.providers.{$provider}.models.embeddings.default");
        $expectedDimensions = (int) config("ai.providers.{$provider}.models.embeddings.dimensions");

        $this->components->twoColumnDetail('Provider', $provider);
        $this->components->twoColumnDetail('Endpoint', (string) config("ai.providers.{$provider}.url"));
        $this->newLine();

        $embeddingsOk = $this->check("Embeddings ({$embedModel})", function () use ($expectedDimensions) {
            $vector = Embeddings::for(['A 3-row hybrid SUV with all-wheel drive.'])
                ->generate()
                ->embeddings[0];

            // A dimension mismatch is the nastiest failure: inserts blow up later, deep inside an
            // indexing job. Better to catch it here.
            if ($expectedDimensions > 0 && count($vector) !== $expectedDimensions) {
                throw new \RuntimeException(sprintf(
                    'Model returned %d dimensions but the database expects %d.',
                    count($vector),
                    $expectedDimensions,
                ));
            }

            return count($vector).' dimensions';
        });

        $chatOk = $this->check("Chat ({$chatModel})", function () {
            $reply = (string) agent('You are a health check. Follow instructions exactly.')
                ->prompt('Reply with the single word: ready');

            return '"'.trim($reply).'"';
        });

        $this->newLine();

        if ($embeddingsOk && $chatOk) {
            $this->components->info('The assistant is good to go.');

            return self::SUCCESS;
        }

        $this->components->bulletList([
            'Is Ollama running? Try `ollama list` on the host.',
            "Are the models pulled? `ollama pull {$embedModel}` and `ollama pull {$chatModel}`.",
            'Inside Docker the URL should use host.docker.internal, not localhost.',
        ]);

        return self::FAILURE;
    }

    /**
     * Run one check, time it and print a single line with the result.
     *
     * @param  callable(): string  $probe
     */
    private function check(string $label, callable $probe): bool
    {
        $started = microtime(true);

        try {
            $detail = $probe();
            $ms = (int) round((microtime(true) - $started) * 1000);

            $this->components->twoColumnDetail($label, "<fg=green>✓</> {$detail} <fg=gray>{$ms} ms</>");

            return true;
        } catch (Throwable $e) {
            $this->components->twoColumnDetail($label, '<fg=red>✗ failed</>');
            $this->components->error(str($e->getMessage())->limit(300)->toString());

            return false;
        }
    }
}
