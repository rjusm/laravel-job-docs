<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ config('job-docs.info.title', 'API Docs') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
    :root {
        --bg: #0f1115;
        --panel: #161922;
        --panel-alt: #1d212c;
        --border: #2a2f3c;
        --text: #e6e8ee;
        --text-dim: #9aa1b2;
        --accent: #5b8def;
        --ok: #3ecf8e;
        --err: #e35d6a;
        --mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    }
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: -apple-system, Segoe UI, Roboto, Helvetica, Arial, sans-serif;
        background: var(--bg);
        color: var(--text);
        height: 100vh;
        overflow: hidden;
    }
    .layout { display: flex; height: 100vh; }
    .sidebar {
        width: 320px;
        min-width: 320px;
        background: var(--panel);
        border-right: 1px solid var(--border);
        display: flex;
        flex-direction: column;
    }
    .sidebar-header {
        padding: 16px;
        border-bottom: 1px solid var(--border);
    }
    .sidebar-header h1 {
        font-size: 15px;
        margin: 0 0 10px;
        font-weight: 600;
    }
    .sidebar-header a {
        display: inline-block;
        font-size: 12px;
        color: var(--accent);
        text-decoration: none;
        margin-bottom: 10px;
    }
    .field-row { display: flex; gap: 6px; margin-bottom: 6px; }
    .search, select, textarea, .btn {
        font-family: inherit;
        font-size: 13px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--panel-alt);
        color: var(--text);
        outline: none;
    }
    .search { width: 100%; padding: 8px 10px; }
    .search:focus, select:focus, textarea:focus { border-color: var(--accent); }
    select { padding: 6px 8px; }
    .group-by { width: 130px; flex: 0 0 130px; }
    .tree { flex: 1; overflow-y: auto; padding: 8px 0; }
    .group { border-bottom: 1px solid var(--border); }
    .group summary {
        cursor: pointer;
        padding: 8px 16px;
        font-size: 13px;
        font-weight: 600;
        color: var(--text-dim);
        list-style: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .group summary::-webkit-details-marker { display: none; }
    .group summary .count {
        font-size: 11px;
        color: var(--text-dim);
        background: var(--panel-alt);
        padding: 1px 6px;
        border-radius: 10px;
    }
    .item {
        padding: 6px 16px 6px 26px;
        font-size: 13px;
        cursor: pointer;
        color: var(--text);
        border-left: 2px solid transparent;
    }
    .item:hover { background: var(--panel-alt); }
    .item.active { background: var(--panel-alt); border-left-color: var(--accent); color: var(--accent); }
    .main { flex: 1; overflow-y: auto; padding: 32px 40px; }
    .empty { color: var(--text-dim); font-size: 14px; margin-top: 40px; }
    .detail h2 { margin: 0 0 4px; font-size: 20px; }
    .detail .subtitle { color: var(--text-dim); font-size: 13px; margin-bottom: 20px; }
    .badge {
        display: inline-block;
        font-size: 11px;
        font-family: var(--mono);
        background: var(--panel-alt);
        border: 1px solid var(--border);
        color: var(--text-dim);
        padding: 2px 8px;
        border-radius: 4px;
        margin-right: 6px;
    }
    .section { margin-top: 24px; }
    .section h3 { font-size: 13px; text-transform: uppercase; letter-spacing: .04em; color: var(--text-dim); margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; font-size: 13px; }
    th, td { text-align: left; padding: 8px 10px; border-bottom: 1px solid var(--border); }
    th { color: var(--text-dim); font-weight: 500; }
    td.field { font-family: var(--mono); color: var(--accent); }
    td.rules { font-family: var(--mono); color: var(--text-dim); }
    .required-yes { color: var(--ok); }
    .required-no { color: var(--text-dim); }
    .code-block {
        position: relative;
        background: var(--panel);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 16px;
    }
    .code-block pre {
        margin: 0;
        font-family: var(--mono);
        font-size: 12.5px;
        white-space: pre-wrap;
        word-break: break-word;
        color: var(--text);
    }
    .copy-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: var(--panel-alt);
        border: 1px solid var(--border);
        color: var(--text);
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
    }
    .copy-btn:hover { border-color: var(--accent); }
    .try-it { background: var(--panel); border: 1px solid var(--border); border-radius: 8px; padding: 16px; }
    .try-it .row { display: flex; gap: 8px; margin-bottom: 10px; }
    .try-it select { flex: 1; }
    textarea#request-body {
        width: 100%;
        min-height: 160px;
        padding: 12px;
        font-family: var(--mono);
        font-size: 12.5px;
        resize: vertical;
    }
    .btn {
        cursor: pointer;
        padding: 8px 16px;
        font-weight: 600;
        color: var(--text);
    }
    .btn.primary { background: var(--accent); border-color: var(--accent); color: #0b1220; }
    .btn.primary:hover { opacity: .9; }
    .btn:disabled { opacity: .5; cursor: default; }
    .response-block {
        margin-top: 12px;
        background: var(--panel-alt);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 12px;
        font-family: var(--mono);
        font-size: 12.5px;
        white-space: pre-wrap;
        word-break: break-word;
        max-height: 320px;
        overflow-y: auto;
    }
    .response-block.status-ok { border-color: var(--ok); }
    .response-block.status-err { border-color: var(--err); }
    .hint { color: var(--text-dim); font-size: 12px; margin-top: 8px; }
</style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="sidebar-header">
            <h1>{{ config('job-docs.info.title', 'API Docs') }}</h1>
            <a href="{{ $openapiUrl }}" target="_blank">openapi.json &#8599;</a>
            <div class="field-row">
                <input class="search" id="search" type="text" placeholder="Search...">
                <select class="group-by" id="group-by"></select>
            </div>
        </div>
        <div class="tree" id="tree"></div>
    </div>
    <div class="main">
        <div class="empty" id="empty">Select an item from the list to see its validation rules and example payload.</div>
        <div class="detail" id="detail" style="display:none"></div>
    </div>
</div>

<script>
const CATALOG = @json($catalog);
const GROUP1_LABEL = @json($group1Label);
const GROUP2_LABEL = @json($group2Label);
const ALLOW_TRY_IT = @json($allowTryIt);
const ENDPOINTS = @json($endpoints);

const treeEl = document.getElementById('tree');
const detailEl = document.getElementById('detail');
const emptyEl = document.getElementById('empty');
const searchEl = document.getElementById('search');
const groupByEl = document.getElementById('group-by');

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

// Flatten CATALOG into a single list once.
const ITEMS = [];
Object.keys(CATALOG).forEach((group1) => {
    Object.keys(CATALOG[group1]).forEach((group2) => {
        ITEMS.push({ group1, group2, entry: CATALOG[group1][group2] });
    });
});

// Discover distinct meta keys (e.g. "queue") across every entry, in first-seen order.
const metaKeys = [];
ITEMS.forEach(({ entry }) => {
    Object.keys(entry.meta || {}).forEach((k) => {
        if (!metaKeys.includes(k)) metaKeys.push(k);
    });
});

const GROUP_OPTIONS = [
    { key: 'group1', label: GROUP1_LABEL },
    { key: 'group2', label: GROUP2_LABEL },
    ...metaKeys.map((k) => ({ key: 'meta:' + k, label: k.charAt(0).toUpperCase() + k.slice(1) })),
];

GROUP_OPTIONS.forEach((opt) => {
    const el = document.createElement('option');
    el.value = opt.key;
    el.textContent = opt.label;
    groupByEl.appendChild(el);
});

function bucketKeyFor(item, groupBy) {
    if (groupBy === 'group1') return { bucket: item.group1, leaf: item.group2 };
    if (groupBy === 'group2') return { bucket: item.group2, leaf: item.group1 };
    if (groupBy.startsWith('meta:')) {
        const metaKey = groupBy.slice(5);
        const bucket = (item.entry.meta && item.entry.meta[metaKey]) || 'unknown';
        return { bucket, leaf: `${item.group1} / ${item.group2}` };
    }
    return { bucket: item.group1, leaf: item.group2 };
}

function renderTree() {
    const filter = (searchEl.value || '').toLowerCase();
    const groupBy = groupByEl.value;
    treeEl.innerHTML = '';

    const buckets = {};
    ITEMS.forEach((item) => {
        const { bucket, leaf } = bucketKeyFor(item, groupBy);
        const haystack = `${item.group1} ${item.group2} ${bucket}`.toLowerCase();
        if (filter && !haystack.includes(filter)) return;

        (buckets[bucket] ??= []).push({ leaf, item });
    });

    Object.keys(buckets).sort().forEach((bucket) => {
        const rows = buckets[bucket].sort((a, b) => a.leaf.localeCompare(b.leaf));

        const details = document.createElement('details');
        details.className = 'group';
        details.open = !!filter;

        const summary = document.createElement('summary');
        summary.innerHTML = `<span>${escapeHtml(bucket)}</span><span class="count">${rows.length}</span>`;
        details.appendChild(summary);

        rows.forEach(({ leaf, item }) => {
            const el = document.createElement('div');
            el.className = 'item';
            el.textContent = leaf;
            el.addEventListener('click', () => selectItem(item, el));
            details.appendChild(el);
        });

        treeEl.appendChild(details);
    });
}

function selectItem(item, el) {
    document.querySelectorAll('.item.active').forEach((n) => n.classList.remove('active'));
    if (el) el.classList.add('active');

    const entry = item.entry;
    emptyEl.style.display = 'none';
    detailEl.style.display = 'block';

    const rules = entry.rules || {};
    const rows = Object.keys(rules).map((field) => {
        const raw = rules[field];
        const ruleStr = Array.isArray(raw) ? raw.map((r) => (typeof r === 'string' ? r : JSON.stringify(r))).join(', ') : raw;
        const required = (entry.schema && entry.schema.required || []).includes(field);
        return `<tr>
            <td class="field">${escapeHtml(field)}</td>
            <td class="rules">${escapeHtml(ruleStr)}</td>
            <td class="${required ? 'required-yes' : 'required-no'}">${required ? 'required' : 'optional'}</td>
        </tr>`;
    }).join('');

    const example = JSON.stringify(entry.example, null, 2);
    const requestExample = JSON.stringify(entry.request ?? entry.example, null, 2);
    const meta = entry.meta || {};
    const metaBadges = Object.keys(meta).map((k) => `<span class="badge">${escapeHtml(k)}: ${escapeHtml(meta[k])}</span>`).join('');

    const tryItHtml = (ALLOW_TRY_IT && ENDPOINTS.length > 0) ? `
        <div class="section">
            <h3>Try it</h3>
            <div class="try-it">
                <div class="row">
                    <select id="endpoint-select"></select>
                    <button class="btn primary" id="send-btn">Send</button>
                </div>
                <textarea id="request-body" spellcheck="false">${escapeHtml(requestExample)}</textarea>
                <div class="hint">Sends a real HTTP request from your browser to this app.</div>
                <div id="response-block" class="response-block" style="display:none"></div>
            </div>
        </div>
    ` : '';

    detailEl.innerHTML = `
        <h2>${escapeHtml(item.group1)} / ${escapeHtml(item.group2)}</h2>
        <div class="subtitle">
            <span class="badge">${escapeHtml(entry.class || '')}</span>${metaBadges}
        </div>
        <div class="section">
            <h3>Validation rules</h3>
            <table>
                <thead><tr><th>Field</th><th>Rules</th><th></th></tr></thead>
                <tbody>${rows || '<tr><td colspan="3" class="required-no">No fields declared.</td></tr>'}</tbody>
            </table>
        </div>
        <div class="section">
            <h3>Example payload</h3>
            <div class="code-block">
                <button class="copy-btn" id="copy-btn">Copy</button>
                <pre id="example-json">${escapeHtml(example)}</pre>
            </div>
        </div>
        ${tryItHtml}
    `;

    document.getElementById('copy-btn').addEventListener('click', () => {
        navigator.clipboard.writeText(example).then(() => {
            const btn = document.getElementById('copy-btn');
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1200);
        });
    });

    if (ALLOW_TRY_IT && ENDPOINTS.length > 0) {
        setupTryIt();
    }
}

