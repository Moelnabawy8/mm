@push('css-page')
<style>
    /* CRM Reports. Colors are tokens so the dark layout swaps them in one place. */
    .crm-reports {
        --crm-surface: #ffffff;
        --crm-plane: #f6f7f9;
        --crm-ink: #1f2430;
        --crm-ink-2: #525866;
        --crm-muted: #8a8f99;
        --crm-line: #e7e8ec;
        --crm-accent: #2a78d6;
        --crm-accent-soft: rgba(42, 120, 214, .10);
        --crm-good: #0c8a0c;
        --crm-bad: #d03b3b;
        --crm-bar: #2a78d6;
        --crm-bar-track: #eef1f5;
    }
    .crm-reports.crm-dark {
        --crm-surface: #1f2227;
        --crm-plane: #17191d;
        --crm-ink: #f3f4f6;
        --crm-ink-2: #c3c6cc;
        --crm-muted: #8f949c;
        --crm-line: #2f333a;
        --crm-accent: #3987e5;
        --crm-accent-soft: rgba(57, 135, 229, .16);
        --crm-good: #2fb52f;
        --crm-bad: #e66767;
        --crm-bar: #3987e5;
        --crm-bar-track: #2a2e35;
    }

    .crm-cat-nav { display: flex; gap: .5rem; flex-wrap: wrap; }
    .crm-cat-link {
        display: inline-flex; align-items: center; gap: .45rem; padding: .55rem 1rem;
        border-radius: 10px; border: 1px solid var(--crm-line); background: var(--crm-surface);
        color: var(--crm-ink-2); font-weight: 600; transition: background-color .15s ease, color .15s ease, border-color .15s ease;
    }
    .crm-cat-link:hover { color: var(--crm-accent); border-color: var(--crm-accent); }
    .crm-cat-link.active { background: var(--crm-accent); border-color: var(--crm-accent); color: #fff; }
    .crm-cat-link i { font-size: 1.1rem; }

    /* Filter bar: one row of controls above everything it scopes. */
    .crm-filters .form-label { font-size: .75rem; color: var(--crm-muted); margin-bottom: .25rem; font-weight: 600; }
    .crm-filters .form-control, .crm-filters .form-select { font-size: .85rem; }
    /* RTL: the theme keeps the select arrow on the right, under the text. */
    .crm-reports[dir="rtl"] .form-select {
        padding-left: 2.25rem; padding-right: .75rem; background-position: left .75rem center;
    }
    .crm-filter-summary { font-size: .8rem; color: var(--crm-ink-2); }
    .crm-filter-summary .badge { font-weight: 500; }

    /* KPI tiles */
    .crm-kpi { height: 100%; }
    .crm-kpi .card-body { padding: 1rem 1.1rem; }
    .crm-kpi-label { font-size: .78rem; color: var(--crm-muted); font-weight: 600; display: flex; align-items: center; gap: .35rem; }
    .crm-kpi-value { font-size: 1.65rem; font-weight: 700; color: var(--crm-ink); line-height: 1.2; margin-top: .35rem; }
    .crm-kpi-delta { font-size: .75rem; font-weight: 600; margin-top: .2rem; }
    .crm-kpi-delta.up { color: var(--crm-good); }
    .crm-kpi-delta.down { color: var(--crm-bad); }
    .crm-kpi-delta.flat { color: var(--crm-muted); }

    /* Report tabs */
    .crm-tabs { display: flex; gap: .25rem; overflow-x: auto; border-bottom: 1px solid var(--crm-line); margin-bottom: 1rem; scrollbar-width: thin; }
    .crm-tab {
        white-space: nowrap; padding: .65rem 1rem; border: 0; background: none; color: var(--crm-ink-2);
        font-weight: 600; font-size: .875rem; border-bottom: 2px solid transparent; margin-bottom: -1px;
        display: inline-flex; align-items: center; gap: .4rem; cursor: pointer;
    }
    .crm-tab .crm-tab-num {
        display: inline-flex; align-items: center; justify-content: center; width: 20px; height: 20px;
        border-radius: 50%; background: var(--crm-bar-track); font-size: .7rem; color: var(--crm-ink-2);
    }
    .crm-tab:hover { color: var(--crm-accent); }
    .crm-tab.active { color: var(--crm-accent); border-bottom-color: var(--crm-accent); }
    .crm-tab.active .crm-tab-num { background: var(--crm-accent); color: #fff; }
    .crm-tab:focus-visible { outline: 2px solid var(--crm-accent); outline-offset: 2px; }

    .crm-section { position: relative; min-height: 220px; transition: opacity .15s ease; }
    .crm-section.is-loading { opacity: .45; pointer-events: none; }
    .crm-loader { position: absolute; inset: 0; display: none; align-items: flex-start; justify-content: center; padding-top: 80px; z-index: 2; }
    .crm-section.is-loading .crm-loader { display: flex; }

    .crm-section-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
    .crm-section-head h4 { margin: 0; font-size: 1.1rem; }
    .crm-definition { font-size: .8rem; color: var(--crm-muted); margin-top: .25rem; max-width: 760px; }

    .crm-card-title { font-size: .9rem; font-weight: 700; margin: 0; color: var(--crm-ink); }
    .crm-card-sub { font-size: .75rem; color: var(--crm-muted); }

    /* Tables */
    .crm-table { font-size: .84rem; margin: 0; }
    .crm-table th { font-size: .74rem; text-transform: none; color: var(--crm-muted); font-weight: 700; white-space: nowrap; background: var(--crm-plane); }
    .crm-table td, .crm-table th { padding: .55rem .75rem; vertical-align: middle; }
    .crm-table .num { text-align: end; font-variant-numeric: tabular-nums; white-space: nowrap; }
    .crm-table tfoot td { font-weight: 700; background: var(--crm-plane); }
    .crm-table th[data-sort] { cursor: pointer; user-select: none; }
    .crm-table th[data-sort]::after { content: '\2195'; opacity: .35; margin-inline-start: .3rem; font-size: .7rem; }
    .crm-table th[data-dir="asc"]::after { content: '\2191'; opacity: .9; }
    .crm-table th[data-dir="desc"]::after { content: '\2193'; opacity: .9; }
    .crm-table-wrap { max-height: 560px; overflow: auto; }
    .crm-table-wrap thead th { position: sticky; top: 0; z-index: 1; }

    /* In-cell rate bar: value text + thin track */
    .crm-rate { display: flex; align-items: center; gap: .5rem; justify-content: flex-end; min-width: 120px; }
    .crm-rate-track { display: block; flex: 0 0 64px; height: 6px; border-radius: 4px; background: var(--crm-bar-track); overflow: hidden; }
    .crm-rate-fill { display: block; height: 100%; border-radius: 4px; background: var(--crm-bar); }

    .crm-delta { font-size: .72rem; font-weight: 600; margin-inline-start: .3rem; direction: ltr; unicode-bidi: isolate; display: inline-block; }
    .crm-delta.up { color: var(--crm-good); }
    .crm-delta.down { color: var(--crm-bad); }

    .crm-empty { text-align: center; padding: 2.5rem 1rem; color: var(--crm-muted); }
    .crm-empty i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .6; }

    .crm-notice { font-size: .8rem; border-radius: 8px; padding: .6rem .85rem; background: var(--crm-accent-soft); color: var(--crm-ink-2); }

    .crm-board-row { display: flex; align-items: center; gap: .6rem; padding: .45rem 0; border-bottom: 1px solid var(--crm-line); }
    .crm-board-row:last-child { border-bottom: 0; }
    .crm-rank { width: 24px; height: 24px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center;
        font-size: .72rem; font-weight: 700; background: var(--crm-bar-track); color: var(--crm-ink-2); flex: 0 0 24px; }
    .crm-board-row:first-child .crm-rank { background: var(--crm-accent); color: #fff; }
    .crm-board-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .crm-board-val { font-weight: 700; font-variant-numeric: tabular-nums; }

    .crm-legend { display: flex; flex-wrap: wrap; gap: .4rem 1rem; font-size: .75rem; color: var(--crm-ink-2); }
    .crm-legend span { display: inline-flex; align-items: center; gap: .35rem; }
    .crm-legend i { width: 10px; height: 10px; border-radius: 3px; display: inline-block; }

    /* Charts draw left-to-right on Arabic pages too, so each name sits where its bar starts. */
    .crm-chart { min-height: 120px; direction: ltr; }

    @media (prefers-reduced-motion: reduce) {
        .crm-section, .crm-cat-link { transition: none; }
    }

    @media print {
        .dash-sidebar, .dash-header, .page-header, .crm-filters, .crm-tabs, .crm-cat-nav, .crm-kpis,
        .crm-no-print, .footer, .dash-footer, .pagination { display: none !important; }
        .dash-container { margin: 0 !important; padding: 0 !important; }
        .crm-print-head { display: block !important; }
        .crm-table-wrap { max-height: none; overflow: visible; }
        .card { break-inside: avoid; box-shadow: none !important; border: 1px solid #ddd !important; }
    }
    .crm-print-head { display: none; margin-bottom: 1rem; }
</style>
@endpush
