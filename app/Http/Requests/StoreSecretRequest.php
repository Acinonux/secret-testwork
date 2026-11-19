<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSecretRequest extends FormRequest
{
    public function rules(): array
    {
        $maxBase64 = (int) ceil(config('secrets.max_bytes') / 3) * 4;

        return [
            'payload' => ['required', 'string', 'max:'.$maxBase64],
            'ttl' => ['required', 'integer', Rule::in(config('secrets.ttl_options'))],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('payload')) {
                return;
            }
            $bytes = $this->decodedPayload();
            if ($bytes === null
                || strlen($bytes) < config('secrets.min_bytes')
                || strlen($bytes) > config('secrets.max_bytes')) {
                $validator->errors()->add('payload', 'Некорректные зашифрованные данные.');
            }
        }];
    }

    public function decodedPayload(): ?string
    {
        $decoded = base64_decode((string) $this->input('payload'), true);

        return $decoded === false ? null : $decoded;
    }
}
