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
        return response()->json(['member' => $request->user()->toPublicArray()]);
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

        $message = $request->input('message') ?? $request->input('edited_message');
        $from    = $message['from'] ?? null;
        $text    = trim((string) ($message['text'] ?? ''));

        if (! $from || ! Str::startsWith($text, '/start')) {
            return response()->json(['ok' => true]); // ignore everything else for now
        }

        $member = Member::updateOrCreate(
            ['telegram_id' => $from['id']],
            [
                'telegram_username' => $from['username'] ?? null,
                'name'              => trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')) ?: ($from['username'] ?? 'عضو'),
                'last_login_at'     => now(),
            ]
        );

        if ($member->isBanned()) {
            $this->telegram->sendMessage((string) $from['id'], '🚫 حسابك محظور. تواصل مع الإدارة.');
            return response()->json(['ok' => true]);
        }

        // /start <token>
        $loginToken = trim(Str::after($text, '/start'));
        if ($loginToken !== '') {
            $login = MemberLoginToken::where('token', $loginToken)->where('status', 'pending')->first();
            if ($login && ! $login->isExpired()) {
                $login->update(['status' => 'verified', 'member_id' => $member->id, 'telegram_id' => $member->telegram_id]);
                $this->telegram->sendMessage((string) $from['id'], '✅ تم تسجيل دخولك في <b>qev.app</b> — ارجع للصفحة.');
                return response()->json(['ok' => true]);
            }
        }

        $this->telegram->sendMessage((string) $from['id'], '👋 أهلًا بك في <b>qev.app</b>. ارجع للموقع واضغط "تسجيل الدخول" لإكمال الدخول.');
        return response()->json(['ok' => true]);
    }
}
