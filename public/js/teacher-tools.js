export async function loadTeacherTools({
    fetchImpl = fetch,
    documentRef = document,
    messages,
    labels,
}) {
    const loading = documentRef.getElementById('loading');
    const tools = documentRef.getElementById('tools');
    const error = documentRef.getElementById('error');
    const locale = documentRef.documentElement.lang;

    try {
        const response = await fetchImpl(
            `/api/teacher/blueprints/discover?locale=${encodeURIComponent(locale)}`,
            {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Accept-Language': locale,
                },
            },
        );

        if (!response.ok) {
            throw new Error(messages.loadError);
        }

        const payload = await response.json().catch(() => ({}));
        const blueprints = Array.isArray(payload?.data) ? payload.data : [];

        for (const blueprint of blueprints) {
            if (
                !blueprint
                || typeof blueprint !== 'object'
                || typeof blueprint.slug !== 'string'
                || blueprint.slug.trim() === ''
            ) {
                continue;
            }

            const link = documentRef.createElement('a');
            link.className = 'card';
            link.href = '/teacher/tools/' + encodeURIComponent(blueprint.slug);

            const eyebrow = documentRef.createElement('div');
            eyebrow.className = 'eyebrow';
            eyebrow.textContent = labels.eyebrow;

            const title = documentRef.createElement('h2');
            title.textContent = blueprint.title ?? '';

            const purpose = documentRef.createElement('p');
            purpose.className = 'purpose';
            purpose.textContent = blueprint.purpose ?? '';

            const meta = documentRef.createElement('div');
            meta.className = 'meta';

            if (blueprint.target_level) {
                const level = documentRef.createElement('span');
                level.className = 'badge';
                level.textContent = labels.level + blueprint.target_level;
                meta.appendChild(level);
            }

            if (blueprint.version) {
                const version = documentRef.createElement('span');
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
        error.textContent = exception instanceof Error && exception.message
            ? exception.message
            : messages.loadError;
        error.hidden = false;
    }
}
