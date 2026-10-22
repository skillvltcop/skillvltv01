<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ ['ar' => 'تسجيل الدخول | SkillVLT', 'fr' => 'Connexion | SkillVLT', 'en' => 'Sign in | SkillVLT'][app()->getLocale()] ?? 'Sign in | SkillVLT' }}</title>
    <style>
        :root{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#172033;background:#f4f7fb;font-synthesis:none}
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px}
        .shell{width:min(100%,440px)}.brand{display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:24px;font-size:23px;font-weight:750;letter-spacing:-.04em;color:#172033}
        .mark{display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:#172554;color:#fff;font-size:19px}
        .panel{background:#fff;border:1px solid #e5eaf2;border-radius:22px;padding:clamp(24px,6vw,38px);box-shadow:0 20px 60px rgba(23,32,51,.08)}
        h1{font-size:27px;letter-spacing:-.035em;margin:0 0 8px}.subtitle{color:#64748b;line-height:1.7;margin:0 0 27px}
        .field{margin-bottom:18px}.field label{display:block;font-size:14px;font-weight:650;margin-bottom:8px}
        input[type=email],input[type=password],input[type=text]{width:100%;min-height:48px;padding:11px 13px;border:1px solid #cbd5e1;border-radius:10px;background:#fff;color:#172033;font:inherit;outline:none}
        input:focus{border-color:#475569;box-shadow:0 0 0 3px rgba(71,85,105,.13)}input[aria-invalid=true]{border-color:#b91c1c}
        .password-wrap{position:relative}.password-wrap input{padding-inline-end:76px}.reveal{position:absolute;inset-inline-end:8px;top:7px;min-height:34px;border:0;background:transparent;color:#475569;font-weight:650;cursor:pointer}
        .row{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:3px 0 22px;font-size:14px}
        .remember{display:flex;align-items:center;gap:8px;color:#475569}.remember input{width:16px;height:16px;accent-color:#172554}
        a{color:#334e8c;text-decoration:none;font-weight:600}a:hover{text-decoration:underline}
        .submit{width:100%;min-height:49px;border:0;border-radius:11px;background:#172554;color:white;font:inherit;font-weight:700;cursor:pointer}
        .submit:hover{background:#243b75}.alert{padding:12px 14px;margin-bottom:20px;border-radius:10px;background:#fff1f2;color:#9f1239;border:1px solid #fecdd3;font-size:14px;line-height:1.6}
        .locale{display:flex;justify-content:center;align-items:center;gap:10px;margin-top:20px;color:#64748b;font-size:13px}
        select{padding:7px 10px;border:1px solid #d7deea;border-radius:9px;background:#fff;color:#334155;font:inherit}
        .foot{text-align:center;color:#94a3b8;font-size:12px;margin-top:22px}
        @media(max-width:420px){body{padding:16px}.panel{border-radius:17px}.row{align-items:flex-start}}
    </style>
</head>
<body>
<main class="shell">
    <div class="brand"><span class="mark" aria-hidden="true">S</span><span>SkillVLT</span></div>
    <section class="panel" aria-labelledby="login-title">
        <h1 id="login-title">{{ ['ar' => 'مرحبًا بعودتك', 'fr' => 'Bon retour', 'en' => 'Welcome back'][app()->getLocale()] ?? 'Welcome back' }}</h1>
        <p class="subtitle">{{ ['ar' => 'سجّل الدخول إلى حسابك لمتابعة أدواتك التعليمية.', 'fr' => 'Connectez-vous pour retrouver vos outils pédagogiques.', 'en' => 'Sign in to continue to your teaching tools.'][app()->getLocale()] ?? 'Sign in to continue to your teaching tools.' }}</p>

        @if (session('status'))
            <div class="alert" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert" role="alert" aria-live="polite">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="field">
                <label for="email">{{ ['ar' => 'البريد الإلكتروني', 'fr' => 'Adresse e-mail', 'en' => 'Email address'][app()->getLocale()] ?? 'Email address' }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" dir="ltr" required autofocus maxlength="254" @if($errors->has('email')) aria-invalid="true" @endif>
            </div>
            <div class="field">
                <label for="password">{{ ['ar' => 'كلمة المرور', 'fr' => 'Mot de passe', 'en' => 'Password'][app()->getLocale()] ?? 'Password' }}</label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password" required>
                    <button class="reveal" type="button" id="toggle-password" aria-controls="password" aria-pressed="false">{{ ['ar' => 'إظهار', 'fr' => 'Afficher', 'en' => 'Show'][app()->getLocale()] ?? 'Show' }}</button>
                </div>
            </div>
            <div class="row">
                <label class="remember" for="remember"><input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))> {{ ['ar' => 'تذكرني', 'fr' => 'Se souvenir de moi', 'en' => 'Remember me'][app()->getLocale()] ?? 'Remember me' }}</label>
                <a href="{{ route('password.request') }}">{{ ['ar' => 'نسيت كلمة المرور؟', 'fr' => 'Mot de passe oublié ?', 'en' => 'Forgot password?'][app()->getLocale()] ?? 'Forgot password?' }}</a>
            </div>
            <button class="submit" type="submit">{{ ['ar' => 'تسجيل الدخول', 'fr' => 'Se connecter', 'en' => 'Sign in'][app()->getLocale()] ?? 'Sign in' }}</button>
        </form>
    </section>
    <div class="locale">
        <span>{{ ['ar' => 'اللغة', 'fr' => 'Langue', 'en' => 'Language'][app()->getLocale()] ?? 'Language' }}</span>
        <form method="POST" action="{{ route('locale.update') }}">
            @csrf
            <select name="locale" aria-label="{{ ['ar' => 'اختيار اللغة', 'fr' => 'Choisir la langue', 'en' => 'Choose language'][app()->getLocale()] ?? 'Choose language' }}" onchange="this.form.submit()">
                <option value="ar" @selected(app()->getLocale() === 'ar')>العربية</option>
                <option value="fr" @selected(app()->getLocale() === 'fr')>Français</option>
                <option value="en" @selected(app()->getLocale() === 'en')>English</option>
            </select>
        </form>
    </div>
    <p class="foot">SkillVLT · {{ ['ar' => 'مساحة عملك التعليمية', 'fr' => 'Votre espace de travail pédagogique', 'en' => 'Your teaching workspace'][app()->getLocale()] ?? 'Your teaching workspace' }}</p>
</main>
<script>
(() => {
    const field = document.getElementById('password');
    const button = document.getElementById('toggle-password');
    const labels = @json([
        'show' => ['ar' => 'إظهار', 'fr' => 'Afficher', 'en' => 'Show'][app()->getLocale()] ?? 'Show',
        'hide' => ['ar' => 'إخفاء', 'fr' => 'Masquer', 'en' => 'Hide'][app()->getLocale()] ?? 'Hide',
    ]);
    button.addEventListener('click', () => {
        const visible = field.type === 'password';
        field.type = visible ? 'text' : 'password';
        button.textContent = visible ? labels.hide : labels.show;
        button.setAttribute('aria-pressed', String(visible));
    });
})();
</script>
</body>
</html>
