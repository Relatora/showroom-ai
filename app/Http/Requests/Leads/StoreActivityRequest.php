<?php

namespace App\Http\Requests\Leads;

use App\Enums\ActivityType;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Lead $lead */
        $lead = $this->route('lead');

        return $this->user()?->can('update', $lead) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Stage changes are written by the system when a lead moves, never typed in by hand.
            'type' => ['required', Rule::enum(ActivityType::class)->except([ActivityType::StageChange])],
            'body' => ['required', 'string', 'min:2', 'max:4000'],
        ];
    }
}
