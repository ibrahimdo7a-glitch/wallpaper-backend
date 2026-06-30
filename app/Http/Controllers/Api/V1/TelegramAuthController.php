<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\MemberLoginToken;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TelegramAuthController extends Controller
{
    public function __construct(private TelegramService $telegram)
    {
    }

    /** Stable, unguessable webhook path segment derived from the bot token. */
    public static function webhookSecret(): string
    {
        return substr(hash('sha256', (string) config('app.key') . '|' . Setting::get('telegram_bot_token')), 0, 32);
    }

    public static function webhookUrl(?string $base = null): string
    {
        $base = $base ?: config('app.url');
        return rtrim($base, '/') . '/api/telegram/webhook/' . self::webhookSecret();
    }

    // POST /v1/auth/telegram/start — begin a login session
    public function start(): JsonResponse
    {
        $botUsername = $this->telegram->getBotUsername();
        if (! $botUsername) {
            return response()->json(['error' => 'البوت غير مهيأ بعد'], 503);
        }

        // prune stale tokens opportunistically
        MemberLoginToken::where('expires_at', '<', now()->subHour())->delete();

        $token = Str::random(40);
        MemberLoginToken::create([
            'token'      => $token,
            'status'     => 'pending',
            'expires_at' => now()->addMinutes(10),
        ]);

        $deepLink = "https://t.me/{$botUsername}?start={$token}";

        return response()->json([
            'token'        => $token,
            'bot_username' => $botUsername,
            'deep_link'    => $deepLink,   // also used as the QR payload
            'expires_in'   => 600,
        ]);
    }

    // GET /v1/auth/telegram/status?token= — poll until the bot verifies
    public function status(Request $request): JsonResponse
    {
        $login = MemberLoginToken::where('token', $request->query('token'))->first();

        if (! $login || $login->isExpired()) {
            return response()->json(['status' => 'expired']);
        }

        if ($login->status === 'verified' && $login->member_id) {
            $member = $login->member;
            if (! $member || $member->isBanned()) {
                return response()->json(['status' => 'expired']);
            }

            $login->update(['status' => 'used']);                 // one-time
            $apiToken = $member->createToken('web')->plainTextToken;

            return response()->json([
                'status'    => 'verified',
                'api_token' => $apiToken,
                'member'    => $member->toPublicArray(),
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    // GET /v1/auth/me — current member (auth:member)
    public function me(Request $request): JsonResponse
    {
        $member = $request->user();
        return response()->json([
            'member' => $member->toPublicArray(),
            // One-time admin message shown in a modal, then acknowledged & cleared.
            'notice' => $member->site_message ?: null,
        ]);
    }

    // POST /v1/member/notice/ack — clear the one-time message after the member reads it
    public function dismissNotice(Request $request): JsonResponse
    {
        $request->user()->update(['site_message' => null]);
        return response()->json(['ok' => true]);
    }

    // POST /v1/auth/logout — revoke current token (auth:member)
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }

    // POST /api/telegram/webhook/{secret} — Telegram calls this on every update
    public function webhook(Request $request, string $secret): JsonResponse
    {
        if (! hash_equals(self::webhookSecret(), $secret)) {
            return response()->json(['ok' => false], 403);
        }

        // Inline-button taps (✅ نشر / ❌ رفض) from moderators.
        if ($callback = $request->input('callback_query')) {
            return $this->handleModeratorCallback($callback);
        }

        $message = $request->input('message') ?? $request->input('edited_message');
        $from    = $message['from'] ?? null;
        $text    = trim((string) ($message['text'] ?? ''));

        $debug = ['at' => now()->toDateTimeString(), 'text' => $text, 'from_id' => $from['id'] ?? null, 'username' => $from['username'] ?? null];
        $record = fn (string $result) => Setting::set('telegram_last_update', json_encode($debug + ['result' => $result], JSON_UNESCAPED_UNICODE));

        if (! $from) {
            return response()->json(['ok' => true]);
        }

        // A moderator typing the rejection reason after tapping ❌ رفض.
        if ($text !== '' && ! Str::startsWith($text, '/') && $this->handleModeratorReason($from, $text)) {
            $record('سبب رفض من مشرف ✓');
            return response()->json(['ok' => true]);
        }

        if (! Str::startsWith($text, '/start')) {
            $record('تجاهل (ليست /start)');
            return response()->json(['ok' => true]);
        }

        $member = Member::updateOrCreate(
            ['telegram_id' => $from['id']],
            [
                'telegram_username' => $from['username'] ?? null,
                'name'              => trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: ($from['username'] ?? 'عضو'),
                'last_login_at'     => now(),
            ]
        );

        // First-time registrant → queue the admin welcome message (shown once in-site).
        if ($member->wasRecentlyCreated
            && filter_var(\App\Models\Setting::get('welcome_enabled', '0'), FILTER_VALIDATE_BOOLEAN)
            && ($welcome = trim((string) \App\Models\Setting::get('welcome_message', ''))) !== '') {
            $member->update(['site_message' => $welcome]);
        }

        if ($member->isBanned()) {
            $record('محظور');
            $this->telegram->sendMessage((string) $from['id'], '🚫 حسابك محظور. تواصل مع الإدارة.');
            return response()->json(['ok' => true]);
        }

        // /start <token>
        $loginToken = trim(Str::after($text, '/start'));
        $debug['token_param'] = $loginToken;

        if ($loginToken !== '') {
            // Moderator Telegram linking: /start adm_<code> → store the chat id on the user.
            if (Str::startsWith($loginToken, 'adm_')) {
                $code = Str::after($loginToken, 'adm_');
                $user = \App\Models\User::where('telegram_link_code', $code)->first();
                if ($user) {
                    $user->update(['telegram_chat_id' => (string) $from['id'], 'telegram_link_code' => null]);
                    $record('ربط مشرف ✓');
                    $this->telegram->sendMessage((string) $from['id'], '✅ تم ربط حسابك كمشرف في <b>qev.app</b> — ستصلك إشعارات الإعلانات الجديدة هنا.');
                } else {
                    $record('رمز مشرف غير صالح');
                    $this->telegram->sendMessage((string) $from['id'], '⚠️ رمز الربط غير صالح أو مستخدَم. أنشئ رمزًا جديدًا من لوحة التحكم.');
                }
                return response()->json(['ok' => true]);
            }

            $login = MemberLoginToken::where('token', $loginToken)->where('status', 'pending')->first();
            if (! $login) {
                $record('الرمز غير موجود');
            } elseif ($login->isExpired()) {
                $record('الرمز منتهي');
            } else {
                $login->update(['status' => 'verified', 'member_id' => $member->id, 'telegram_id' => $member->telegram_id]);
                $record('تم التحقق ✓');
                $this->telegram->sendMessage((string) $from['id'], '✅ تم تسجيل دخولك في <b>qev.app</b> — ارجع للصفحة.');
                return response()->json(['ok' => true]);
            }
        } else {
            $record('بدون رمز (start فارغ)');
        }

        $this->telegram->sendMessage((string) $from['id'], '👋 أهلًا بك في <b>qev.app</b>. ارجع للموقع واضغط "تسجيل الدخول" لإكمال الدخول.');
        return response()->json(['ok' => true]);
    }

    /** Handle a moderator tapping ✅ نشر / ❌ رفض on a listing notification inside Telegram. */
    private function handleModeratorCallback(array $callback): JsonResponse
    {
        $from       = $callback['from'] ?? [];
        $data       = (string) ($callback['data'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');

        Setting::set('telegram_last_update', json_encode([
            'at' => now()->toDateTimeString(), 'callback' => $data, 'from_id' => $from['id'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
        $msg        = $callback['message'] ?? [];
        $chatId     = (string) ($msg['chat']['id'] ?? ($from['id'] ?? ''));
        $messageId  = (int) ($msg['message_id'] ?? 0);

        // Only a linked, opted-in moderator may act.
        $mod = \App\Models\User::where('telegram_chat_id', (string) ($from['id'] ?? ''))
            ->where('notify_new_listings', true)->first();
        if (! $mod) {
            $this->telegram->answerCallback($callbackId, '⛔ غير مصرّح', true);
            return response()->json(['ok' => true]);
        }

        [$action, $idRaw] = array_pad(explode(':', $data, 2), 2, null);
        $listing = $idRaw ? \App\Models\MarketListing::find((int) $idRaw) : null;
        if (! $listing) {
            $this->telegram->answerCallback($callbackId, 'الإعلان غير موجود');
            return response()->json(['ok' => true]);
        }

        if ($listing->status !== 'pending') {
            $this->telegram->answerCallback($callbackId, 'تمت معالجته مسبقًا');
            if ($messageId) {
                $this->telegram->editMessageCaption($chatId, $messageId, '☑️ عولج مسبقًا — «' . e($listing->title_ar) . '»');
            }
            return response()->json(['ok' => true]);
        }

        if ($action === 'approve' || $action === 'approve_tg') {
            $listing->update(['status' => 'published', 'rejection_reason' => null, 'published_at' => $listing->published_at ?? now()]);

            if ($action === 'approve_tg') {
                // Publish on the site AND post into the channel's listings topic.
                $res  = $this->telegram->sendListingToChannel($listing->fresh());
                $okTg = $res['ok'] ?? false;
                $this->telegram->answerCallback(
                    $callbackId,
                    $okTg ? '✅ نُشر في الموقع وقناة الإعلانات' : ('✅ نُشر في الموقع — تعذّر النشر في القناة: ' . ($res['error'] ?? '')),
                    ! $okTg,
                );
                if ($messageId) {
                    $this->telegram->editMessageCaption($chatId, $messageId, $okTg
                        ? '📣 <b>نُشر في الموقع + قناة الإعلانات</b> — «' . e($listing->title_ar) . '»'
                        : '✅ <b>نُشر في الموقع</b> (تعذّر النشر في القناة) — «' . e($listing->title_ar) . '»');
                }
            } else {
                $this->telegram->answerCallback($callbackId, '✅ تم النشر');
                if ($messageId) {
                    $this->telegram->editMessageCaption($chatId, $messageId, '✅ <b>نُشر</b> — «' . e($listing->title_ar) . '»');
                }
            }
            return response()->json(['ok' => true]);
        }

        if ($action === 'reject') {
            $mod->update(['pending_reject_listing_id' => $listing->id]);
            $this->telegram->answerCallback($callbackId, '✍️ اكتب سبب الرفض كرسالة');
            $this->telegram->sendMessage((string) $from['id'], '✍️ اكتب سبب رفض «<b>' . e($listing->title_ar) . '</b>» كرسالة الآن، وسأرسله للعضو.');
            return response()->json(['ok' => true]);
        }

        $this->telegram->answerCallback($callbackId, '');
        return response()->json(['ok' => true]);
    }

    /** A moderator with a pending reject typed the reason → reject + notify the member. Returns true if handled. */
    private function handleModeratorReason(array $from, string $text): bool
    {
        $mod = \App\Models\User::where('telegram_chat_id', (string) ($from['id'] ?? ''))
            ->whereNotNull('pending_reject_listing_id')->first();
        if (! $mod) {
            return false;
        }

        $listing = \App\Models\MarketListing::find($mod->pending_reject_listing_id);
        $mod->update(['pending_reject_listing_id' => null]);

        if ($listing && $listing->status === 'pending') {
            $listing->update(['status' => 'rejected', 'rejection_reason' => $text]);
            $this->telegram->sendMessage((string) $from['id'], '❌ تم رفض «<b>' . e($listing->title_ar) . '</b>» وإشعار العضو بالسبب.');
        } else {
            $this->telegram->sendMessage((string) $from['id'], 'تعذّر — ربما عولج الإعلان مسبقًا.');
        }

        return true;
    }
}
