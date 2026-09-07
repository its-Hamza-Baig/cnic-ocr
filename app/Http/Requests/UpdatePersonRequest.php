<?php

namespace App\Http\Requests;

use App\Enums\VerificationStatus;
use App\Services\CnicNormalizerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePersonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $person = $this->route('person');

        return $person !== null && ($this->user()?->can('update', $person) ?? false);
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
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
        ];
    }
}
