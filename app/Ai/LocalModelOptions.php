<?php

namespace App\Ai;

/**
 * Ollama-specific runtime options, kept in one place.
 *
 * The main one is OLLAMA_NUM_GPU. Leave it unset and Ollama decides (it uses the GPU when it
 * can). Set it to 0 to force CPU, which is the fix when a machine's GPU driver is too old for
 * Ollama's CUDA build and every request crashes. Slower, but it works.
 */
final class LocalModelOptions
{
    /**
     * Options for the /api/embed request body.
     *
     * @return array<string, mixed>
     */
    public static function embeddings(): array
    {
        $options = self::runtime();

        return $options === [] ? [] : ['options' => $options];
    }

    /**
     * Options for chat requests. The SDK moves these into Ollama's "options" object for us.
     *
     * @return array<string, mixed>
     */
    public static function chat(): array
    {
        return self::runtime();
    }

    /**
     * @return array<string, mixed>
     */
    private static function runtime(): array
    {
        if (config('ai.default') !== 'ollama') {
            return [];
        }

        $numGpu = config('ai.providers.ollama.num_gpu');

        return $numGpu === null || $numGpu === '' ? [] : ['num_gpu' => (int) $numGpu];
    }
}
