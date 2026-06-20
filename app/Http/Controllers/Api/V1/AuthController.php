<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $key = 'login:' . hash('sha256', $request->email . $request->ip());

        $tooManyAttempts = RateLimiter::tooManyAttempts($key, (int) Setting::get('login_attempts_limit', 5));

        if ($tooManyAttempts) {
            $seconds = RateLimiter::availableIn($key);
            return response()->json([
                'message' => "تم قفل الحساب مؤقتًا. حاول بعد {$seconds} ثانية",
            ], 429);
        }

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            RateLimiter::hit($key, (int) Setting::get('login_lockout_minutes', 30) * 60);

            return response()->json(['message' => 'بيانات الدخول غير صحيحة'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'حسابك غير مفعل'], 403);
        }

        if (! $user->hasPermissionTo('can_login_admin_panel')) {
            return response()->json(['message' => 'ليس لديك صلاحية الوصول'], 403);
        }

        RateLimiter::clear($key);

        $user->update([
            'last_login_at' => now(),
            'last_login_ip_hash' => hash('sha256', $request->ip() . config('app.key')),
            'failed_login_attempts' => 0,
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        activity('auth')->causedBy($user)->log('login');

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->getRoleNames(),
                'permissions' => $user->getAllPermissions()->pluck('name'),
                'auto_publish' => $user->auto_publish,
                'daily_upload_limit' => $user->daily_upload_limit,
                'can_upload_without_watermark' => $user->can_upload_without_watermark,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'تم تسجيل الخروج بنجاح']);
    }
}
