<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\ContentSanitizer;
use Illuminate\Foundation\Http\FormRequest;

final class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => trim(ContentSanitizer::clean((string) $this->input('content'))),
            'author_name' => trim(strip_tags((string) $this->input('author_name'))),
            'author_email' => strtolower(trim((string) $this->input('author_email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:5', 'max:1000'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_email' => ['nullable', 'email:rfc', 'max:255'],
        ];
    }
}
