<?php

namespace App\Http\Controllers;

use App\Services\TwoFactorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorController extends Controller
{
    // GET /admin/2fa — show the OTP entry page (only if a challenge is pending).
    public function show(TwoFactorService $svc): View|RedirectResponse
    {
        if (! $svc->hasPendingChallenge()) {
            return redirect('/admin/login');
        }

        return view('auth.two-factor', [
            'secondsLeft'  => $svc->secondsLeft(),
            'attemptsLeft' => $svc->attemptsLeft(),
        ]);
    }

    // POST /admin/2fa — verify the code.
    public function verify(Request $request, TwoFactorService $svc): RedirectResponse
    {
        if (! $svc->hasPendingChallenge()) {
            return redirect('/admin/login');
        }

        // Extra rate limit on verification attempts.
        $key = 'admin-2fa:' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $svc->cancel();
            return redirect('/admin/login')->withErrors(['code' => 'محاولات كثيرة جدًا. أعد المحاولة لاحقًا.']);
        }
        RateLimiter::hit($key, 120);

        // Emergency fallback: super admin backup code (when Telegram is down).
        if ($request->filled('recovery_code')) {
            return match ($svc->verifyRecovery((string) $request->input('recovery_code'), $request)) {
                'ok'      => redirect()->intended('/admin'),
                'expired' => redirect('/admin/login')->withErrors(['code' => 'انتهت صلاحية الجلسة. أعد تسجيل الدخول.']),
                default   => back()->withErrors(['code' => 'الرمز الاحتياطي غير صحيح.']),
            };
        }

        $request->validate(['code' => 'required|digits:4'], [], ['code' => 'الرمز']);

        return match ($svc->verify((string) $request->input('code'), $request)) {
            'ok'      => redirect()->intended('/admin'),
            'locked'  => redirect('/admin/login')->withErrors(['code' => 'تم إلغاء العملية بعد ٣ محاولات خاطئة. أعد تسجيل الدخول.']),
            'expired' => redirect('/admin/login')->withErrors(['code' => 'انتهت صلاحية الرمز. أعد تسجيل الدخول.']),
            default   => back()->withErrors(['code' => 'رمز غير صحيح. المحاولات المتبقية: ' . $svc->attemptsLeft()]),
        };
    }
}
