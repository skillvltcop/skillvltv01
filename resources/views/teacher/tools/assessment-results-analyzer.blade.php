<!DOCTYPE html>
@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $translations = [
        'ar' => [
            'title' => 'محلل نتائج التقويم',
            'intro' => 'أدخل نتائج المتعلمين لتحليل نتائج المجموعة وتحديد من يحتاج إلى الدعم.',
            'name' => 'اسم المتعلم',
            'score' => 'النقطة المحصل عليها',
            'max' => 'النقطة القصوى',
            'add' => 'إضافة متعلم',
            'run' => 'تحليل النتائج',
            'remove' => 'حذف',
            'loading' => 'جارٍ تحميل الأداة...',
            'error' => 'تعذر تنفيذ التحليل. تحقق من البيانات وحاول مجددًا.',
            'count' => 'عدد المتعلمين',
            'average' => 'متوسط النسبة',
            'ready' => 'مستعدون',
            'support' => 'يحتاجون إلى الدعم',
            'rate' => 'نسبة النجاح',
            'learner' => 'المتعلم',
            'percentage' => 'النسبة المئوية',
            'position' => 'التموضع',
            'ready_value' => 'مستعد',
            'support_value' => 'يحتاج إلى دعم',
            'invalid' => 'أدخل اسم كل متعلم ونقطة صحيحة لا تتجاوز النقطة القصوى.',
        ],
        'fr' => [
            'title' => 'Analyseur des résultats d’évaluation',
            'intro' => 'Saisissez les résultats pour analyser le groupe et repérer les besoins de soutien.',
            'name' => 'Nom de l’apprenant',
            'score' => 'Score obtenu',
            'max' => 'Score maximal',
            'add' => 'Ajouter un apprenant',
            'run' => 'Analyser les résultats',
            'remove' => 'Supprimer',
            'loading' => 'Chargement de l’outil...',
            'error' => 'Analyse impossible. Vérifiez les données et réessayez.',
            'count' => 'Nombre d’apprenants',
            'average' => 'Pourcentage moyen',
            'ready' => 'Prêts',
            'support' => 'Besoin de soutien',
            'rate' => 'Taux de réussite',
            'learner' => 'Apprenant',
            'percentage' => 'Pourcentage',
            'position' => 'Positionnement',
            'ready_value' => 'Prêt',
            'support_value' => 'Besoin de soutien',
            'invalid' => 'Saisissez chaque nom et un score valide ne dépassant pas le maximum.',
        ],
        'en' => [
            'title' => 'Assessment Results Analyzer',
            'intro' => 'Enter learner results to analyze the group and identify support needs.',
            'name' => 'Learner name',
            'score' => 'Score obtained',
            'max' => 'Maximum score',
            'add' => 'Add learner',
            'run' => 'Analyze results',
            'remove' => 'Remove',
            'loading' => 'Loading tool...',
            'error' => 'Analysis failed. Check the data and try again.',
            'count' => 'Learners',
            'average' => 'Average percentage',
            'ready' => 'Ready',
            'support' => 'Need support',
            'rate' => 'Success rate',
            'learner' => 'Learner',
            'percentage' => 'Percentage',
            'position' => 'Positioning',
            'ready_value' => 'Ready',
            'support_value' => 'Needs support',
            'invalid' => 'Enter each learner name and a valid score not exceeding the maximum.',
        ],
    ];
    $t = $translations[$locale] ?? $translations['ar'];
