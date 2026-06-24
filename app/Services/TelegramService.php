<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

/**
 * Posts a wallpaper to the configured Telegram channel (bot token + channel id
 * are set by the super admin in Site Settings → تلجرام).
 */
class TelegramService
{
    public function isConfigured(): bool
    {
        return filled(Setting::get('telegram_bot_token')) && filled(Setting::get('telegram_channel_id'));
    }

    /** @return array{ok: bool, error?: string} */
    public function sendPhoto(string $photoUrl, ?string $caption = null): array
    {
        $token   = Setting::get('telegram_bot_token');
        $channel = Setting::get('telegram_channel_id');

        if (! $token || ! $channel) {
            return ['ok' => false, 'error' => 'لم يُضبط توكن البوت أو معرّف القناة في الإعدادات'];
        }

        try {
            $res = Http::timeout(25)->asJson()->post("https://api.telegram.org/bot{$token}/sendPhoto", array_filter([
                'chat_id' => $channel,
                'photo'   => $photoUrl,
                'caption' => $caption ?: null,
            ]));

            $body = $res->json();

            if ($res->successful() && ($body['ok'] ?? false)) {
                return ['ok' => true];
            }

            return ['ok' => false, 'error' => $body['description'] ?? ('HTTP ' . $res->status())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
