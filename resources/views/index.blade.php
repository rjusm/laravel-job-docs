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
        margin-bottom: 4px;
    }
    .search {
        width: 100%;
        padding: 8px 10px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--panel-alt);
        color: var(--text);
        font-size: 13px;
        outline: none;
    }
    .search:focus { border-color: var(--accent); }
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
</style>
</head>
<body>
<div class="layout">
    <div class="sidebar">
        <div class="sidebar-header">
            <h1>{{ config('job-docs.info.title', 'API Docs') }}</h1>
            <a href="{{ $openapiUrl }}" target="_blank">openapi.json &#8599;</a>
            <input class="search" id="search" type="text" placeholder="Search handlers...">
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

const treeEl = document.getElementById('tree');
const detailEl = document.getElementById('detail');
const emptyEl = document.getElementById('empty');
const searchEl = document.getElementById('search');

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function renderTree(filter) {
    filter = (filter || '').toLowerCase();
    treeEl.innerHTML = '';

    Object.keys(CATALOG).sort().forEach((group1) => {
        const group2Map = CATALOG[group1];
        const keys = Object.keys(group2Map).filter((group2) => {
            if (!filter) return true;
            return (group1 + ' ' + group2).toLowerCase().includes(filter);
        });
        if (keys.length === 0) return;

        const details = document.createElement('details');
        details.className = 'group';
        details.open = !!filter;

        const summary = document.createElement('summary');
        summary.innerHTML = `<span>${escapeHtml(group1)}</span><span class="count">${keys.length}</span>`;
        details.appendChild(summary);

        keys.sort().forEach((group2) => {
            const item = document.createElement('div');
            item.className = 'item';
            item.textContent = group2;
            item.dataset.group1 = group1;
            item.dataset.group2 = group2;
            item.addEventListener('click', () => selectItem(group1, group2, item));
            details.appendChild(item);
        });

        treeEl.appendChild(details);
    });
}

function selectItem(group1, group2, el) {
    document.querySelectorAll('.item.active').forEach((n) => n.classList.remove('active'));
    if (el) el.classList.add('active');

    const entry = CATALOG[group1][group2];
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
    const meta = entry.meta || {};
    const metaBadges = Object.keys(meta).map((k) => `<span class="badge">${escapeHtml(k)}: ${escapeHtml(meta[k])}</span>`).join('');

    detailEl.innerHTML = `
        <h2>${escapeHtml(group1)} / ${escapeHtml(group2)}</h2>
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
    `;

    document.getElementById('copy-btn').addEventListener('click', () => {
        navigator.clipboard.writeText(example).then(() => {
            const btn = document.getElementById('copy-btn');
            btn.textContent = 'Copied!';
            setTimeout(() => { btn.textContent = 'Copy'; }, 1200);
        });
    });
}

searchEl.addEventListener('input', () => renderTree(searchEl.value));
renderTree('');
</script>
</body>
</html>
