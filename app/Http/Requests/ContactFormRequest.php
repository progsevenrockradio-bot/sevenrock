<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ContentSanitizer;
use Illuminate\Foundation\Http\FormRequest;

final class ContactFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim(strip_tags((string) $this->input('name'))),
            'email' => strtolower(trim((string) $this->input('email'))),
            'phone' => trim(strip_tags((string) $this->input('phone'))),
            'message' => trim(ContentSanitizer::clean((string) $this->input('message'))),
            'subject' => trim(strip_tags((string) $this->input('subject', 'general'))),
            'band_name' => $this->filled('band_name') ? trim(strip_tags((string) $this->input('band_name'))) : null,
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'subject' => ['required', 'string', 'in:general,join_radio'],
            'band_name' => ['required_if:subject,join_radio', 'nullable', 'string', 'max:255'],
        ];
    }
}
