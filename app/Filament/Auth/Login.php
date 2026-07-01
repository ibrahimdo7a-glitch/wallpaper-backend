<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Admin login with a Telegram OTP second factor. Validates the password WITHOUT
 * logging in; if 2FA is enabled for the admin, it starts a Telegram OTP challenge
 * and redirects to /two-factor instead of authenticating. Members are unaffected.
 *
 * Lives outside app/Filament/Pages so panel page-discovery never registers it as
 * a navigation page — it's used only via ->login() in the panel provider.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        // App-level rate limit on the login attempt.
        $throttleKey = 'admin-login:' . request()->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'data.email' => 'محاولات كثيرة جدًا. حاول بعد ' . RateLimiter::availableIn($throttleKey) . ' ثانية.',
            ]);
        }
        RateLimiter::hit($throttleKey, 60);

        $data        = $this->form->getState();
        $credentials = $this->getCredentialsFromFormData($data);
        $svc         = app(TwoFactorService::class);

        $user = User::where('email', $credentials['email'] ?? null)->first();

        // Verify password + panel access WITHOUT establishing a session.
        if (! $user
            || ! Hash::check($credentials['password'] ?? '', $user->password)
            || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            $svc->log('password_failed', request(), $user?->id, $credentials['email'] ?? null);
            if ($user) {
                $svc->notifyBadPassword($user, request());
            }
            $this->throwFailureValidationException();
        }

        RateLimiter::clear($throttleKey);
        $remember = (bool) ($data['remember'] ?? false);

        // 2FA disabled / not linked / kill-switched → normal login (never lock out).
        if (! $svc->enabledFor($user)) {
            Filament::auth()->login($user, $remember);
            session()->regenerate();
            $svc->log('login_no_2fa', request(), $user->id);
            return app(LoginResponse::class);
        }

        // Password OK → start the Telegram OTP challenge and hand off to /two-factor.
        $svc->startChallenge($user, request(), $remember);
        $this->redirect('/two-factor');
        return null;
    }
}
