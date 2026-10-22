<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ['ar' => 'تعيين كلمة مرور جديدة | SkillVLT', 'fr' => 'Nouveau mot de passe | SkillVLT', 'en' => 'Choose a new password | SkillVLT'][app()->getLocale()] ?? 'Choose a new password | SkillVLT' }}</title>
    <style>
        :root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f4f7fb}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}.shell{width:min(100%,440px)}.brand{text-align:center;font-size:23px;font-weight:750;margin-bottom:24px}.panel{background:white;border:1px solid #e5eaf2;border-radius:22px;padding:clamp(24px,6vw,38px);box-shadow:0 20px 60px rgba(23,32,51,.08)}h1{font-size:26px;margin:0 0 10px}.intro{color:#64748b;line-height:1.75;margin:0 0 24px}.field{margin-bottom:18px}label{display:block;font-size:14px;font-weight:650;margin-bottom:8px}input{width:100%;min-height:48px;padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;font:inherit}input:focus{outline:3px solid #e0e7ff;border-color:#475569}.submit{width:100%;min-height:49px;border:0;border-radius:11px;background:#172554;color:white;font:inherit;font-weight:700;cursor:pointer}.alert{padding:12px 14px;margin-bottom:18px;border-radius:10px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;font-size:14px;line-height:1.7}
    </style>
</head>
<body>
<main class="shell">
    <div class="brand">SkillVLT</div>
    <section class="panel">
        <h1>{{ ['ar' => 'تعيين كلمة مرور جديدة', 'fr' => 'Choisir un nouveau mot de passe', 'en' => 'Choose a new password'][app()->getLocale()] ?? 'Choose a new password' }}</h1>
        <p class="intro">{{ ['ar' => 'اختر كلمة مرور قوية لا تقل عن 8 أحرف.', 'fr' => 'Choisissez un mot de passe robuste de 8 caractères minimum.', 'en' => 'Choose a strong password with at least 8 characters.'][app()->getLocale()] ?? 'Choose a strong password with at least 8 characters.' }}</p>
        @if ($errors->any())<div class="alert" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.update') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="field">
                <label for="email">{{ ['ar' => 'البريد الإلكتروني', 'fr' => 'Adresse e-mail', 'en' => 'Email address'][app()->getLocale()] ?? 'Email address' }}</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" autocomplete="username" inputmode="email" dir="ltr" maxlength="254" required autofocus>
            </div>
            <div class="field">
                <label for="password">{{ ['ar' => 'كلمة المرور الجديدة', 'fr' => 'Nouveau mot de passe', 'en' => 'New password'][app()->getLocale()] ?? 'New password' }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <div class="field">
                <label for="password_confirmation">{{ ['ar' => 'تأكيد كلمة المرور', 'fr' => 'Confirmer le mot de passe', 'en' => 'Confirm password'][app()->getLocale()] ?? 'Confirm password' }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" required>
            </div>
            <button class="submit" type="submit">{{ ['ar' => 'حفظ كلمة المرور', 'fr' => 'Enregistrer le mot de passe', 'en' => 'Reset password'][app()->getLocale()] ?? 'Reset password' }}</button>
        </form>
    </section>
</main>
</body>
</html>
