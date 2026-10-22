@push('scripts')
<script>
/*
 * CRM Reports front end.
 *
 * Charts are declared by the server as <div class="crm-chart" data-spec="{...}"> and drawn
 * here, so the palette and the chart conventions live in one place:
 *  - categorical hues in a fixed order (never by rank), validated for colour-vision deficiency
 *  - a 5-step ordinal blue ramp for ordered stages (validated: monotone, visible gaps, clears the surface)
 *  - status colours only for good/bad states
 *  - hairline solid grid, thin bars with 4px rounded ends, 2px surface gap between stacked fills
 *  - every chart sits next to a table carrying the same numbers
 */
(function () {
    var root = document.querySelector('.crm-reports');
    if (!root) { return; }
    var dark = root.classList.contains('crm-dark');
    var rtl = root.getAttribute('dir') === 'rtl';

    var P = dark ? {
        cat: ['#3987e5', '#d95926', '#199e70', '#c98500', '#d55181', '#008300', '#9085e9', '#e66767'],
        ramp: ['#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf'],
        status: { good: '#0ca30c', warning: '#fab219', serious: '#ec835a', critical: '#d03b3b', neutral: '#6b6f76' },
        heat: ['#1f2a3a', '#184f95', '#2a78d6', '#6da7ec', '#b7d3f6'],
        surface: '#1f2227', grid: '#2f333a', axis: '#8f949c', ink: '#f3f4f6'
    } : {
        cat: ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'],
        ramp: ['#104281', '#1c5cab', '#2a78d6', '#5598e7', '#86b6ef'],
        status: { good: '#0ca30c', warning: '#fab219', serious: '#ec835a', critical: '#d03b3b', neutral: '#a3a6ad' },
        heat: ['#eef4fc', '#b7d3f6', '#6da7ec', '#2a78d6', '#104281'],
        surface: '#ffffff', grid: '#e7e8ec', axis: '#8a8f99', ink: '#1f2430'
    };

    function color(token, i) {
        if (!token) { return P.cat[i % P.cat.length]; }
        if (token.indexOf('status.') === 0) { return P.status[token.slice(7)] || P.status.neutral; }
        if (token.indexOf('cat') === 0) { return P.cat[(parseInt(token.slice(3), 10) - 1) % P.cat.length]; }
        if (token.indexOf('ramp.') === 0) { return P.ramp[Math.min(parseInt(token.slice(5), 10), P.ramp.length - 1)]; }
        return token;
    }

    function fmt(v, suffix) {
        if (v === null || v === undefined || isNaN(v)) { return '–'; }
        var n = Number(v);
        var s = Math.abs(n) >= 1000 ? n.toLocaleString() : (Math.round(n * 10) / 10).toString();
        return s + (suffix || '');
    }

    function base(spec) {
        return {
            chart: {
                fontFamily: 'inherit', toolbar: { show: false }, parentHeightOffset: 0,
                background: 'transparent', animations: { enabled: !window.matchMedia('(prefers-reduced-motion: reduce)').matches, speed: 250 },
                foreColor: P.axis
            },
            theme: { mode: dark ? 'dark' : 'light' },
            grid: { borderColor: P.grid, strokeDashArray: 0, xaxis: { lines: { show: !!spec.horizontal } }, yaxis: { lines: { show: !spec.horizontal } }, padding: { left: 8, right: 8 } },
            dataLabels: { enabled: false },
            legend: { show: (spec.series || []).length > 1, position: 'top', horizontalAlign: rtl ? 'right' : 'left', fontSize: '12px', markers: { radius: 3 }, labels: { colors: P.ink } },
            tooltip: { theme: dark ? 'dark' : 'light', y: { formatter: function (v) { return fmt(v, spec.suffix); } } },
            xaxis: { axisBorder: { color: P.grid }, axisTicks: { color: P.grid } },
            states: { active: { filter: { type: 'none' } } }
        };
    }

    function build(spec) {
        var o = base(spec);
        var series = spec.series || [];
        var cats = spec.categories || [];

        if (spec.type === 'heatmap') {
            var max = 0;
            series.forEach(function (s) { s.data.forEach(function (v) { max = Math.max(max, v); }); });
            var step = Math.max(1, Math.ceil(max / 4));
            o.chart.type = 'heatmap';
            o.chart.height = spec.height || 300;
            o.series = series.map(function (s) { return { name: s.name, data: s.data.map(function (v, i) { return { x: cats[i], y: v }; }) }; });
            o.plotOptions = { heatmap: { radius: 3, enableShades: false, colorScale: { ranges: [
                { from: 0, to: 0, color: P.heat[0], name: '0' },
                { from: 1, to: step, color: P.heat[1], name: '1–' + step },
                { from: step + 1, to: step * 2, color: P.heat[2], name: (step + 1) + '–' + step * 2 },
                { from: step * 2 + 1, to: step * 3, color: P.heat[3], name: (step * 2 + 1) + '–' + step * 3 },
                { from: step * 3 + 1, to: 1e9, color: P.heat[4], name: '> ' + step * 3 }
            ] } } };
            o.stroke = { width: 2, colors: [P.surface] };
            o.legend.show = true;
            o.grid.yaxis.lines.show = false;
            return o;
        }

        var horizontal = !!spec.horizontal;
        o.series = series.map(function (s) { return { name: s.name, data: s.data }; });
        o.colors = series.map(function (s, i) { return color(s.color, i); });
        o.xaxis.categories = cats;
        o.chart.type = spec.type === 'line' ? 'line' : (spec.type === 'area' ? 'area' : 'bar');
        o.chart.stacked = !!spec.stacked;
        if (spec.percent) { o.chart.stackType = '100%'; }

        var rows = cats.length || 1;
        o.chart.height = spec.height || (horizontal ? Math.max(180, rows * (spec.stacked ? 34 : 30) + 70) : 300);

        if (o.chart.type === 'bar') {
            o.plotOptions = { bar: {
                horizontal: horizontal, borderRadius: 4, borderRadiusApplication: 'end', borderRadiusWhenStacked: 'last',
                barHeight: horizontal ? (rows > 12 ? '70%' : '58%') : undefined,
                columnWidth: horizontal ? undefined : (rows > 20 ? '80%' : '55%'),
                distributed: !!spec.distributed
            } };
            o.stroke = { show: true, width: 2, colors: [P.surface] };
            if (spec.distributed) {
                o.colors = cats.map(function (c, i) { return spec.ramp ? P.ramp[Math.min(i, P.ramp.length - 1)] : color(null, i); });
                o.legend.show = false;
            }
        } else {
            o.stroke = { width: 2, curve: 'smooth' };
            o.markers = { size: rows <= 31 ? 3 : 0, strokeColors: P.surface, strokeWidth: 2, hover: { size: 5 } };
            o.tooltip.shared = true;
            o.tooltip.intersect = false;
            if (o.chart.type === 'area') { o.fill = { type: 'solid', opacity: 0.12 }; }
        }

        if (spec.labels) {
            o.dataLabels = {
                enabled: true, textAnchor: horizontal ? 'start' : 'middle', offsetX: horizontal ? 16 : 0, offsetY: horizontal ? 0 : -18,
                style: { colors: [P.ink], fontSize: '11px', fontWeight: 600 },
                formatter: function (v) { return fmt(v, spec.suffix); }
            };
            if (o.plotOptions && o.plotOptions.bar) { o.plotOptions.bar.dataLabels = { position: 'top' }; }
            // Room for the value past the end of the longest bar.
            if (horizontal) { o.grid.padding.right = 40; } else { o.grid.padding.top = 16; }
        }

        // A reference line (team average) drawn as a solid hairline, labelled.
        if (spec.avg !== undefined && spec.avg !== null) {
            var line = { borderColor: P.ink, strokeDashArray: 0, opacity: 0.6,
                label: { text: (spec.avgLabel || '') + ' ' + fmt(spec.avg, spec.suffix), borderColor: 'transparent', orientation: 'horizontal', position: 'top', offsetY: -6,
                    style: { background: P.surface, color: P.ink, fontSize: '11px' } } };
            o.annotations = horizontal ? { xaxis: [Object.assign({ x: spec.avg }, line)] } : { yaxis: [Object.assign({ y: spec.avg }, line)] };
            o.grid.padding.top = 22;
        }

        var axisFmt = function (v) { return typeof v === 'number' ? fmt(v, spec.percent ? '%' : spec.suffix) : v; };
        if (horizontal) {
            o.xaxis.labels = { formatter: axisFmt };
            o.yaxis = { labels: { maxWidth: 180, style: { fontSize: '12px' } } };
        } else {
            o.yaxis = { labels: { formatter: function (v) { return fmt(v, spec.suffix); } }, min: 0, forceNiceScale: true };
            o.xaxis.labels = { rotate: -35, hideOverlappingLabels: true, trim: true };
            if (spec.datetime || /^\d{4}-\d{2}-\d{2}$/.test(cats[0] || '')) {
                // Day buckets read as "07-14"; month buckets (always the 1st) as "2026-07".
                var monthly = cats.every(function (c) { return /-01$/.test(c); }) && cats.length > 1;
                o.xaxis.labels = { rotate: 0, hideOverlappingLabels: true, formatter: function (v) {
                    return typeof v === 'string' && v.length === 10 ? (monthly ? v.slice(0, 7) : v.slice(5)) : v;
                } };
                o.xaxis.tickAmount = Math.min(rows, 12);
            }
        }
        return o;
    }

    var charts = [];
    function drawCharts(scope) {
        charts.forEach(function (c) { try { c.destroy(); } catch (e) {} });
        charts = [];
        if (typeof ApexCharts === 'undefined') { return; }
        scope.querySelectorAll('.crm-chart[data-spec]').forEach(function (el) {
            var spec;
            try { spec = JSON.parse(el.getAttribute('data-spec')); } catch (e) { return; }
            var empty = !(spec.series || []).some(function (s) { return (s.data || []).some(function (v) { return Number(v) > 0; }); });
            if (empty) {
                el.innerHTML = '<div class="crm-empty"><i class="ti ti-chart-bar-off"></i>' + (root.getAttribute('data-empty') || '') + '</div>';
                return;
            }
            var chart = new ApexCharts(el, build(spec));
            chart.render();
            charts.push(chart);
        });
    }

    // Click-to-sort on any table header carrying data-sort.
    function initSort(scope) {
        scope.querySelectorAll('.crm-table th[data-sort]').forEach(function (th) {
            th.setAttribute('tabindex', '0');
            var go = function () {
                var table = th.closest('table');
                var idx = Array.prototype.indexOf.call(th.parentNode.children, th);
                var dir = th.getAttribute('data-dir') === 'desc' ? 'asc' : 'desc';
                table.querySelectorAll('th[data-sort]').forEach(function (h) { h.removeAttribute('data-dir'); });
                th.setAttribute('data-dir', dir);
                var body = table.tBodies[0];
                var rows = Array.prototype.slice.call(body.rows);
                var num = th.getAttribute('data-sort') === 'num';
                rows.sort(function (a, b) {
                    var x = a.cells[idx], y = b.cells[idx];
                    var vx = x ? (x.getAttribute('data-v') || x.textContent.trim()) : '';
                    var vy = y ? (y.getAttribute('data-v') || y.textContent.trim()) : '';
                    var r = num ? (parseFloat(vx) || 0) - (parseFloat(vy) || 0) : vx.localeCompare(vy);
                    return dir === 'asc' ? r : -r;
                });
                rows.forEach(function (r) { body.appendChild(r); });
            };
            th.addEventListener('click', go);
            th.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
        });
    }

    // ---- tab loader -------------------------------------------------------
    var container = document.getElementById('crm-section');
    var tabs = document.querySelectorAll('.crm-tab');
    var baseQuery = root.getAttribute('data-query') || '';
    var sectionUrl = root.getAttribute('data-section-url');
    var extras = {};
    var current = root.getAttribute('data-active');
    var pending = null;

    function qs(obj) {
        var parts = [];
        Object.keys(obj).forEach(function (k) {
            var v = obj[k];
            if (v === null || v === undefined || v === '') { return; }
            parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(v));
        });
        return parts.join('&');
    }

    function load(key, extra) {
        current = key;
        extras[key] = extra || extras[key] || {};
        tabs.forEach(function (t) {
            var on = t.getAttribute('data-section') === key;
            t.classList.toggle('active', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        container.classList.add('is-loading');
        var q = [baseQuery, qs(extras[key])].filter(Boolean).join('&');
        var url = sectionUrl.replace('__SECTION__', key) + (q ? '?' + q : '');
        if (pending) { pending.abort(); }
        var xhr = pending = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.onload = function () {
            if (xhr !== pending) { return; }
            pending = null;
            container.classList.remove('is-loading');
            var body = container.querySelector('.crm-section-body');
            if (xhr.status === 200) {
                var data = JSON.parse(xhr.responseText);
                body.innerHTML = data.html;
                drawCharts(body);
                initSort(body);
            } else {
                body.innerHTML = '<div class="crm-empty"><i class="ti ti-alert-triangle"></i>' + (root.getAttribute('data-error') || 'Error') + '</div>';
            }
            try {
                var u = new URL(window.location.href);
                u.searchParams.set('tab', key);
                window.history.replaceState(null, '', u.toString());
            } catch (e) {}
        };
        xhr.onerror = function () { container.classList.remove('is-loading'); };
        xhr.send();
    }

    tabs.forEach(function (t) {
        t.addEventListener('click', function () { load(t.getAttribute('data-section')); });
    });

    // Pagination and section-level filters reload the current tab in place.
    container.addEventListener('click', function (e) {
        var a = e.target.closest('.pagination a');
        if (!a) { return; }
        e.preventDefault();
        var page = new URL(a.href).searchParams.get('page');
        var ex = Object.assign({}, extras[current] || {}, { page: page });
        load(current, ex);
        container.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    container.addEventListener('change', function (e) {
        var form = e.target.closest('form[data-section-filter]');
        if (!form) { return; }
        var ex = {};
        new FormData(form).forEach(function (v, k) { ex[k] = v; });
        load(current, ex);
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-crm-print]')) { e.preventDefault(); window.print(); }
    });
    window.addEventListener('beforeprint', function () { charts.forEach(function (c) { try { c.updateOptions({ chart: { animations: { enabled: false } } }, false, false); } catch (e) {} }); });

    // Custom date range shows its two date inputs only when chosen.
    var period = document.getElementById('crm-period');
    var custom = document.querySelectorAll('.crm-custom-range');
    function toggleCustom() { custom.forEach(function (el) { el.classList.toggle('d-none', period.value !== 'custom'); }); }
    if (period) { period.addEventListener('change', toggleCustom); toggleCustom(); }

    load(current);
})();
</script>
@endpush
