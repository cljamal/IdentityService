<?php

namespace App\Notifications\Otp;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final readonly class SmsTemplate
{
    /** @param array<string, string> $data */
    private function __construct(public string $template, public array $data) {}

    /** @param array<string, mixed> $input */
    public static function fromInput(array $input): ?self
    {
        if (! array_key_exists('sms', $input)) {
            return null;
        }

        /** @var array{sms: array{template: string, data?: array<array-key, mixed>}} $validated */
        $validated = Validator::make($input, [
            'sms' => ['required', 'array:template,data'],
            'sms.template' => ['required', 'string'],
            'sms.data' => ['sometimes', 'array'],
        ])->validate();

        $template = $validated['sms']['template'];
        $data = [];

        foreach ($validated['sms']['data'] ?? [] as $key => $value) {
            if ($key === 'code' || ! is_string($key) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $key) || ! is_scalar($value)) {
                throw ValidationException::withMessages([
                    'sms.data' => 'Используйте именованные скалярные значения; code задаёт сервис идентификации.',
                ]);
            }

            $data[$key] = (string) $value;
        }

        preg_match_all('/\{\{([A-Za-z_][A-Za-z0-9_]*)\}\}/', $template, $matches);
        $remaining = preg_replace('/\{\{([A-Za-z_][A-Za-z0-9_]*)\}\}/', '', $template);

        if (! in_array('code', $matches[1], true) || str_contains($remaining ?? '', '{{') || str_contains($remaining ?? '', '}}')) {
            throw ValidationException::withMessages([
                'sms.template' => 'Шаблон должен содержать {{code}} и только подстановки вида {{name}}.',
            ]);
        }

        foreach ($matches[1] as $name) {
            if ($name !== 'code' && ! array_key_exists($name, $data)) {
                throw ValidationException::withMessages([
                    'sms.template' => "Не передано значение для переменной $name.",
                ]);
            }
        }

        return new self($template, $data);
    }
}
