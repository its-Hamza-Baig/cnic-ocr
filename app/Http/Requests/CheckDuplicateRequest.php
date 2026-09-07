<?php

namespace App\Http\Requests;

use App\Models\Person;
use App\Services\CnicNormalizerService;
use Illuminate\Foundation\Http\FormRequest;

class CheckDuplicateRequest extends FormRequest
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
            'ignore_person_id' => ['nullable', 'integer'],
        ];
    }
}
