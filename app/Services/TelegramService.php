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

    /** DM the member that their listing was rejected, with the reason + an edit-and-resubmit link. */
    public function notifyListingRejected(\App\Models\MarketListing $listing, string $reason): void
    {
        $chatId = $listing->member?->telegram_id;
        if (! $chatId) {
            return;
        }
        $editUrl = 'https://qev.app/ar/sell?edit=' . $listing->id;
        $text = "❌ <b>تم رفض إعلانك</b>\n«" . e($listing->title_ar) . "»\n\n"
              . '📝 السبب: ' . e($reason)
              . "\n\nعدّل إعلانك وأعد إرساله للمراجعة من الزر بالأسفل.";
        $this->sendMessage((string) $chatId, $text, [
            'inline_keyboard' => [[['text' => '✏️ تعديل الإعلان', 'url' => $editUrl]]],
        ]);
    }

    /**
     * DM every opted-in moderator about a new pending listing — with the cover photo
     * and ✅ نشر / ❌ رفض inline buttons so they can moderate from inside Telegram.
     */
    public function notifyModeratorsNewListing(\App\Models\MarketListing $listing): void
    {
        $mods = \App\Models\User::query()
            ->where('notify_new_listings', true)
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->pluck('telegram_chat_id');
        if ($mods->isEmpty()) {
            return;
        }

        $isCar     = in_array($listing->listing_type, ['car_sale', 'car_request'], true);
        $reviewUrl = 'https://api.qev.app/admin/' . ($isCar ? 'car-market' : 'parts-market') . '/' . $listing->id . '/edit';
        $member    = $listing->member;
        $price     = $listing->price !== null ? number_format((float) $listing->price, 0) . ' ' . $listing->currency : 'حسب الطلب';
        $caption = "🆕 <b>إعلان جديد بانتظار المراجعة</b>\n«" . e($listing->title_ar) . "»\n"
                 . "💰 {$price}\n"
                 . '👤 ' . e($member?->name ?? 'عضو') . ($member?->telegram_username ? ' (@' . $member->telegram_username . ')' : '')
                 . ($listing->description_ar ? "\n\n" . e(mb_substr($listing->description_ar, 0, 300)) : '');

        $markup = ['inline_keyboard' => [
            [
                ['text' => '✅ نشر', 'callback_data' => 'approve:' . $listing->id],
                ['text' => '❌ رفض', 'callback_data' => 'reject:' . $listing->id],
            ],
            [['text' => '📣 نشر + قناة الإعلانات', 'callback_data' => 'approve_tg:' . $listing->id]],
            [['text' => '🔍 فتح في اللوحة', 'url' => $reviewUrl]],
        ]];

        foreach ($mods as $chatId) {
            $this->sendPhotoToChat((string) $chatId, $listing->cover_url, $caption, $markup);
        }
    }

    /**
     * Publish an approved listing into the channel's "listings" topic — cover photo,
     * a tidy caption (title / price / location / blurb) and a link back to the site.
     * Requires telegram_topic_id_market so listings never leak into another topic.
     *
     * @return array{ok: bool, error?: string}
     */
    public function sendListingToChannel(\App\Models\MarketListing $listing): array
    {
        $topic = Setting::get('telegram_topic_id_market');
        if (blank($topic)) {
            return ['ok' => false, 'error' => 'لم يُضبط رقم قسم الإعلانات في الإعدادات'];
        }

        $front = rtrim(config('app.frontend_url', 'https://qev.app'), '/');
        $isCar = in_array($listing->listing_type, ['car_sale', 'car_request'], true);
        $price = $listing->price !== null
            ? number_format((float) $listing->price, 0) . ' ' . $listing->currency
            : 'حسب الطلب';
        $loc = trim(implode('، ', array_filter([$listing->city, $listing->country])));

        $caption = ($isCar ? '🚗 ' : '🛒 ') . '<b>' . e($listing->title_ar) . "</b>\n"
                 . '💰 ' . e($price) . ($listing->is_negotiable ? ' • قابل للتفاوض' : '') . "\n"
                 . ($loc ? '📍 ' . e($loc) . "\n" : '')
                 . ($listing->description_ar ? "\n" . e(mb_substr($listing->description_ar, 0, 400)) . "\n" : '')
                 . "\n🔗 " . $front . '/ar/market/' . $listing->slug;

        return $this->sendPhoto((string) $listing->cover_url, $caption, (string) $topic);
    }

    /** Send a photo (or a text fallback) to a specific chat with an optional inline keyboard. */
    public function sendPhotoToChat(string $chatId, ?string $photoUrl, string $caption, ?array $replyMarkup = null): array
    {
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return ['ok' => false];
        }

        try {
            if ($photoUrl && ($jpeg = $this->imageAsJpeg($photoUrl))) {
                $fields = array_filter([
                    'chat_id'      => $chatId,
                    'caption'      => $caption,
                    'parse_mode'   => 'HTML',
                    'reply_markup' => $replyMarkup ? json_encode($replyMarkup) : null,
                ], fn ($v) => $v !== null);
                $res = Http::timeout(45)->attach('photo', $jpeg, 'cover.jpg')->post("https://api.telegram.org/bot{$token}/sendPhoto", $fields);
                if ($res->successful() && ($res->json('ok') ?? false)) {
                    return ['ok' => true];
                }
            }
        } catch (\Throwable) {
            // fall through to a text message
        }

        return $this->sendMessage($chatId, $caption, $replyMarkup);
    }

    /** Acknowledge an inline-button tap (small toast/alert inside Telegram). */
    public function answerCallback(string $callbackId, string $text = '', bool $alert = false): void
    {
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return;
        }
        try {
            Http::timeout(10)->asJson()->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", array_filter([
                'callback_query_id' => $callbackId,
                'text'              => $text ?: null,
                'show_alert'        => $alert,
            ], fn ($v) => $v !== null));
        } catch (\Throwable) {
        }
    }

    /** Update a sent photo's caption and drop its inline keyboard (after it's been acted on). */
    public function editMessageCaption(string $chatId, int $messageId, string $caption): void
    {
        $token = Setting::get('telegram_bot_token');
        if (! $token) {
            return;
        }
        try {
            Http::timeout(10)->asJson()->post("https://api.telegram.org/bot{$token}/editMessageCaption", [
                'chat_id'      => $chatId,
                'message_id'   => $messageId,
                'caption'      => $caption,
                'parse_mode'   => 'HTML',
                'reply_markup' => ['inline_keyboard' => []],
            ]);
        } catch (\Throwable) {
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
                'allowed_updates'      => ['message', 'callback_query'],
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
