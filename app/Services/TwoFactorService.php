<?php

namespace App\Services;

use App\Models\AdminLoginLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Telegram-based second factor for the admin panel ONLY (web guard). After the
 * email+password check passes, a 4-digit OTP is sent to the admin's linked
 * Telegram; the challenge lives in the session (hashed, single-use, 60s, 3 tries).
 * Members are never affected. A settings toggle + an env kill-switch prevent lockout.
 */
class TwoFactorService
{
    private const KEY = 'admin_2fa';
    private const TTL = 60;       // seconds
    private const MAX_ATTEMPTS = 3;

    /** Is 2FA active for this admin? Off by default; env kill-switch wins. */
    public function enabledFor(User $user): bool
    {
        if (getenv('ADMIN_2FA_DISABLED') === 'true') {
            return false; // emergency bypass (set in Railway, no login needed)
        }
        if (blank($user->telegram_chat_id)) {
            return false; // no linked Telegram → can't deliver an OTP
        }
        return filter_var(Setting::get('admin_2fa_enabled', '0'), FILTER_VALIDATE_BOOLEAN);
    }

    /** Generate + store + send a fresh OTP, and remember the pending challenge. */
    public function startChallenge(User $user, Request $request, bool $remember): void
    {
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);

        session()->put(self::KEY, [
            'user_id'    => $user->id,
            'code_hash'  => Hash::make($code),
            'expires_at' => now()->addSeconds(self::TTL)->timestamp,
            'attempts'   => 0,
            'remember'   => $remember,
        ]);

