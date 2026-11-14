<?php

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A question for the assistant, plus the last few turns so follow-ups ("and for a CPO?") work.
 *
 * History comes from the browser, so it's capped in count and length: it's the cheapest way
 * to keep context without storing conversations, and the caps stop anyone from stuffing an
 * enormous prompt through it.
 */
class AskRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $turns = config('showroom.assistant.history_turns') * 2;

        return [
            'question' => ['required', 'string', 'min:2', 'max:500'],
            'history' => ['sometimes', 'array', "max:{$turns}"],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:3000'],
        ];
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function history(): array
    {
        /** @var list<array{role: string, content: string}> */
        return array_values(array_map(
            fn (array $turn) => ['role' => $turn['role'], 'content' => $turn['content']],
            $this->validated('history', []),
        ));
    }
}
