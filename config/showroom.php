<?php

/**
 * Settings for the Showroom assistant. Everything model-related lives in config/ai.php; this
 * file is about how the app *uses* the model.
 */
return [

    // The dealership the assistant works for. It shows up in prompts and email signatures.
    'dealership' => env('SHOWROOM_DEALERSHIP', 'Northgate Motors'),

    // Linked from the public landing page.
    'repository' => 'https://github.com/Relatora/showroom-ai',

    'assistant' => [
        // How many passages go into each prompt. More context helps with broad questions, but
        // every passage costs prompt-processing time, which is very noticeable on a CPU.
        'sources' => (int) env('ASSISTANT_SOURCES', 5),

        // How many earlier turns of the conversation are sent along for follow-up questions.
        'history_turns' => 4,
    ],

];