function setupTryIt() {
    const endpointSelect = document.getElementById('endpoint-select');
    ENDPOINTS.forEach((ep, i) => {
        const opt = document.createElement('option');
        opt.value = i;
        opt.textContent = ep.label || `${ep.method} ${ep.path}`;
        endpointSelect.appendChild(opt);
    });

    document.getElementById('send-btn').addEventListener('click', sendTryItRequest);
}

async function sendTryItRequest() {
    const btn = document.getElementById('send-btn');
    const responseEl = document.getElementById('response-block');
    const endpoint = ENDPOINTS[document.getElementById('endpoint-select').value];
    const method = (endpoint.method || 'POST').toUpperCase();

    let body;
    try {
        body = JSON.parse(document.getElementById('request-body').value);
    } catch (e) {
        responseEl.style.display = 'block';
        responseEl.className = 'response-block status-err';
        responseEl.textContent = 'Invalid JSON: ' + e.message;
        return;
    }

    btn.disabled = true;
    responseEl.style.display = 'block';
    responseEl.className = 'response-block';
    responseEl.textContent = 'Sending...';

    try {
        const res = await fetch(endpoint.path, {
            method,
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: method === 'GET' ? undefined : JSON.stringify(body),
        });
        const text = await res.text();
        let pretty = text;
        try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) { /* not JSON, show as-is */ }

        responseEl.className = 'response-block ' + (res.ok ? 'status-ok' : 'status-err');
        responseEl.textContent = `HTTP ${res.status} ${res.statusText}\n\n${pretty}`;
    } catch (err) {
        responseEl.className = 'response-block status-err';
        responseEl.textContent = 'Request failed: ' + err.message;
    } finally {
        btn.disabled = false;
    }
}

searchEl.addEventListener('input', renderTree);
groupByEl.addEventListener('change', renderTree);
renderTree();
</script>
</body>
</html>
