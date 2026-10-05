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
    </div>
    <p class="intro">{{ ['ar' => 'اختر الأداة التي تريد استخدامها.', 'fr' => 'Choisissez l’outil que vous souhaitez utiliser.', 'en' => 'Choose the tool you want to use.'][app()->getLocale()] ?? 'Choose the tool you want to use.' }}</p>

    <div id="loading">{{ ['ar' => 'جارٍ تحميل الأدوات...', 'fr' => 'Chargement des outils...', 'en' => 'Loading tools...'][app()->getLocale()] ?? 'Loading tools...' }}</div>
    <div id="tools" class="tools" hidden></div>
    <div id="error" role="alert" hidden></div>
</main>

<script>
(async () => {
    const loading = document.getElementById('loading');
    const tools = document.getElementById('tools');
    const error = document.getElementById('error');

    try {
        const response = await fetch('/api/teacher/blueprints/discover', {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Accept-Language': document.documentElement.lang,
            },
        });

        if (!response.ok) {
            throw new Error(@json(['ar' => 'تعذر تحميل الأدوات التعليمية.', 'fr' => 'Impossible de charger les outils pédagogiques.', 'en' => 'Unable to load educational tools.'][app()->getLocale()] ?? 'Unable to load educational tools.'));
        }

        const payload = await response.json();

        for (const blueprint of payload.data ?? []) {
            if (blueprint.title !== 'Assessment Positioning' && blueprint.title !== 'Positionnement évaluatif' && blueprint.title !== 'التموضع التقويمي') {
                continue;
            }

            const link = document.createElement('a');
            link.className = 'card';
            link.href = '/teacher/tools/assessment-positioning';

            const eyebrow = document.createElement('div');
            eyebrow.className = 'eyebrow';
            eyebrow.textContent = @json(['ar' => 'أداة تعليمية', 'fr' => 'Outil pédagogique', 'en' => 'Educational tool'][app()->getLocale()] ?? 'Educational tool');

            const title = document.createElement('h2');
            title.textContent = blueprint.title;

            const purpose = document.createElement('p');
            purpose.className = 'purpose';
            purpose.textContent = blueprint.purpose ?? '';

            const meta = document.createElement('div');
            meta.className = 'meta';

            if (blueprint.target_level) {
                const level = document.createElement('span');
                level.className = 'badge';
                level.textContent = @json(['ar' => 'المستوى: ', 'fr' => 'Niveau : ', 'en' => 'Level: '][app()->getLocale()] ?? 'Level: ') + blueprint.target_level;
                meta.appendChild(level);
            }

            if (blueprint.version) {
                const version = document.createElement('span');
                version.className = 'badge';
                version.textContent = blueprint.version;
                meta.appendChild(version);
            }

            link.append(eyebrow, title, purpose, meta);
            tools.appendChild(link);
        }

        loading.hidden = true;
        tools.hidden = false;
    } catch (exception) {
        loading.hidden = true;
        error.textContent = exception.message;
        error.hidden = false;
    }
})();
</script>
</body>
</html>
