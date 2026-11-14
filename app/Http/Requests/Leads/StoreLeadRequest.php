<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadTemperature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'source' => ['required', Rule::enum(LeadSource::class)],
            'temperature' => ['required', Rule::enum(LeadTemperature::class)],
            'interest' => ['required', 'string', 'max:1000'],
            'budget_max' => ['nullable', 'integer', 'min:1000', 'max:500000'],
            'trade_in' => ['nullable', 'string', 'max:160'],
            'vehicle_id' => ['nullable', 'integer', 'exists:vehicles,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required_without' => 'We need an email or a phone number to follow up.',
            'phone.required_without' => 'We need an email or a phone number to follow up.',
        ];
    }
}
