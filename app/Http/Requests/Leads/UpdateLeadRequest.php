<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadTemperature;
use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Lead $lead */
        $lead = $this->route('lead');

        // Reassigning is a separate, manager-only permission, even inside an otherwise allowed edit.
        if ($this->has('assigned_user_id') && $this->input('assigned_user_id') !== $lead->assigned_user_id) {
            return $this->user()?->can('reassign', $lead) ?? false;
        }

        return $this->user()?->can('update', $lead) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'temperature' => ['sometimes', Rule::enum(LeadTemperature::class)],
            'interest' => ['sometimes', 'string', 'max:1000'],
            'budget_max' => ['sometimes', 'nullable', 'integer', 'min:1000', 'max:500000'],
            'trade_in' => ['sometimes', 'nullable', 'string', 'max:160'],
            'vehicle_id' => ['sometimes', 'nullable', 'integer', 'exists:vehicles,id'],
            'next_follow_up_at' => ['sometimes', 'nullable', 'date'],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
