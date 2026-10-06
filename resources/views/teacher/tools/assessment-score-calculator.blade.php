<!DOCTYPE html>
@php
    $locale = app()->getLocale();
    $isRtl = $locale === 'ar';
    $translations = [
        'ar' => [
            'page_title' => 'حاسبة نتيجة التقويم',
            'loading' => 'جارٍ تحميل الأداة...',
            'educational_tool' => 'أداة تعليمية',
            'level' => 'المستوى',
            'correct' => 'عدد الإجابات الصحيحة',
            'total' => 'المجموع',
            'execute' => 'حساب النتيجة',
            'empty_input' => 'يرجى إدخال عدد الإجابات الصحيحة والمجموع.',
            'load_error' => 'تعذر تحميل الأدوات التعليمية.',
            'tool_unavailable' => 'حاسبة نتيجة التقويم غير متاحة.',
            'execute_error' => 'تعذر تنفيذ حاسبة نتيجة التقويم.',
        ],
        'fr' => [
            'page_title' => 'Calculateur de score d’évaluation',
            'loading' => 'Chargement de l’outil...',
            'educational_tool' => 'Outil pédagogique',
            'level' => 'Niveau',
            'correct' => 'Réponses correctes',
            'total' => 'Total',
            'execute' => 'Calculer le résultat',
            'empty_input' => 'Veuillez saisir le nombre de réponses correctes et le total.',
            'load_error' => 'Impossible de charger les outils pédagogiques.',
            'tool_unavailable' => 'Le calculateur de score d’évaluation n’est pas disponible.',
            'execute_error' => 'Impossible d’exécuter le calculateur de score d’évaluation.',
        ],
        'en' => [
            'page_title' => 'Assessment Score Calculator',
            'loading' => 'Loading tool...',
            'educational_tool' => 'Educational tool',
            'level' => 'Level',
            'correct' => 'Correct answers',
            'total' => 'Total',
            'execute' => 'Calculate result',
            'empty_input' => 'Please enter the number of correct answers and the total.',
            'load_error' => 'Unable to load educational tools.',
            'tool_unavailable' => 'The assessment score calculator is not available.',
            'execute_error' => 'Unable to execute the assessment score calculator.',
        ],
    ];
    $t = $translations[$locale] ?? $translations['ar'];
@endphp
<html lang="{{ $locale }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $t['page_title'] }}</title>
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
        input { width: 100%; padding: 13px 14px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 18px; margin-bottom: 16px; }
        button { margin-top: 0; width: 100%; padding: 13px 16px; border: 0; border-radius: 10px; background: #172033; color: white; font-size: 16px; cursor: pointer; }
        button:disabled { opacity: .5; cursor: not-allowed; }
        .meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 24px; }
        .badge { background: #f1f5f9; color: #475569; padding: 6px 10px; border-radius: 999px; font-size: 14px; }
        [role="status"] { margin-top: 20px; padding: 14px; border-radius: 10px; background: #f8fafc; }
    </style>
</head>
<body>
<main>
    <section class="card" aria-labelledby="tool-title">
        <div id="loading">{{ $t['loading'] }}</div>

        <div id="tool" hidden>
            <div class="eyebrow">{{ $t['educational_tool'] }}</div>
            <h1 id="tool-title"></h1>
            <p class="purpose" id="tool-purpose"></p>

            <div class="meta">
                <span class="badge" id="tool-level"></span>
                <span class="badge" id="tool-version"></span>
            </div>

            <label for="correct">{{ $t['correct'] }}</label>
            <input id="correct" type="number" min="0" inputmode="numeric">

            <label for="total">{{ $t['total'] }}</label>
            <input id="total" type="number" min="1" inputmode="numeric">

            <button id="execute" type="button" disabled>{{ $t['execute'] }}</button>

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
    const correct = document.getElementById('correct');
    const total = document.getElementById('total');
    const execute = document.getElementById('execute');
    const status = document.getElementById('status');
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const translations = @json($t);
    const toolSlug = @json($slug);
    const executionEndpoint = `/api/teacher/tools/${encodeURIComponent(toolSlug)}/execute`;

    try {
        const response = await fetch(`/api/teacher/blueprints/discover?locale=${encodeURIComponent(document.documentElement.lang)}`, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Accept-Language': document.documentElement.lang,
            },
        });

        if (!response.ok) {
            throw new Error(translations.load_error);
        }

        const payload = await response.json();
        const blueprint = (payload.data ?? []).find(item => item.slug === toolSlug);

        if (!blueprint) {
            throw new Error(translations.tool_unavailable);
        }

        document.getElementById('tool-title').textContent = blueprint.title;
        document.getElementById('tool-purpose').textContent = blueprint.purpose ?? '';
        document.getElementById('tool-level').textContent =
            translations.level + ': ' + (blueprint.target_level ?? '—');
        document.getElementById('tool-version').textContent = blueprint.version ?? '';

        loading.hidden = true;
        tool.hidden = false;
        execute.disabled = false;

        execute.addEventListener('click', async () => {
            status.hidden = true;
            error.hidden = true;

            const correctValue = correct.value.trim();
            const totalValue = total.value.trim();

            if (correctValue === '' || totalValue === '') {
                error.textContent = translations.empty_input;
                error.hidden = false;
                return;
            }

            execute.disabled = true;

            try {
                const response = await fetch(
                    executionEndpoint,
                    {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({
                            input: {
                                correct: Number(correctValue),
                                total: Number(totalValue),
                            },
                            context: {},
                        }),
                    }
                );

                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message ?? translations.execute_error);
                }

                const result = payload.result;
                const percentage = Number(result?.percentage);

                status.textContent = Number.isFinite(percentage)
                    ? `${result?.score ?? correctValue} — ${percentage.toFixed(2)}%`
                    : String(result?.score ?? correctValue);
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
