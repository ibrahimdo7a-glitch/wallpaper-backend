<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>التحقق الثنائي — QEV</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, #060d20 0%, #0e1e46 100%);
            font-family: "Noto Kufi Arabic", system-ui, -apple-system, sans-serif; color: #e8ecf3; padding: 16px;
        }
        .card {
            width: 100%; max-width: 380px; background: #0f1522; border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px; padding: 32px 26px; box-shadow: 0 20px 60px rgba(0,0,0,.45); text-align: center;
        }
        .icon { font-size: 40px; margin-bottom: 10px; }
        h1 { font-size: 19px; margin: 0 0 6px; }
        p.sub { color: #9aa6bd; font-size: 13px; margin: 0 0 22px; line-height: 1.7; }
        .otp { display: flex; justify-content: center; direction: ltr; margin-bottom: 8px; }
        input[name="code"] {
            width: 100%; letter-spacing: 18px; text-align: center; font-size: 30px; font-weight: 800;
            padding: 14px 10px; border-radius: 14px; border: 1px solid rgba(255,255,255,.12);
            background: #0a0f1a; color: #fff; outline: none; direction: ltr;
        }
        input[name="code"]:focus { border-color: #2f7bf0; box-shadow: 0 0 0 3px rgba(47,123,240,.25); }
        button {
            width: 100%; margin-top: 16px; padding: 13px; border: 0; border-radius: 14px; cursor: pointer;
            background: #2563eb; color: #fff; font-weight: 700; font-size: 15px; font-family: inherit;
        }
        button:hover { background: #1d4ed8; }
        .timer { margin-top: 14px; font-size: 13px; color: #9aa6bd; }
        .timer b { color: #e8ecf3; }
        .error { background: rgba(220,38,38,.12); border: 1px solid rgba(220,38,38,.35); color: #fca5a5;
            border-radius: 12px; padding: 10px; font-size: 13px; margin-bottom: 16px; }
        a.back { display: inline-block; margin-top: 16px; color: #7aa2f7; text-decoration: none; font-size: 13px; }
        a.back:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔐</div>
        <h1>التحقق الثنائي</h1>
        <p class="sub">أرسلنا رمزًا من ٤ أرقام إلى حسابك في تيليجرام.<br>أدخله للمتابعة إلى لوحة التحكم.</p>

        @if ($errors->any())
            <div class="error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="/two-factor" id="otpForm">
            @csrf
            <div class="otp">
                <input name="code" inputmode="numeric" pattern="[0-9]*" maxlength="4" autocomplete="one-time-code"
                       autofocus placeholder="••••" />
            </div>
            <button type="submit">تأكيد الدخول</button>
        </form>

        <div class="timer">تنتهي صلاحية الرمز خلال <b id="t">{{ $secondsLeft }}</b> ثانية</div>
        <a class="back" href="/admin/login">العودة لتسجيل الدخول</a>
    </div>

    <script>
        // Countdown; on expiry send the admin back to login.
        var left = {{ (int) $secondsLeft }};
        var el = document.getElementById('t');
        var iv = setInterval(function () {
            left--; if (el) el.textContent = left < 0 ? 0 : left;
            if (left <= 0) { clearInterval(iv); window.location.href = '/admin/login'; }
        }, 1000);
        // Auto-submit once 4 digits are entered.
        var input = document.querySelector('input[name="code"]');
        input.addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
            if (this.value.length === 4) document.getElementById('otpForm').submit();
        });
    </script>
</body>
</html>
