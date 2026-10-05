<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>أدوات المعلم</title>
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
    <h1>أدوات المعلم</h1>
    <p class="intro">اختر الأداة التي تريد استخدامها.</p>

    <div id="loading">جارٍ تحميل الأدوات...</div>
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
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            throw new Error('تعذر تحميل الأدوات التعليمية.');
        }

        const payload = await response.json();

        for (const blueprint of payload.data ?? []) {
            if (blueprint.title !== 'Assessment Positioning') {
                continue;
            }

            const link = document.createElement('a');
            link.className = 'card';
            link.href = '/teacher/tools/assessment-positioning';

            const eyebrow = document.createElement('div');
            eyebrow.className = 'eyebrow';
            eyebrow.textContent = 'أداة تعليمية';

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
                level.textContent = 'المستوى: ' + blueprint.target_level;
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
