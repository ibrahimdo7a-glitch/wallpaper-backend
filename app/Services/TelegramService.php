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

    /** Current webhook registration info (for diagnostics). */
    public function getWebhookInfo(): array
    {
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return ['error' => 'no token'];
        }
        try {
            return Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo")->json('result') ?? [];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
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
                'url'                  => $url,
                'allowed_updates'      => ['message'],
                'drop_pending_updates' => true,
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

        $endpoint = "https://api.telegram.org/bot{$token}/sendPhoto";
        $base = array_filter([
            'chat_id'           => $channel,
            'message_thread_id' => filled($topicId) ? (int) $topicId : null,
            'caption'           => $caption ?: null,
            'parse_mode'        => $caption ? 'HTML' : null,
        ], fn ($v) => $v !== null);

        try {
            // Preferred: re-encode the image to JPEG and upload the bytes directly.
            // Telegram rejects WebP/AVIF (which CDNs love to serve), so a plain URL often
            // fails with "wrong type of the web page content".
            if ($jpeg = $this->imageAsJpeg($photoUrl)) {
                $res  = Http::timeout(45)->attach('photo', $jpeg, 'cover.jpg')->post($endpoint, $base);
                $body = $res->json();
                if ($res->successful() && ($body['ok'] ?? false)) {
                    return ['ok' => true];
                }
            }

            // Fallback 1: let Telegram fetch the URL itself.
            $res  = Http::timeout(25)->asJson()->post($endpoint, $base + ['photo' => $photoUrl]);
            $body = $res->json();
            if ($res->successful() && ($body['ok'] ?? false)) {
                return ['ok' => true];
            }

            // Fallback 2: publish the caption as a text post so the news still goes out.
            if ($caption && $this->sendChannelText($channel, $caption, $topicId)['ok']) {
                return ['ok' => true];
            }

            return ['ok' => false, 'error' => $body['description'] ?? ('HTTP ' . $res->status())];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** Download an image and re-encode it to JPEG bytes (Telegram-safe), or null on failure. */
    private function imageAsJpeg(string $url): ?string
    {
        try {
            $resp = Http::timeout(25)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (compatible; QEVBot/1.0)',
                // Ask the CDN for formats GD can decode — never AVIF.
                'Accept'     => 'image/webp,image/png,image/jpeg,*/*;q=0.8',
            ])->get($url);

            if (! $resp->successful() || $resp->body() === '') {
                return null;
            }

            $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
            $img = $manager->read($resp->body());
            if ($img->width() > 2000) {
                $img->scaleDown(width: 2000);
            }
            return (string) $img->toJpeg(quality: 85);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Post a plain text message to the channel (optionally inside a forum topic). */
    private function sendChannelText(string $channel, string $text, ?string $topicId): array
    {
        $token = Setting::get('telegram_bot_token');
        try {
            $res = Http::timeout(15)->asJson()->post("https://api.telegram.org/bot{$token}/sendMessage", array_filter([
                'chat_id'           => $channel,
                'message_thread_id' => filled($topicId) ? (int) $topicId : null,
                'text'              => $text,
                'parse_mode'        => 'HTML',
            ], fn ($v) => $v !== null));
            $body = $res->json();
            return ['ok' => (bool) ($res->successful() && ($body['ok'] ?? false))];
        } catch (\Throwable) {
            return ['ok' => false];
        }
    }
}
