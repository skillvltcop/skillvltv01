<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إدارة الصلاحيات | SkillVLT</title>
    <style>
        :root { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #172033; background: #f5f7fb; }
        * { box-sizing: border-box; }
        body { margin: 0; }
        main { width: min(1180px, calc(100% - 32px)); margin: 0 auto; padding: 36px 0 64px; }
        h1 { margin: 0 0 8px; font-size: clamp(26px, 4vw, 36px); }
        h2 { margin: 0; font-size: 21px; }
        p { line-height: 1.7; }
        .muted { color: #64748b; }
        .top { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 24px; }
        .top a { color: #334155; text-decoration: none; border: 1px solid #cbd5e1; background: white; padding: 9px 13px; border-radius: 10px; }
        .tabs { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 20px; }
        button, input, select { font: inherit; }
        button { cursor: pointer; }
        .tab, .secondary { background: white; border: 1px solid #cbd5e1; border-radius: 10px; padding: 10px 15px; color: #334155; }
        .tab.active { background: #172033; color: white; border-color: #172033; }
        .primary { background: #1d4ed8; border: 1px solid #1d4ed8; border-radius: 10px; padding: 10px 15px; color: white; }
        button:disabled { opacity: .55; cursor: wait; }
        .panel { display: none; }
        .panel.active { display: block; }
        .section-head { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin: 0 0 16px; }
        .card { background: white; border: 1px solid #e2e8f0; border-radius: 16px; padding: 20px; margin-bottom: 14px; box-shadow: 0 6px 20px rgba(15, 23, 42, .035); }
        .user-head { display: grid; grid-template-columns: 1.2fr 1fr 180px; gap: 12px; align-items: end; margin-bottom: 16px; }
        label.field { display: block; font-size: 13px; color: #475569; }
        label.field input, label.field select { display: block; width: 100%; margin-top: 6px; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; background: white; color: #172033; }
        .permissions { display: grid; grid-template-columns: repeat(auto-fit, minmax(270px, 1fr)); gap: 9px; margin: 14px 0; }
        .permission { display: flex; align-items: flex-start; gap: 9px; border: 1px solid #e2e8f0; border-radius: 10px; padding: 10px; }
        .permission input { margin-top: 4px; }
        .permission span { display: block; font-size: 13px; line-height: 1.5; }
        .permission code { display: block; direction: ltr; text-align: left; color: #64748b; font-size: 11px; margin-top: 3px; overflow-wrap: anywhere; }
        .reason { width: 100%; margin: 8px 0 12px; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; }
        .registry-fields { display: grid; grid-template-columns: 1fr 1.4fr 1.4fr 100px; gap: 10px; align-items: end; }
        .check { display: flex; align-items: center; gap: 8px; padding: 10px 0; }
        .table-wrap { overflow-x: auto; background: white; border: 1px solid #e2e8f0; border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; min-width: 760px; }
        th, td { text-align: right; padding: 12px 14px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        th { background: #f8fafc; font-size: 13px; color: #475569; }
        td { font-size: 13px; }
        .status { min-height: 24px; margin: 12px 0; font-size: 14px; }
        .status.error { color: #b91c1c; }
        .status.success { color: #047857; }
        .pagination { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-top: 16px; }
        .empty { color: #64748b; padding: 18px; background: white; border-radius: 12px; }
        @media (max-width: 720px) {
            main { width: min(100% - 20px, 1180px); padding-top: 20px; }
            .top, .section-head { align-items: flex-start; flex-direction: column; }
            .user-head, .registry-fields { grid-template-columns: 1fr; }
            .card { padding: 14px; }
        }
    </style>
</head>
<body>
<main>
    <header class="top">
        <div>
            <h1>إدارة الصلاحيات</h1>
            <p class="muted" style="margin:0">إدارة المستخدمين والأذونات وسجل أدوات المعلم ومراجعة أحداث التدقيق. جميع العمليات الحساسة تتطلب سببًا وتُسجّل.</p>
        </div>
        <a href="{{ route('teacher.tools.index') }}">العودة إلى أدوات المعلم</a>
    </header>

    <nav class="tabs" aria-label="أقسام الإدارة">
        <button class="tab active" data-tab="users">المستخدمون والصلاحيات</button>
        <button class="tab" data-tab="registry">سجل أدوات المعلم</button>
        <button class="tab" data-tab="audit">سجل التدقيق</button>
    </nav>

    <div id="globalStatus" class="status" role="status" aria-live="polite"></div>

    <section id="panel-users" class="panel active">
        <div class="section-head">
            <div>
                <h2>المستخدمون والصلاحيات</h2>
                <p class="muted">يمنح الدور admin الصلاحيات المحددة فقط؛ أما super_admin فيدير الأدوار ولا يحتاج إلى قائمة صلاحيات.</p>
            </div>
            <button id="refreshUsers" class="secondary">تحديث القائمة</button>
        </div>
        <div id="usersContainer"><div class="empty">جارٍ تحميل المستخدمين…</div></div>
        <div class="pagination">
            <button id="usersPrev" class="secondary" disabled>السابق</button>
            <span id="usersMeta" class="muted"></span>
            <button id="usersNext" class="secondary" disabled>التالي</button>
        </div>
    </section>

    <section id="panel-registry" class="panel">
        <div class="section-head">
            <div>
                <h2>سجل أدوات المعلم</h2>
                <p class="muted">تُستخدم هذه القائمة نفسها في الاكتشاف والتنفيذ وحل صفحة العرض. تعطيل أداة افتراضية ينشئ تجاوزًا محفوظًا.</p>
            </div>
            <button id="refreshRegistry" class="secondary">تحديث السجل</button>
        </div>
        <form id="newToolForm" class="card">
            <h2 style="margin-bottom:14px">إضافة أداة أو تحديث أهلية slug</h2>
            <div class="registry-fields">
                <label class="field">Slug<input name="slug" required pattern="[a-z0-9][a-z0-9-]{0,127}" placeholder="assessment-example"></label>
                <label class="field">Namespace<input name="namespace" required maxlength="255" placeholder="skillvlt.edu.assessment"></label>
                <label class="field">View<input name="view" required maxlength="255" pattern="teacher\.tools\.[a-z0-9-]+" placeholder="teacher.tools.example"></label>
                <label class="check"><input name="enabled" type="checkbox" checked> مفعّلة</label>
            </div>
            <label class="field">سبب التغيير<input class="reason" name="reason" required minlength="10" maxlength="1000" placeholder="اذكر سبب التغيير (10 أحرف على الأقل)"></label>
            <button class="primary" type="submit">حفظ سجل الأداة</button>
        </form>
        <div id="registryContainer"><div class="empty">افتح هذا القسم لتحميل السجل.</div></div>
    </section>

    <section id="panel-audit" class="panel">
        <div class="section-head">
            <div>
                <h2>سجل التدقيق</h2>
                <p class="muted">عرض للقراءة فقط. لا تعرض سجلات التدقيق بيانات المدخلات أو المخرجات الكاملة للتنفيذ.</p>
            </div>
            <button id="refreshAudit" class="secondary">تحديث السجل</button>
        </div>
        <div id="auditContainer"><div class="empty">افتح هذا القسم لتحميل سجل التدقيق.</div></div>
        <div class="pagination">
            <button id="auditPrev" class="secondary" disabled>الأحدث</button>
            <span id="auditMeta" class="muted"></span>
            <button id="auditNext" class="secondary" disabled>الأقدم</button>
        </div>
    </section>
</main>
<script>
(() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const currentUserId = @json($currentUserId);
    const permissionLabels = new Map();
    let usersPage = 1;
    let usersLastPage = 1;
    let auditPage = 1;
    let auditLastPage = 1;
    let registryLoaded = false;
    let auditLoaded = false;

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    })[char]);

    const status = (message, type = '') => {
        const el = document.getElementById('globalStatus');
        el.textContent = message || '';
        el.className = 'status ' + type;
    };

    async function api(path, options = {}) {
        const response = await fetch(path, {
            credentials: 'same-origin',
            ...options,
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                ...(options.headers || {}),
            },
        });
        const body = await response.json().catch(() => ({}));
        if (!response.ok) {
            const validation = body.errors
                ? Object.values(body.errors).flat().join(' ')
                : '';
            throw new Error(validation || body.message || ('فشل الطلب (' + response.status + ')'));
        }
        return body;
    }

    function setBusy(button, busy) {
        if (button) button.disabled = busy;
    }

    async function loadPermissions() {
        const result = await api('/api/admin/permissions');
        permissionLabels.clear();
        for (const permission of result.data || []) {
            permissionLabels.set(permission.key, permission.description);
        }
    }

    async function loadUsers(page = 1) {
        usersPage = page;
        const container = document.getElementById('usersContainer');
        container.innerHTML = '<div class="empty">جارٍ تحميل المستخدمين…</div>';
        try {
            const result = await api('/api/admin/users?per_page=25&page=' + usersPage);
            const users = result.data || [];
            usersLastPage = result.meta?.last_page || 1;
            document.getElementById('usersMeta').textContent =
                'الصفحة ' + (result.meta?.current_page || 1) + ' من ' + usersLastPage
                + ' — مجموع المستخدمين: ' + (result.meta?.total || 0);
            document.getElementById('usersPrev').disabled = usersPage <= 1;
            document.getElementById('usersNext').disabled = usersPage >= usersLastPage;

            if (!users.length) {
                container.innerHTML = '<div class="empty">لا يوجد مستخدمون في هذه الصفحة.</div>';
                return;
            }

            container.innerHTML = users.map((user) => {
                const checked = new Set(user.permissions || []);
                const permissions = [...permissionLabels.entries()].map(([key, description]) => {
                    const isChecked = checked.has(key) ? 'checked' : '';
                    return '<label class="permission"><input type="checkbox" value="' + esc(key) + '" ' + isChecked + '>'
                        + '<span>' + esc(description) + '<code>' + esc(key) + '</code></span></label>';
                }).join('');
                const role = user.role || 'user';
                return '<form class="card userForm" data-user-id="' + esc(user.id) + '">'
                    + '<div class="user-head">'
                    + '<label class="field">المستخدم<input value="' + esc(user.name) + ' — ' + esc(user.email) + '" readonly></label>'
                    + '<label class="field">معرّف المستخدم<input value="' + esc(user.id) + '" readonly></label>'
                    + '<label class="field">الدور<select name="role">'
                    + '<option value="user" ' + (role === 'user' ? 'selected' : '') + '>user</option>'
                    + '<option value="admin" ' + (role === 'admin' ? 'selected' : '') + '>admin</option>'
                    + '<option value="super_admin" ' + (role === 'super_admin' ? 'selected' : '') + '>super_admin</option>'
                    + '</select></label></div>'
                    + '<div class="permissions">' + permissions + '</div>'
                    + '<label class="field">سبب التغيير<input class="reason" name="reason" required minlength="10" maxlength="1000" placeholder="سبب واضح ومحدد (10 أحرف على الأقل)"></label>'
                    + '<button class="primary" type="submit">حفظ الدور والصلاحيات</button>'
                    + '<div class="muted" style="font-size:12px;margin-top:8px">تُستخدم الصلاحيات المحددة فقط عند اختيار admin. يجب أن تكون قائمة الصلاحيات فارغة للأدوار الأخرى.</div>'
                    + '</form>';
            }).join('');

            container.querySelectorAll('select[name="role"]').forEach((select) => {
                const update = () => {
                    const form = select.closest('form');
                    const disabled = select.value !== 'admin';
                    form.querySelectorAll('.permissions input').forEach((input) => { input.disabled = disabled; });
                };
                select.addEventListener('change', update);
                update();
            });
        } catch (error) {
            container.innerHTML = '<div class="empty">تعذر تحميل المستخدمين.</div>';
            status(error.message, 'error');
        }
    }

    async function saveUser(form) {
        const button = form.querySelector('button[type="submit"]');
        setBusy(button, true);
        const role = form.querySelector('[name="role"]').value;
        const permissions = role === 'admin'
            ? [...form.querySelectorAll('.permissions input:checked')].map((input) => input.value)
            : [];
        const reason = form.querySelector('[name="reason"]').value.trim();
        try {
            await api('/api/admin/users/' + encodeURIComponent(form.dataset.userId) + '/authorization', {
                method: 'PATCH',
                body: JSON.stringify({ role, permissions, reason }),
            });
            form.querySelector('[name="reason"]').value = '';
            status('تم حفظ الصلاحيات وتسجيل العملية في سجل التدقيق.', 'success');
            if (String(form.dataset.userId) === String(currentUserId) && role !== 'super_admin') {
                status('تم تغيير دور حسابك؛ سيتم نقلك إلى صفحة أدوات المعلم.', 'success');
                window.location.href = '/teacher/tools';
                return;
            }
            await loadUsers(usersPage);
        } catch (error) {
            status(error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    }

    async function loadRegistry() {
        const container = document.getElementById('registryContainer');
        container.innerHTML = '<div class="empty">جارٍ تحميل سجل الأدوات…</div>';
        try {
            const result = await api('/api/admin/teacher-tools');
            const tools = result.data || [];
            container.innerHTML = tools.map((tool) =>
                '<form class="card registryForm" data-slug="' + esc(tool.slug) + '">'
                + '<div class="registry-fields">'
                + '<label class="field">Slug<input name="slug" value="' + esc(tool.slug) + '" readonly></label>'
                + '<label class="field">Namespace<input name="namespace" value="' + esc(tool.namespace) + '" required maxlength="255"></label>'
                + '<label class="field">View<input name="view" value="' + esc(tool.view) + '" required maxlength="255" pattern="teacher\.tools\.[a-z0-9-]+"></label>'
                + '<label class="check"><input name="enabled" type="checkbox" ' + (tool.enabled ? 'checked' : '') + '> مفعّلة</label>'
                + '</div>'
                + '<label class="field">سبب التغيير<input class="reason" name="reason" required minlength="10" maxlength="1000" placeholder="سبب واضح ومحدد"></label>'
                + '<button class="primary" type="submit">حفظ التغيير</button>'
                + '</form>'
            ).join('') || '<div class="empty">لا توجد أدوات مسجلة.</div>';
            registryLoaded = true;
        } catch (error) {
            container.innerHTML = '<div class="empty">تعذر تحميل سجل الأدوات.</div>';
            status(error.message, 'error');
        }
    }

    async function saveRegistry(form, slug) {
        const button = form.querySelector('button[type="submit"]');
        setBusy(button, true);
        const payload = {
            namespace: form.querySelector('[name="namespace"]').value.trim(),
            view: form.querySelector('[name="view"]').value.trim(),
            enabled: form.querySelector('[name="enabled"]').checked,
            reason: form.querySelector('[name="reason"]').value.trim(),
        };
        try {
            await api('/api/admin/teacher-tools/' + encodeURIComponent(slug), {
                method: 'PUT',
                body: JSON.stringify(payload),
            });
            status('تم تحديث سجل الأداة وتسجيل العملية.', 'success');
            if (form.id === 'newToolForm') form.reset();
            await loadRegistry();
        } catch (error) {
            status(error.message, 'error');
        } finally {
            setBusy(button, false);
        }
    }

    async function loadAudit(page = 1) {
        auditPage = page;
        const container = document.getElementById('auditContainer');
        container.innerHTML = '<div class="empty">جارٍ تحميل سجل التدقيق…</div>';
        try {
            const result = await api('/api/admin/audit-events?per_page=25&page=' + auditPage);
            const events = result.data || [];
            auditLastPage = result.meta?.last_page || 1;
            document.getElementById('auditMeta').textContent =
                'الصفحة ' + (result.meta?.current_page || 1) + ' من ' + auditLastPage
                + ' — مجموع الأحداث: ' + (result.meta?.total || 0);
            document.getElementById('auditPrev').disabled = auditPage <= 1;
            document.getElementById('auditNext').disabled = auditPage >= auditLastPage;
            container.innerHTML = '<div class="table-wrap"><table><thead><tr>'
                + '<th>التاريخ</th><th>الفاعل</th><th>العملية</th><th>المورد</th><th>الصلاحية</th><th>السبب</th>'
                + '</tr></thead><tbody>'
                + events.map((event) => '<tr>'
                    + '<td>' + esc(event.created_at) + '</td>'
                    + '<td>' + esc(event.actor_user_id) + '</td>'
                    + '<td>' + esc(event.action) + '</td>'
                    + '<td>' + esc(event.resource_type) + ' / ' + esc(event.resource_id) + '</td>'
                    + '<td>' + esc(event.permission || '—') + '</td>'
                    + '<td>' + esc(event.reason) + '</td>'
                    + '</tr>').join('')
                + '</tbody></table></div>';
            auditLoaded = true;
        } catch (error) {
            container.innerHTML = '<div class="empty">تعذر تحميل سجل التدقيق.</div>';
            status(error.message, 'error');
        }
    }

    document.querySelectorAll('[data-tab]').forEach((button) => {
        button.addEventListener('click', async () => {
            document.querySelectorAll('[data-tab]').forEach((item) => item.classList.toggle('active', item === button));
            document.querySelectorAll('.panel').forEach((panel) => panel.classList.toggle('active', panel.id === 'panel-' + button.dataset.tab));
            status('');
            if (button.dataset.tab === 'registry' && !registryLoaded) await loadRegistry();
            if (button.dataset.tab === 'audit' && !auditLoaded) await loadAudit(1);
        });
    });

    document.getElementById('refreshUsers').addEventListener('click', () => loadUsers(usersPage));
    document.getElementById('usersPrev').addEventListener('click', () => loadUsers(Math.max(1, usersPage - 1)));
    document.getElementById('usersNext').addEventListener('click', () => loadUsers(Math.min(usersLastPage, usersPage + 1)));
    document.getElementById('refreshRegistry').addEventListener('click', loadRegistry);
    document.getElementById('refreshAudit').addEventListener('click', () => loadAudit(auditPage));
    document.getElementById('auditPrev').addEventListener('click', () => loadAudit(Math.max(1, auditPage - 1)));
    document.getElementById('auditNext').addEventListener('click', () => loadAudit(Math.min(auditLastPage, auditPage + 1)));

    document.getElementById('usersContainer').addEventListener('submit', (event) => {
        if (!event.target.matches('.userForm')) return;
        event.preventDefault();
        saveUser(event.target);
    });
    document.getElementById('registryContainer').addEventListener('submit', (event) => {
        if (!event.target.matches('.registryForm')) return;
        event.preventDefault();
        saveRegistry(event.target, event.target.dataset.slug);
    });
    document.getElementById('newToolForm').addEventListener('submit', (event) => {
        event.preventDefault();
        const form = event.target;
        saveRegistry(form, form.querySelector('[name="slug"]').value.trim());
    });

    (async () => {
        try {
            await loadPermissions();
            await loadUsers(1);
        } catch (error) {
            status(error.message, 'error');
        }
    })();
})();
</script>
</body>
</html>
