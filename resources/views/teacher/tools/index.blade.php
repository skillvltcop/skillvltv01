<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ ['ar' => 'أدوات المعلم', 'fr' => 'Outils de l’enseignant', 'en' => 'Teacher Tools'][app()->getLocale()] ?? 'Teacher Tools' }}</title>
    <style>
        :root { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f6f8fb; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        main { width: min(920px, calc(100% - 32px)); margin: 0 auto; padding: 48px 0; }
        h1 { margin: 0 0 8px; font-size: 32px; }
        .intro { color: #64748b; margin: 0 0 28px; }
        .tools { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; }
        .card { display: block; background: white; border: 1px solid #e5e7eb; border-radius: 18px; padding: 24px; color: inherit; text-decoration: none; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .card:hover { border-color: #cbd5e1; }
        .eyebrow { color: #64748b; font-size: 14px; margin-bottom: 8px; }
        h2 { margin: 0 0 8px; font-size: 22px; }
        .purpose { color: #475569; line-height: 1.7; margin: 0 0 18px; }
        .meta { display: flex; gap: 8px; flex-wrap: wrap; }
        .badge { background: #f1f5f9; color: #475569; padding: 6px 10px; border-radius: 999px; font-size: 14px; }
        #loading { color: #64748b; }
        #error { color: #b91c1c; }
    </style>
</head>
<body>
<main>
    <div style="display:flex;justify-content:space-between;align-items:center;gap:16px;margin-bottom:24px;">
        <h1 style="margin:0;">{{ ['ar' => 'أدوات المعلم', 'fr' => 'Outils de l’enseignant', 'en' => 'Teacher Tools'][app()->getLocale()] ?? 'Teacher Tools' }}</h1>
        <form method="POST" action="{{ route('locale.update') }}">
            @csrf
            <label for="locale" style="margin-inline-end:8px;">{{ ['ar' => 'اللغة', 'fr' => 'Langue', 'en' => 'Language'][app()->getLocale()] ?? 'Language' }}</label>
            <select id="locale" name="locale" onchange="this.form.submit()">
                <option value="ar" @selected(app()->getLocale() === 'ar')>العربية</option>
                <option value="fr" @selected(app()->getLocale() === 'fr')>Français</option>
                <option value="en" @selected(app()->getLocale() === 'en')>English</option>
            </select>
        </form>
        @if (auth()->user()?->isSuperAdmin())
            <a href="{{ route('admin.authorization.index') }}" style="color:#1d4ed8;text-decoration:none;font-weight:600;">
                {{ ['ar' => 'إدارة الصلاحيات', 'fr' => 'Autorisations', 'en' => 'Authorization'][app()->getLocale()] ?? 'Authorization' }}
            </a>
        @endif
    </div>
    <p class="intro">{{ ['ar' => 'اختر الأداة التي تريد استخدامها.', 'fr' => 'Choisissez l’outil que vous souhaitez utiliser.', 'en' => 'Choose the tool you want to use.'][app()->getLocale()] ?? 'Choose the tool you want to use.' }}</p>

    <div id="loading">{{ ['ar' => 'جارٍ تحميل الأدوات...', 'fr' => 'Chargement des outils...', 'en' => 'Loading tools...'][app()->getLocale()] ?? 'Loading tools...' }}</div>
    <div id="tools" class="tools" hidden></div>
    <div id="error" role="alert" hidden></div>
</main>

<script type="module">
import { loadTeacherTools } from '/js/teacher-tools.js';

loadTeacherTools({
    messages: {
        loadError: @json(['ar' => 'تعذر تحميل الأدوات التعليمية.', 'fr' => 'Impossible de charger les outils pédagogiques.', 'en' => 'Unable to load educational tools.'][app()->getLocale()] ?? 'Unable to load educational tools.'),
    },
    labels: {
        eyebrow: @json(['ar' => 'أداة تعليمية', 'fr' => 'Outil pédagogique', 'en' => 'Educational tool'][app()->getLocale()] ?? 'Educational tool'),
        level: @json(['ar' => 'المستوى: ', 'fr' => 'Niveau : ', 'en' => 'Level: '][app()->getLocale()] ?? 'Level: '),
    },
});
</script>
</body>
</html>
