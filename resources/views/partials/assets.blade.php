@once
<style>
    .pm {
        --pm-bg: #ffffff; --pm-soft: #f6f7f9; --pm-fg: #111827; --pm-muted: #6b7280;
        --pm-border: #e5e7eb; --pm-accent: #4338ca; --pm-accent-soft: #eef2ff; --pm-accent-fg: #ffffff;
        --pm-danger: #b91c1c; --pm-danger-soft: #fef2f2; --pm-ok: #15803d; --pm-ok-soft: #f0fdf4;
        --pm-radius: 10px;
        color: var(--pm-fg); font: 14px/1.5 ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        max-width: 1120px; margin: 0 auto; padding: 24px 16px 48px; box-sizing: border-box;
    }
    @media (prefers-color-scheme: dark) {
        .pm {
            --pm-bg: #171a21; --pm-soft: #1f232c; --pm-fg: #e5e7eb; --pm-muted: #9ca3af;
            --pm-border: #2e3440; --pm-accent: #818cf8; --pm-accent-soft: #23263a; --pm-accent-fg: #0b0d12;
            --pm-danger: #f87171; --pm-danger-soft: #2a1717; --pm-ok: #4ade80; --pm-ok-soft: #13241a;
        }
    }
    .pm *, .pm *::before, .pm *::after { box-sizing: border-box; }
    .pm a { color: var(--pm-accent); text-decoration: none; }
    .pm h1, .pm h2 { margin: 0; line-height: 1.25; }
    .pm-title { font-size: 22px; font-weight: 650; }
    .pm-h2 { font-size: 15px; font-weight: 650; margin-bottom: 4px !important; }
    .pm-muted { color: var(--pm-muted); }
    .pm-small { font-size: 12px; }
    .pm-tabs { display: flex; gap: 4px; flex-wrap: wrap; border-bottom: 1px solid var(--pm-border); margin-bottom: 20px; align-items: center; }
    .pm-tab { padding: 8px 12px; color: var(--pm-muted) !important; border-bottom: 2px solid transparent; margin-bottom: -1px; font-weight: 550; }
    .pm-tab.is-active { color: var(--pm-fg) !important; border-bottom-color: var(--pm-accent); }
    .pm-scope { margin-left: auto; }
    .pm-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; margin-bottom: 16px; }
    .pm-card { background: var(--pm-bg); border: 1px solid var(--pm-border); border-radius: var(--pm-radius); padding: 16px; }
    .pm-stack > * + * { margin-top: 16px; }
    .pm-grid-2 { display: grid; gap: 12px 16px; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
    .pm-field { display: flex; flex-direction: column; gap: 4px; font-weight: 550; }
    .pm-field > span { font-size: 13px; }
    .pm-input { width: 100%; padding: 8px 10px; border: 1px solid var(--pm-border); border-radius: 8px; background: var(--pm-bg); color: var(--pm-fg); font: inherit; font-weight: 400; }
    .pm-input:focus { outline: 2px solid var(--pm-accent); outline-offset: -1px; }
    .pm-input:disabled { background: var(--pm-soft); color: var(--pm-muted); }
    .pm-toolbar { display: flex; gap: 8px; margin-bottom: 12px; max-width: 480px; }
    .pm-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: 1px solid var(--pm-border); background: var(--pm-bg); color: var(--pm-fg) !important; font: inherit; font-weight: 550; cursor: pointer; white-space: nowrap; }
    .pm-btn:hover { background: var(--pm-soft); }
    .pm-btn-primary { background: var(--pm-accent); border-color: var(--pm-accent); color: var(--pm-accent-fg) !important; }
    .pm-btn-primary:hover { background: var(--pm-accent); filter: brightness(1.08); }
    .pm-btn-danger { color: var(--pm-danger) !important; }
    .pm-btn-sm { padding: 4px 10px; font-size: 13px; }
    .pm-actions { display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap; }
    .pm-actions form { margin: 0; }
    .pm-form-actions { display: flex; gap: 8px; justify-content: flex-end; }
    .pm-table-wrap { padding: 0; overflow-x: auto; }
    .pm-table { width: 100%; border-collapse: collapse; }
    .pm-table th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: var(--pm-muted); font-weight: 600; background: var(--pm-soft); }
    .pm-table th, .pm-table td { padding: 10px 14px; border-bottom: 1px solid var(--pm-border); vertical-align: middle; }
    .pm-table tr:last-child td { border-bottom: 0; }
    .pm-num { text-align: right; font-variant-numeric: tabular-nums; }
    .pm-empty { padding: 28px; text-align: center; color: var(--pm-muted); }
    .pm-badge { display: inline-block; padding: 1px 8px; border-radius: 999px; font-size: 12px; font-weight: 550; background: var(--pm-soft); color: var(--pm-muted); border: 1px solid var(--pm-border); }
    .pm-badge-accent { background: var(--pm-accent-soft); color: var(--pm-accent); border-color: transparent; }
    .pm-badge-ok { background: var(--pm-ok-soft); color: var(--pm-ok); border-color: transparent; }
    .pm-badge-danger { background: var(--pm-danger-soft); color: var(--pm-danger); border-color: transparent; }
    .pm-code { font: 12px ui-monospace, SFMono-Regular, Consolas, monospace; color: var(--pm-muted); }
    .pm-alert { padding: 10px 14px; border-radius: 8px; margin-bottom: 16px; }
    .pm-alert ul { margin: 0; padding-left: 18px; }
    .pm-alert-ok { background: var(--pm-ok-soft); color: var(--pm-ok); }
    .pm-alert-error { background: var(--pm-danger-soft); color: var(--pm-danger); }
    .pm-alert-info { background: var(--pm-accent-soft); color: var(--pm-accent); }
    .pm-check { display: flex; gap: 8px; align-items: flex-start; padding: 4px 0; font-weight: 400; cursor: pointer; }
    .pm-check input { margin-top: 3px; accent-color: var(--pm-accent); }
    .pm-modules { display: grid; gap: 12px; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); }
    .pm-module { margin: 0; min-width: 0; }
    .pm-module-head { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 0 0 8px; margin-bottom: 6px; border-bottom: 1px solid var(--pm-border); }
    .pm-module legend { float: left; width: 100%; padding: 0; }
    .pm-module legend + * { clear: both; }
    .pm-radios { display: inline-flex; border: 1px solid var(--pm-border); border-radius: 8px; overflow: hidden; }
    .pm-radios label { padding: 3px 10px; font-size: 12px; cursor: pointer; border-left: 1px solid var(--pm-border); }
    .pm-radios label:first-child { border-left: 0; }
    .pm-radios input { position: absolute; opacity: 0; pointer-events: none; }
    .pm-radios label:has(input:checked) { background: var(--pm-accent-soft); color: var(--pm-accent); font-weight: 600; }
    .pm-radios label:has(input[value=deny]:checked) { background: var(--pm-danger-soft); color: var(--pm-danger); }
    .pm-radios label:has(input:disabled) { cursor: not-allowed; opacity: .55; }
    .pm-pager { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; gap: 8px; }
    @media (max-width: 640px) { .pm-hide-sm { display: none; } .pm-table th, .pm-table td { padding: 8px 10px; } }
</style>
<script>
    document.addEventListener('change', function (event) {
        if (!event.target.matches('[data-pm-toggle-all]')) return;
        event.target.closest('[data-pm-module]')
            .querySelectorAll('input[name="permissions[]"]:not(:disabled)')
            .forEach(function (box) { box.checked = event.target.checked; });
    });
    document.addEventListener('submit', function (event) {
        var message = event.target.getAttribute('data-pm-confirm');
        if (message && !window.confirm(message)) event.preventDefault();
    });
</script>
@endonce
