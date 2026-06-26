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

    public function hasBot(): bool
    {
        return filled(Setting::get('telegram_bot_token'));
    }

    /** Send a direct message to a chat (member DM, admin notification). */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null): array
    {
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return ['ok' => false, 'error' => 'لم يُضبط توكن البوت'];
        }

        try {
            $res = Http::timeout(15)->asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", array_filter([
                'chat_id'      => $chatId,
                'text'         => $text,
                'parse_mode'   => 'HTML',
                'reply_markup' => $replyMarkup,
            ], fn ($v) => $v !== null));

            $body = $res->json();
            if ($res->successful() && ($body['ok'] ?? false)) {
                return ['ok' => true];
            }
            return ['ok' => false, 'error' => $body['description'] ?? ('HTTP ' . $res->status())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** The bot's @username (cached in settings) — needed for t.me deep links. */
    public function getBotUsername(): ?string
    {
        if ($cached = Setting::get('telegram_bot_username')) {
            return $cached;
        }
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return null;
        }
        try {
            $username = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe")->json('result.username');
            if ($username) {
                Setting::set('telegram_bot_username', $username);
                return $username;
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /** Register the webhook so the bot receives /start updates. */
    public function setWebhook(string $url): array
    {
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return ['ok' => false, 'error' => 'لم يُضبط توكن البوت'];
        }
        try {
            $res = Http::timeout(15)->asJson()->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url'             => $url,
                'allowed_updates' => ['message'],
            ]);
            $body = $res->json();
            return ['ok' => (bool) ($body['ok'] ?? false), 'error' => $body['description'] ?? null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  string|null  $topicId  Explicit forum topic (section). Falls back to
     *                                the default wallpapers topic when null.
     * @return array{ok: bool, error?: string}
     */
    public function sendPhoto(string $photoUrl, ?string $caption = null, ?string $topicId = null): array
    {
        $token   = Setting::get('telegram_bot_token');
        $channel = Setting::get('telegram_channel_id');
        $topicId = $topicId ?: Setting::get('telegram_topic_id'); // explicit section or wallpapers default

        if (! $token || ! $channel) {
            return ['ok' => false, 'error' => 'لم يُضبط توكن البوت أو معرّف القناة في الإعدادات'];
        }

        try {
            $res = Http::timeout(25)->asJson()->post("https://api.telegram.org/bot{$token}/sendPhoto", array_filter([
                'chat_id'                  => $channel,
                'message_thread_id'        => filled($topicId) ? (int) $topicId : null,
                'photo'                    => $photoUrl,
                'caption'                  => $caption ?: null,
                'parse_mode'               => $caption ? 'HTML' : null,
            ], fn ($v) => $v !== null));

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
