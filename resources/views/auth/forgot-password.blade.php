<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ['ar' => 'استعادة كلمة المرور | SkillVLT', 'fr' => 'Réinitialiser le mot de passe | SkillVLT', 'en' => 'Reset password | SkillVLT'][app()->getLocale()] ?? 'Reset password | SkillVLT' }}</title>
    <style>
        :root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f4f7fb}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.shell{width:min(100%,440px)}.brand{text-align:center;font-size:23px;font-weight:750;margin-bottom:24px}.panel{background:white;border:1px solid #e5eaf2;border-radius:22px;padding:clamp(24px,6vw,38px);box-shadow:0 20px 60px rgba(23,32,51,.08)}h1{font-size:26px;margin:0 0 10px}.intro{color:#64748b;line-height:1.75;margin:0 0 24px}.field{margin-bottom:18px}label{display:block;font-size:14px;font-weight:650;margin-bottom:8px}input{width:100%;min-height:48px;padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}input:focus{outline:3px solid #e0e7ff;border-color:#475569}.submit{width:100%;min-height:49px;border:0;border-radius:11px;background:#172554;color:white;font:inherit;font-weight:700;cursor:pointer}.alert{padding:12px 14px;margin-bottom:18px;border-radius:10px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;font-size:14px;line-height:1.7}.status{background:#f0fdf4;color:#166534;border-color:#bbf7d0}.back{text-align:center;margin:20px 0 0}.back a{color:#334e8c;text-decoration:none;font-weight:650}
    </style>
</head>
<body>
<main class="shell">
    <div class="brand">SkillVLT</div>
    <section class="panel">
        <h1>{{ ['ar' => 'نسيت كلمة المرور؟', 'fr' => 'Mot de passe oublié ?', 'en' => 'Forgot your password?'][app()->getLocale()] ?? 'Forgot your password?' }}</h1>
        <p class="intro">{{ ['ar' => 'أدخل بريدك الإلكتروني وسنرسل إليك رابطًا لإعادة تعيين كلمة المرور إذا كان الحساب مسجلًا.', 'fr' => 'Saisissez votre adresse e-mail pour recevoir un lien de réinitialisation si le compte existe.', 'en' => 'Enter your email and we will send a reset link if an account exists.'][app()->getLocale()] ?? 'Enter your email and we will send a reset link if an account exists.' }}</p>
        @if (session('status'))<div class="alert status" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="field">
                <label for="email">{{ ['ar' => 'البريد الإلكتروني', 'fr' => 'Adresse e-mail', 'en' => 'Email address'][app()->getLocale()] ?? 'Email address' }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" dir="ltr" maxlength="254" required autofocus>
            </div>
            <button class="submit" type="submit">{{ ['ar' => 'إرسال رابط الاستعادة', 'fr' => 'Envoyer le lien', 'en' => 'Send reset link'][app()->getLocale()] ?? 'Send reset link' }}</button>
        </form>
        <p class="back"><a href="{{ route('login') }}">← {{ ['ar' => 'العودة إلى تسجيل الدخول', 'fr' => 'Retour à la connexion', 'en' => 'Back to sign in'][app()->getLocale()] ?? 'Back to sign in' }}</a></p>
    </section>
</main>
</body>
</html>