@endphp
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $t['title'] }}</title>
<style>
:root{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;color:#172033;background:#f5f7fb}*{box-sizing:border-box}body{margin:0}main{max-width:1000px;margin:auto;padding:32px 16px 56px}.card{background:white;border:1px solid #e2e8f0;border-radius:18px;padding:24px;margin-bottom:18px;box-shadow:0 8px 24px #0f172a0a}h1{margin:0 0 8px;font-size:28px}.intro{color:#64748b;line-height:1.7;margin:0 0 20px}.row{display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:10px;align-items:end;margin:12px 0}.field label{display:block;font-size:14px;font-weight:600;margin-bottom:6px}.field input{width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:9px;font:inherit}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}button{border:0;border-radius:9px;padding:11px 16px;font:inherit;cursor:pointer}.primary{background:#172033;color:white}.secondary{background:#e2e8f0;color:#172033}.remove{background:#fff1f2;color:#be123c}.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}.stat{background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px}.stat span{display:block;color:#64748b;font-size:14px}.stat strong{display:block;font-size:24px;margin-top:6px}table{width:100%;border-collapse:collapse;margin-top:12px}th,td{text-align:start;padding:12px;border-bottom:1px solid #e2e8f0}#error{color:#b91c1c;margin-top:12px;white-space:pre-wrap}[hidden]{display:none!important}@media(max-width:680px){.row{grid-template-columns:1fr 1fr}.row .name-field{grid-column:1/-1}.row .remove{grid-column:1/-1}}
</style>
</head>
<body>
<main>
<section class="card">
<h1>{{ $t['title'] }}</h1>
<p class="intro">{{ $t['intro'] }}</p>
<form id="analysis-form">
<div id="learners"></div>
<div class="actions">
<button class="secondary" type="button" id="add">{{ $t['add'] }}</button>
<button class="primary" type="submit" id="run">{{ $t['run'] }}</button>
</div>
<div id="error" role="alert" hidden></div>
</form>
</section>
<section id="results" class="card" hidden>
<div class="stats">
<div class="stat"><span>{{ $t['count'] }}</span><strong id="count">—</strong></div>
<div class="stat"><span>{{ $t['average'] }}</span><strong id="average">—</strong></div>
<div class="stat"><span>{{ $t['ready'] }}</span><strong id="ready">—</strong></div>
<div class="stat"><span>{{ $t['support'] }}</span><strong id="support">—</strong></div>
<div class="stat"><span>{{ $t['rate'] }}</span><strong id="rate">—</strong></div>
</div>
<div style="overflow-x:auto;margin-top:20px">
<table><thead><tr><th>{{ $t['learner'] }}</th><th>{{ $t['score'] }}</th><th>{{ $t['percentage'] }}</th><th>{{ $t['position'] }}</th></tr></thead><tbody id="learner-results"></tbody></table>
</div>
</section>
</main>
<script>
(() => {
const labels = @json($t);
const container = document.getElementById('learners');
const form = document.getElementById('analysis-form');
const error = document.getElementById('error');
const results = document.getElementById('results');
const run = document.getElementById('run');
const add = document.getElementById('add');
const setFormBusy = busy => {
    add.disabled = busy;
    run.disabled = busy;
    container.querySelectorAll('input, .remove').forEach(control => { control.disabled = busy; });
};
const addRow = (name = '', score = '', max = '20') => {
    const row = document.createElement('div');
    row.className = 'row';
    row.innerHTML = '<div class="field name-field"><label></label><input class="learner-name" required maxlength="120"></div><div class="field"><label></label><input class="learner-score" type="number" min="0" step="any" required></div><div class="field"><label></label><input class="learner-max" type="number" min="0.000001" step="any" required></div><button class="remove" type="button"></button>';
    const fields = row.querySelectorAll('label');
    fields[0].textContent = labels.name; fields[1].textContent = labels.score; fields[2].textContent = labels.max;
    row.querySelector('.remove').textContent = labels.remove;
    row.querySelector('.learner-name').value = name;
    row.querySelector('.learner-score').value = score;
    row.querySelector('.learner-max').value = max;
    row.querySelector('.remove').addEventListener('click', () => { if (container.children.length > 1) row.remove(); });
    container.appendChild(row);
};
addRow(); addRow(); addRow();
add.addEventListener('click', () => addRow());
form.addEventListener('submit', async event => {
    event.preventDefault();
    error.hidden = true; results.hidden = true;
    const learners = [...container.querySelectorAll('.row')].map(row => ({
        name: row.querySelector('.learner-name').value.trim(),
        score: Number(row.querySelector('.learner-score').value),
        max_score: Number(row.querySelector('.learner-max').value)
    }));
    if (!learners.length || learners.some(x => !x.name || !Number.isFinite(x.score) || !Number.isFinite(x.max_score) || x.max_score <= 0 || x.score < 0 || x.score > x.max_score)) {
        error.textContent = labels.invalid; error.hidden = false; return;
    }
    setFormBusy(true);
    try {
        const response = await fetch('/api/teacher/tools/assessment-results-analyzer/execute', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body: JSON.stringify({input:{learners},context:{locale:document.documentElement.lang}})
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.status !== 'completed' || !payload.result) {
            const validationMessages = payload.errors && typeof payload.errors === 'object'
                ? Object.values(payload.errors).flat().filter(message => typeof message === 'string' && message.trim() !== '')
                : [];
            throw new Error(validationMessages[0] || payload.message || labels.error);
        }
        const summary = payload.result;
        document.getElementById('count').textContent = summary.count;
        document.getElementById('average').textContent = Number(summary.average_percentage).toFixed(2) + '%';
        document.getElementById('ready').textContent = summary.ready_count;
        document.getElementById('support').textContent = summary.needs_support_count;
        document.getElementById('rate').textContent = Number(summary.success_rate).toFixed(2) + '%';
        const body = document.getElementById('learner-results'); body.replaceChildren();
        for (const learner of summary.learners) {
            const tr = document.createElement('tr');
            const scoreCell = learner.score + ' / ' + learner.max_score;
            const position = learner.positioning === 'ready' ? labels.ready_value : labels.support_value;
            for (const value of [learner.name, scoreCell, Number(learner.percentage).toFixed(2) + '%', position]) {
                const td = document.createElement('td'); td.textContent = value; tr.appendChild(td);
            }
            body.appendChild(tr);
        }
        results.hidden = false;
    } catch (e) {
        error.textContent = e.message || labels.error; error.hidden = false;
    } finally {
        setFormBusy(false);
    }
});
})();
</script>
</body>
</html>
