<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStage;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveLeadStageRequest extends FormRequest
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
            'stage' => ['required', Rule::enum(LeadStage::class)],
            'position' => ['nullable', 'integer', 'min:0'],
            'lost_reason' => ['nullable', 'required_if:stage,lost', 'string', 'max:160'],
        ];
    }
}
