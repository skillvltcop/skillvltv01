import test from 'node:test';
import assert from 'node:assert/strict';
import { loadTeacherTools } from '../../public/js/teacher-tools.js';

function makeElement(tag = 'div') {
    return {
        tag,
        hidden: false,
        textContent: '',
        className: '',
        href: '',
        children: [],
        append(...children) {
            this.children.push(...children);
        },
        appendChild(child) {
            this.children.push(child);
        },
    };
}

function makeDocument(locale = 'en') {
    const elements = {
        loading: makeElement(),
        tools: makeElement(),
        error: makeElement(),
    };

    return {
        documentElement: { lang: locale },
        elements,
        getElementById(id) {
            return elements[id];
        },
        createElement(tag) {
            return makeElement(tag);
        },
    };
}

const messages = { loadError: 'Unable to load educational tools.' };
const labels = { eyebrow: 'Educational tool', level: 'Level: ' };

test('renders valid tools and safely skips empty or malformed entries', async () => {
    const documentRef = makeDocument();
    const fetchCalls = [];
    const payload = {
        data: [
            null,
            false,
            {},
            { slug: '' },
            { slug: '   ' },
            { slug: 12 },
            { slug: 'assessment-positioning', title: 'Positioning', purpose: 'Assess progress', target_level: 'Grade 5', version: '1.0' },
            { slug: 'score tool', title: 'Score calculator' },
        ],
    };

    await loadTeacherTools({
        documentRef,
        messages,
        labels,
        fetchImpl: async (...args) => {
            fetchCalls.push(args);
            return { ok: true, json: async () => payload };
        },
    });

    assert.equal(fetchCalls.length, 1);
    assert.equal(fetchCalls[0][0], '/api/teacher/blueprints/discover?locale=en');
    assert.equal(fetchCalls[0][1].credentials, 'same-origin');
    assert.equal(documentRef.elements.loading.hidden, true);
    assert.equal(documentRef.elements.tools.hidden, false);
    assert.equal(documentRef.elements.error.hidden, true);
    assert.equal(documentRef.elements.tools.children.length, 2);

    const [first, second] = documentRef.elements.tools.children;
    assert.equal(first.href, '/teacher/tools/assessment-positioning');
    assert.equal(first.children[1].textContent, 'Positioning');
    assert.equal(first.children[2].textContent, 'Assess progress');
    assert.deepEqual(first.children[3].children.map(child => child.textContent), ['Level: Grade 5', '1.0']);
    assert.equal(second.href, '/teacher/tools/score%20tool');
    assert.equal(second.children[2].textContent, '');
});

test('shows a localized error for a non-success HTTP response', async () => {
    const documentRef = makeDocument('fr');

    await loadTeacherTools({
        documentRef,
        messages,
        labels,
        fetchImpl: async (url, options) => {
            assert.match(url, /locale=fr$/);
            assert.equal(options.headers['Accept-Language'], 'fr');
            return { ok: false, json: async () => ({}) };
        },
    });

    assert.equal(documentRef.elements.loading.hidden, true);
    assert.equal(documentRef.elements.error.hidden, false);
    assert.equal(documentRef.elements.error.textContent, messages.loadError);
    assert.equal(documentRef.elements.tools.hidden, true);
});

test('shows the error state when the network request rejects', async () => {
    const documentRef = makeDocument();

    await loadTeacherTools({
        documentRef,
        messages,
        labels,
        fetchImpl: async () => {
            throw new TypeError('network unavailable');
        },
    });

    assert.equal(documentRef.elements.loading.hidden, true);
    assert.equal(documentRef.elements.error.hidden, false);
    assert.equal(documentRef.elements.error.textContent, 'network unavailable');
    assert.equal(documentRef.elements.tools.hidden, true);
});

test('treats invalid JSON and a non-array data field as empty results without crashing', async (t) => {
    for (const json of [
        async () => { throw new SyntaxError('invalid JSON'); },
        async () => ({ data: { slug: 'not-an-array' } }),
        async () => null,
    ]) {
        await t.test('payload fallback', async () => {
            const documentRef = makeDocument();

            await loadTeacherTools({
                documentRef,
                messages,
                labels,
                fetchImpl: async () => ({ ok: true, json }),
            });

            assert.equal(documentRef.elements.loading.hidden, true);
            assert.equal(documentRef.elements.tools.hidden, false);
            assert.equal(documentRef.elements.tools.children.length, 0);
            assert.equal(documentRef.elements.error.hidden, true);
        });
    }
});