        $this->sendOtp($user, $code);
        $this->log('otp_sent', $request, $user->id);
    }

    public function hasPendingChallenge(): bool
    {
        $c = session(self::KEY);
        return is_array($c) && ($c['expires_at'] ?? 0) >= now()->timestamp;
    }

    public function secondsLeft(): int
    {
        $c = session(self::KEY);
        return is_array($c) ? max(0, (int) ($c['expires_at'] ?? 0) - now()->timestamp) : 0;
    }

    public function attemptsLeft(): int
    {
        $c = session(self::KEY);
        return is_array($c) ? max(0, self::MAX_ATTEMPTS - (int) ($c['attempts'] ?? 0)) : 0;
    }

    /**
     * Verify a submitted code. Returns one of: ok | expired | locked | wrong.
     * On success the admin is logged in; the challenge is always single-use.
     */
    public function verify(string $code, Request $request): string
    {
        $c = session(self::KEY);
        if (! is_array($c)) {
            return 'expired';
        }
        if (($c['expires_at'] ?? 0) < now()->timestamp) {
            session()->forget(self::KEY);
            return 'expired';
        }

        $c['attempts'] = (int) ($c['attempts'] ?? 0) + 1;

        if (! Hash::check($code, $c['code_hash'] ?? '')) {
            if ($c['attempts'] >= self::MAX_ATTEMPTS) {
                session()->forget(self::KEY);
                $this->log('otp_locked', $request, $c['user_id'] ?? null);
                $this->notifyFailed(User::find($c['user_id'] ?? null), $request, 'استنفاد محاولات رمز التحقق');
                return 'locked';
            }
            session()->put(self::KEY, $c); // keep, decremented attempts
            $this->log('otp_failed', $request, $c['user_id'] ?? null);
            return 'wrong';
        }

        // Correct → complete login and destroy the challenge (single-use).
        $user = User::find($c['user_id'] ?? null);
        session()->forget(self::KEY);
        if (! $user) {
            return 'expired';
        }

        auth('web')->login($user, (bool) ($c['remember'] ?? false));
        session()->regenerate();

        $this->log('otp_success', $request, $user->id);
        $this->notifySuccess($user, $request);

        return 'ok';
    }

    public function cancel(): void
    {
        session()->forget(self::KEY);
    }

    // ─────────────────────────── Telegram messages ──────────────────────────

    private function sendOtp(User $user, string $code): void
    {
        $text = "🔐 <b>رمز الدخول إلى لوحة تحكم QEV</b>\n\n"
              . "رمز التحقق الخاص بك هو:\n\n<code>{$code}</code>\n\n"
              . "صلاحية الرمز: 60 ثانية.\n\n"
              . "إذا لم تحاول تسجيل الدخول، يرجى تغيير كلمة المرور فورًا.";
        $this->tg($user, $text);
    }

    private function notifySuccess(User $user, Request $request): void
    {
        [$device, $os, $browser] = $this->parseUa((string) $request->userAgent());
        $ctx = $this->context($request, $device, $os, $browser);
        $this->tg($user, "✅ <b>تم تسجيل الدخول بنجاح إلى لوحة تحكم QEV</b>\n{$ctx}\n\nإذا لم تكن أنت، غيّر كلمة المرور فورًا.");
    }

    private function notifyFailed(?User $user, Request $request, string $reason): void
    {
        if (! $user) {
            return;
        }
        [$device, $os, $browser] = $this->parseUa((string) $request->userAgent());
        $ctx = $this->context($request, $device, $os, $browser);
        $this->tg($user, "⚠️ <b>محاولة دخول فاشلة للوحة تحكم QEV</b>\nالسبب: {$reason}\n{$ctx}\n\nإذا لم تكن أنت، غيّر كلمة المرور فورًا.");
    }

    /** Notify an admin (by email) of a wrong-password attempt, if we can reach them. */
    public function notifyBadPassword(?User $user, Request $request): void
    {
        $this->notifyFailed($user, $request, 'كلمة مرور خاطئة');
    }

    private function context(Request $request, string $device, string $os, string $browser): string
    {
        $country = strtoupper(substr((string) $request->header('CF-IPCountry', ''), 0, 2)) ?: '—';
        return "📍 الدولة: {$country} · {$device} · {$os} · {$browser}\n"
             . "🌐 IP: " . $request->ip() . "\n"
             . "🕒 " . now()->format('Y-m-d H:i');
    }

    private function tg(?User $user, string $text): void
    {
        if (! $user || blank($user->telegram_chat_id)) {
            return;
        }
        try {
            app(TelegramService::class)->sendMessage((string) $user->telegram_chat_id, $text);
        } catch (\Throwable) {
            // never let a Telegram failure block the auth flow
        }
    }

    // ─────────────────────────── audit + parsing ────────────────────────────

    public function log(string $event, Request $request, ?int $userId = null, ?string $email = null): void
    {
        try {
            [$device, $os, $browser] = $this->parseUa((string) $request->userAgent());
            AdminLoginLog::create([
                'user_id'    => $userId,
                'email'      => $email,
                'event'      => $event,
                'ip'         => $request->ip(),
                'country'    => strtoupper(substr((string) $request->header('CF-IPCountry', ''), 0, 2)) ?: null,
                'device'     => $device,
                'os'         => $os,
                'browser'    => $browser,
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'created_at' => now(),
            ]);
        } catch (\Throwable) {
            // auditing must never break login
        }
    }

    /** @return array{0:string,1:string,2:string} [device, os, browser] */
    private function parseUa(string $ua): array
    {
        $s = strtolower($ua);
        $device = 'desktop';
        if (str_contains($s, 'ipad') || (str_contains($s, 'tablet') && ! str_contains($s, 'mobile'))) {
            $device = 'tablet';
        } elseif (str_contains($s, 'mobi') || str_contains($s, 'iphone') || str_contains($s, 'android')) {
            $device = 'mobile';
        }
        $os = 'Other';
        if (str_contains($s, 'windows')) $os = 'Windows';
        elseif (str_contains($s, 'iphone') || str_contains($s, 'ipad') || str_contains($s, 'cpu os')) $os = 'iOS';
        elseif (str_contains($s, 'mac os x') || str_contains($s, 'macintosh')) $os = 'macOS';
        elseif (str_contains($s, 'android')) $os = 'Android';
        elseif (str_contains($s, 'linux')) $os = 'Linux';
        $browser = 'Other';
        if (str_contains($s, 'edg/')) $browser = 'Edge';
        elseif (str_contains($s, 'chrome') || str_contains($s, 'crios')) $browser = 'Chrome';
        elseif (str_contains($s, 'firefox') || str_contains($s, 'fxios')) $browser = 'Firefox';
        elseif (str_contains($s, 'safari')) $browser = 'Safari';
        return [$device, $os, $browser];
    }
}
