<?php

namespace App\Http\Requests;

use App\Models\Person;
use App\Rules\CnicImageRule;
use Illuminate\Foundation\Http\FormRequest;

class ProcessOcrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Person::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'front_image' => ['required', 'file', new CnicImageRule],
            'back_image' => ['nullable', 'file', new CnicImageRule],
        ];
    }
}
