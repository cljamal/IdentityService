<?php

namespace App\Actions\Auth;

use App\Notifications\Otp\SmsTemplate;
use Lorisleiva\Actions\Concerns\AsAction;

final readonly class RenderOtpSmsAction
{
    use AsAction;

    public function handle(string $code, ?SmsTemplate $sms = null): string
    {
        $replacements = ['{{code}}' => $code];

        foreach ($sms->data ?? [] as $name => $value) {
            $replacements['{{'.$name.'}}'] = $value;
        }

        return strtr($sms->template ?? 'Ваш код подтверждения: {{code}}', $replacements);
    }
}
