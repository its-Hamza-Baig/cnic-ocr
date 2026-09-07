<?php

namespace App\Http\Requests;

use App\Enums\VerificationStatus;
use App\Models\Person;
use App\Services\CnicNormalizerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Person::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('cnic')) {
            $this->merge([
                'cnic' => app(CnicNormalizerService::class)->normalize((string) $this->input('cnic')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cnic' => ['required', 'string', 'regex:'.config('cnic.pattern')],
            'name' => ['required', 'string', 'max:'.config('cnic.name_max_length')],
            'father_husband_name' => ['nullable', 'string', 'max:'.config('cnic.name_max_length')],
            'address' => ['nullable', 'string', 'max:'.config('cnic.address_max_length')],
            'verification_status' => ['nullable', Rule::enum(VerificationStatus::class)],
            'confirmed' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'You must confirm the extracted details before saving.',
        ];
    }
}
