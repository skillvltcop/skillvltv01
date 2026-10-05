<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>أداة التموضع التقويمي</title>
    <style>
        :root { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f6f8fb; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; }
        main { width: min(720px, calc(100% - 32px)); margin: 0 auto; padding: 48px 0; }
        .card { background: white; border: 1px solid #e5e7eb; border-radius: 20px; padding: 28px; box-shadow: 0 10px 30px rgba(15, 23, 42, .06); }
        .eyebrow { color: #64748b; font-size: 14px; margin-bottom: 8px; }
        h1 { margin: 0 0 10px; font-size: 30px; }
        .purpose { color: #475569; line-height: 1.8; margin: 0 0 24px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input { width: 100%; padding: 13px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 18px; }
        button { margin-top: 16px; width: 100%; padding: 13px 16px; border: 0; border-radius: 10px; background: #172033; color: white; font-size: 16px; cursor: pointer; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
        .badge { background: #f1f5f9; color: #475569; padding: 6px 10px; border-radius: 999px; font-size: 14px; }
        [role="status"] { margin-top: 20px; padding: 14px; border-radius: 10px; background: #f8fafc; }
    </style>
</head>
<body>
<main>
    <section class="card" aria-labelledby="tool-title">
        <div id="loading">جارٍ تحميل الأداة...</div>

        <div id="tool" hidden>
            <div class="eyebrow">أداة تعليمية</div>
            <h1 id="tool-title"></h1>
            <p class="purpose" id="tool-purpose"></p>

            <div class="meta">
                <span class="badge" id="tool-level"></span>
                <span class="badge" id="tool-version"></span>
            </div>

            <label for="score">نقطة المتعلم</label>
            <input id="score" type="number" min="0" max="100" inputmode="numeric">
            <button id="execute" type="button" disabled>تحديد التموضع</button>

            <div id="status" role="status" hidden></div>
        </div>

        <div id="error" role="alert" hidden></div>
    </section>
</main>

<script>
(async () => {
    const loading = document.getElementById('loading');
    const tool = document.getElementById('tool');
    const error = document.getElementById('error');
    const score = document.getElementById('score');
    const execute = document.getElementById('execute');
    const status = document.getElementById('status');

    try {
        const response = await fetch('/api/teacher/blueprints/discover', {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' },
        });

        if (!response.ok) {
            throw new Error('تعذر تحميل الأدوات التعليمية.');
        }

        const payload = await response.json();
        const blueprint = payload.data?.find(
            item => item.title === 'Assessment Positioning'
        );

        if (!blueprint) {
            throw new Error('أداة التموضع التقويمي غير متاحة.');
        }

        document.getElementById('tool-title').textContent = blueprint.title;
        document.getElementById('tool-purpose').textContent = blueprint.purpose ?? '';
        document.getElementById('tool-level').textContent = 'المستوى: ' + (blueprint.target_level ?? '—');
        document.getElementById('tool-version').textContent = blueprint.version ?? '';

        loading.hidden = true;
        tool.hidden = false;
        execute.disabled = false;

        execute.addEventListener('click', async () => {
            status.hidden = true;
            error.hidden = true;

            const value = score.value.trim();

            if (value === '') {
                error.textContent = 'يرجى إدخال نقطة المتعلم.';
                error.hidden = false;
                return;
            }

            execute.disabled = true;

            try {
                const response = await fetch(
                    '/api/teacher/tools/assessment-positioning/execute',
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            input: { score: Number(value) },
                            context: {},
                        }),
                    }
                );

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(
                        payload.message ?? 'تعذر تنفيذ أداة التموضع التقويمي.'
                    );
                }

                const result = payload.result;

                status.textContent = result?.positioning === 'ready'
                    ? 'جاهز'
                    : 'يحتاج إلى دعم';
                status.hidden = false;
            } catch (exception) {
                error.textContent = exception.message;
                error.hidden = false;
            } finally {
                execute.disabled = false;
            }
        });
    } catch (exception) {
        loading.hidden = true;
        error.textContent = exception.message;
        error.hidden = false;
    }
})();
</script>
</body>
</html>
