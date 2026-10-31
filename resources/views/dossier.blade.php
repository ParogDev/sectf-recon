<!DOCTYPE html>
{{--
    Phase 1 dossier: a standalone, print-ready document (File → Print → Save as PDF is the submission path).
    All styling is inline; no build step or fonts are needed; one small inline script powers click-to-copy numbers. Always light theme.
    Owned by the `ui` agent. Keep the data-test hooks: dossier-title, dossier-phones, dossier-callsheet, dossier-people, dossier-sources.
    Template strings live in lang/{en,fr}/dossier.php; the controller sets the locale before rendering.
--}}
@php
    $summary = $report['summary'];
    $target = $report['target'];
    $method = $report['methodology'];

    /** 2026-10-14T01:33:16+00:00 → "2026-10-14 01:33 UTC" */
    $dt = function (?string $value): ?string {
        if (! $value) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->utc()->format('Y-m-d H:i').' UTC';
        } catch (\Throwable) {
            return $value;
        }
    };

    /** Content dates are stored as dates; show them as-is when they are not parseable. */
    $d = function (?string $value): ?string {
        if (! $value) {
            return null;
        }
        try {
            return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    };

    /** 7.5 stays 7.5, 10.0 becomes 10. */
    $pts = fn ($value): string => rtrim(rtrim(number_format((float) $value, 1, '.', ''), '0'), '.') ?: '0';

    $confWord = fn ($c): ?string => $c === null ? null : __('dossier.confidence.'.($c >= 0.7 ? 'high' : ($c >= 0.4 ? 'medium' : 'low')));

    $pct = fn ($part, $whole): int => (float) $whole > 0 ? (int) round(min(100, max(0, (float) $part / (float) $whole * 100))) : 0;

    $bankedPct = $pct($summary['osint_points_banked'], $summary['osint_points_available']);
    $flagTotal = $summary['flags_found'] + $summary['flags_partial'] + $summary['flags_open'];

    /** Small tag after text that is still English in a French dossier (backend sets `untranslated`). */
    $enTag = fn (array $item): string => ! empty($item['untranslated']) && app()->getLocale() !== 'en'
        ? ' <span class="tag tag-en">'.e(__('dossier.tags.english_only')).'</span>'
        : '';

    /** "corporate" → localised kind word; unknown or null → null. */
    $kindWord = function (?string $kind): ?string {
        if (! $kind) {
            return null;
        }
        $key = 'dossier.kinds.'.$kind;
        $word = __($key);

        return $word === $key ? $kind : $word;
    };

    /** Backend cites as "flag 03-09", "phone +1…", "person Jane"; keep the id, translate the kind. */
    $citedBy = function (string $ref): string {
        [$kind, $id] = array_pad(explode(' ', $ref, 2), 2, '');
        $key = 'dossier.sources.cited_kind.'.$kind;

        return __($key) === $key ? $ref : __($key, ['id' => $id]);
    };

    $statusChip = fn (?string $status): string => $status
        ? '<span class="status status-'.e($status).'">'.e(__('dossier.status.'.$status)).'</span>'
        : '';

    $readiness = $report['readiness'] ?? ['ok' => true, 'gaps' => [], 'pretexts' => ['have' => count($report['pretexts']), 'need' => 5]];
    $coverage = $report['pretext_coverage'] ?? ['by_flag' => [], 'uncovered' => []];

    /** Every catalogue flag in catalogue order, keyed by id, for the coverage matrix. */
    $flagsById = [];
    foreach ($report['categories'] as $cat) {
        foreach ($cat['flags'] as $f) {
            $flagsById[$f['id']] = $f;
        }
    }
    $reachable = [];
    foreach ($flagsById as $id => $f) {
        if (! empty($coverage['by_flag'][$id])) {
            $reachable[] = ['flag' => $f, 'refs' => $coverage['by_flag'][$id]];
        }
    }
    foreach ($coverage['by_flag'] as $id => $refs) {
        if (! isset($flagsById[$id])) {
            $reachable[] = ['flag' => ['id' => $id, 'question' => null, 'osint_points' => null, 'status' => null], 'refs' => $refs];
        }
    }
    $foldAfter = 12;

    /** Rows of the "reachable by pretext" matrix; the chip style (solid = primary, dashed = pivot) carries the role. */
    $reachRows = function (array $rows) use ($pts, $statusChip): string {
        $html = '';
        foreach ($rows as $row) {
            $f = $row['flag'];
            $html .= '<tr>';
            $html .= '<td class="num nowrap"><a class="flag-link" href="#flag-'.e($f['id']).'">'.e($f['id']).'</a></td>';
            $html .= '<td>'.e($f['question'] ?? '').'</td>';
            $html .= '<td class="right num">'.($f['osint_points'] === null ? '' : e($pts($f['osint_points']))).'</td>';
            $html .= '<td>'.$statusChip($f['status'] ?? null).'</td>';
            $html .= '<td class="nowrap">';
            foreach ($row['refs'] as $r) {
                $role = $r['role'] === 'pivot' ? 'pivot' : 'primary';
                $html .= '<a class="ref'.($role === 'pivot' ? ' pivot' : '').'" href="#pretext-'.e($r['ref']).'" title="'.e(__('dossier.pretexts.role_'.$role)).'">'.e($r['ref']).'</a> ';
            }
            $html .= '</td></tr>';
        }

        return $html;
    };

    /** Rows of the uncovered list; `uncovered` carries catalogue points, the dossier shows OSINT points (half). */
    $uncoveredRows = function (array $rows) use ($pts, $statusChip): string {
        $html = '';
        foreach ($rows as $f) {
            $html .= '<tr>';
            $html .= '<td class="num nowrap"><a class="flag-link" href="#flag-'.e($f['id']).'">'.e($f['id']).'</a></td>';
            $html .= '<td>'.e($f['question'] ?? '').'</td>';
            $html .= '<td class="right num">'.($f['points'] === null ? '' : e($pts($f['points'] / 2))).'</td>';
            $html .= '<td>'.$statusChip($f['status'] ?? null).'</td>';
            $html .= '</tr>';
        }

        return $html;
    };

    $kindCounts = collect($report['phones'])->pluck('kind')->filter()->countBy();

    /** Gap code + numbers → one sentence in the document language. */
    $gapSentence = function (array $gap): string {
        $params = collect($gap)->except('code')->map(fn ($v) => is_array($v) ? implode(', ', $v) : $v)->all();
        $key = 'dossier.readiness.'.$gap['code'];

        return __($key) === $key ? $gap['code'] : __($key, $params);
    };
@endphp
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="noindex">
    <title>{{ __('dossier.title', ['target' => $target['name'], 'event' => $report['event']['name'], 'phase' => $report['event']['phase']]) }}</title>
    <style>
        @page { size: Letter; margin: 18mm; }

        :root {
            --ink: #111;
            --muted: #555;
            --faint: #888;
            --rule: #bbb;
            --rule-light: #e2e2e2;
            --panel: #f4f4f4;
            --link: #0b3d91;
            color-scheme: light;
        }

        * { box-sizing: border-box; }

        html { background: #fff; }

        body {
            margin: 0;
            padding: 24px 16px 48px;
            max-width: 900px;
            margin-inline: auto;
            color: var(--ink);
            background: #fff;
            font: 10.5pt/1.45 system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            overflow-wrap: anywhere;
        }

        a { color: var(--link); text-decoration: underline; text-decoration-thickness: 1px; text-underline-offset: 2px; }
        a.url { word-break: break-all; }
        a.cite { font-variant-numeric: tabular-nums; text-decoration: none; font-weight: 600; }
        a.cite:hover { text-decoration: underline; }

        h1, h2, h3, h4 { line-height: 1.2; margin: 0; break-after: avoid; }
        h1 { font-size: 22pt; font-weight: 700; letter-spacing: -0.01em; }
        h2 { font-size: 15pt; font-weight: 700; margin: 0 0 10px; padding-bottom: 5px; border-bottom: 2px solid var(--ink); }
        h2 .num { color: var(--muted); font-weight: 600; margin-right: .4em; }
        h3 { font-size: 12pt; font-weight: 700; margin: 18px 0 6px; }
        h4 { font-size: 10.5pt; font-weight: 700; margin: 10px 0 4px; }

        p { margin: 0 0 8px; }
        .muted { color: var(--muted); }
        .faint { color: var(--faint); }
        .small { font-size: 9pt; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 9.5pt; }
        .nowrap { white-space: nowrap; }
        .num { font-variant-numeric: tabular-nums; }
        .right { text-align: right; }
        .none { color: var(--muted); font-style: italic; margin: 4px 0 0; }

        section { margin-top: 28px; }
        section.page { break-before: page; }
        section.page:first-of-type { break-before: auto; }

        /* Title block */
        .eyebrow { font-size: 9pt; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); font-weight: 600; }
        .title-block { border-bottom: 3px solid var(--ink); padding-bottom: 12px; }
        .title-block .sub { font-size: 12pt; color: var(--muted); margin-top: 4px; }
        dl.facts { display: grid; grid-template-columns: max-content 1fr; gap: 3px 14px; margin: 12px 0 0; }
        dl.facts dt { color: var(--muted); font-weight: 600; font-size: 9.5pt; }
        dl.facts dd { margin: 0; }

        /* Toolbar (screen only) */
        .toolbar {
            display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px;
            background: var(--panel); border: 1px solid var(--rule-light); border-radius: 6px;
            padding: 8px 12px; margin-bottom: 18px; font-size: 9.5pt; color: var(--muted);
        }
        .toolbar button {
            font: inherit; font-weight: 600; color: #fff; background: var(--ink); border: 0; border-radius: 4px;
            padding: 5px 12px; cursor: pointer;
        }
        .toolbar button:focus-visible { outline: 2px solid var(--link); outline-offset: 2px; }

        /* Readiness (screen only): calm, non-blocking */
        .readiness {
            border: 1px solid var(--rule); border-left: 4px solid var(--muted); border-radius: 6px;
            padding: 8px 12px; margin: -8px 0 18px; font-size: 9.5pt; color: var(--ink); background: #fff;
        }
        .readiness b { display: block; font-size: 9pt; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); margin-bottom: 2px; }
        .readiness ul { margin: 0; padding-left: 1.2em; }
        .readiness li { padding: 1px 0; }

        /* Contents */
        nav.toc ol { margin: 6px 0 0; padding-left: 1.4em; columns: 2; column-gap: 24px; }
        nav.toc li { break-inside: avoid; padding: 1px 0; }

        /* Summary */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; margin: 0 0 14px; }
        .stat { border: 1px solid var(--rule); border-radius: 4px; padding: 8px 10px; break-inside: avoid; }
        .stat.wide { grid-column: span 2; }
        .stat .label { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .06em; color: var(--muted); font-weight: 600; }
        .stat .value { font-size: 18pt; font-weight: 700; line-height: 1.1; margin-top: 2px; }
        .stat .value small { font-size: 10pt; font-weight: 500; color: var(--muted); }
        .bar { position: relative; height: 16px; border: 1px solid var(--ink); border-radius: 3px; overflow: hidden; background: #fff; margin: 6px 0 2px; }
        .bar .fill { height: 100%; background: var(--ink); }
        .bar.thin { height: 9px; margin: 2px 0 0; }
        .bar-label { font-size: 9pt; color: var(--muted); }

        /* Tables */
        table { width: 100%; border-collapse: collapse; font-size: 9.5pt; }
        th, td { text-align: left; vertical-align: top; padding: 4px 6px; border-bottom: 1px solid var(--rule-light); }
        thead th { border-bottom: 1px solid var(--ink); font-size: 8.5pt; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); font-weight: 600; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        tfoot td { border-top: 1px solid var(--ink); border-bottom: 0; font-weight: 700; }
        .table-wrap { overflow-x: auto; }

        /* Status, greyscale-safe: the word carries the meaning */
        .status {
            display: inline-block; font-size: 8pt; font-weight: 700; text-transform: uppercase; letter-spacing: .05em;
            line-height: 1.5; padding: 0 5px; border: 1px solid var(--ink); border-radius: 3px; white-space: nowrap;
        }
        .status-found { background: var(--ink); color: #fff; }
        .status-partial { background: #fff; color: var(--ink); border-style: dashed; }
        .status-open { background: #fff; color: var(--faint); border-color: var(--rule); }
        .tag {
            display: inline-block; font-size: 8pt; font-weight: 600; line-height: 1.5; padding: 0 5px; margin-left: 4px;
            border: 1px solid var(--rule); border-radius: 3px; color: var(--muted); white-space: nowrap; vertical-align: middle;
        }
        .tag-en { border-style: dotted; font-weight: 500; text-transform: none; }
        .kind { font-size: 8.5pt; color: var(--muted); white-space: nowrap; }

        /* Flag list */
        table.flags th.col-id { width: 3.6em; }
        table.flags th.col-pts { width: 3.6em; }
        table.flags th.col-status { width: 6em; }
        /* References (A-1, D-12, P3): chips that link to the call sheet or the pretext */
        .ref {
            display: inline-block; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 9.5pt; font-weight: 700;
            line-height: 1.5; padding: 0 5px; border: 1.5px solid var(--ink); border-radius: 3px; color: var(--ink);
            text-decoration: none; white-space: nowrap; vertical-align: middle;
        }
        a.ref:hover { background: var(--ink); color: #fff; }
        .card h3 .ref { font-size: 11pt; margin-right: 4px; }
        table.callsheet th.col-ref, table.directory th.col-ref { width: 4em; }
        table.callsheet th.col-num { width: 9em; }
        table.callsheet th.col-ext { width: 3em; }
        table.callsheet th.col-pretexts { width: 11em; }
        table.callsheet th { white-space: nowrap; }
        table.callsheet td { padding: 5px 6px; }
        table.callsheet td .mono { font-size: 10.5pt; }
        table.callsheet tr.group th {
            background: var(--panel); color: var(--ink); font-size: 10pt; text-transform: none; letter-spacing: 0;
            border-bottom: 1px solid var(--ink); padding: 8px 6px 4px;
        }
        table.callsheet tr.group { break-after: avoid; }
        /* Directories: fixed widths so the Notes column cannot squeeze names and headers */
        table.directory th.col-num { width: 7.5em; }
        table.directory th.col-ext { width: 2.5em; }
        table.directory th.col-label { width: 8em; }
        table.directory th.col-dept { width: 7.5em; }
        table.directory th.col-person { width: 5.5em; }
        table.directory th.col-ring { width: 4.5em; }
        table.directory th.col-cite { width: 3em; }
        table.directory th.col-cite, table.directory td.num, table.directory th.col-ext { white-space: nowrap; }
        table.directory th.col-name { width: 7.5em; }
        table.directory th.col-title { width: 7em; }
        table.directory th.col-tenure { width: 6em; }
        table.directory th.col-profile { width: 8em; }
        table.directory td.name { min-width: 7.5em; }
        table.flags tr.flag td { padding-top: 6px; }
        table.flags tr.flag.has-findings { break-after: avoid; }
        table.flags tr.flag.has-findings td { border-bottom: 0; }
        table.flags tr.findings { break-inside: auto; } /* each .finding avoids breaks on its own; a tall group may span pages */
        table.flags tr.findings td { padding: 0 6px 8px 6px; }
        table.flags .flag-notes { color: var(--muted); font-size: 9pt; margin-top: 2px; }
        .category-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; margin: 22px 0 8px; break-after: avoid; }
        .category-head h3 { margin: 0; }
        .category-head .score { color: var(--muted); font-size: 9.5pt; }
        .category-head .bar { width: 120px; height: 9px; margin: 0; }

        /* Findings */
        .finding { border-left: 3px solid var(--rule); padding: 4px 0 4px 10px; margin: 8px 0 0 0; break-inside: avoid; }
        .finding .claim { font-weight: 600; margin: 0; }
        .finding .detail { margin: 3px 0 0; white-space: pre-wrap; }
        .finding .meta { margin: 4px 0 0; font-size: 9pt; color: var(--muted); }
        .finding .meta b { color: var(--ink); font-weight: 600; }
        ol.sources { margin: 5px 0 0; padding: 0; list-style: none; font-size: 9pt; }
        ol.sources li { padding: 3px 0 3px 2.4em; position: relative; break-inside: avoid; }
        ol.sources li .n { position: absolute; left: 0; top: 3px; }
        ol.sources .src-meta { color: var(--muted); }
        ol.sources blockquote { margin: 2px 0 0; padding-left: 8px; border-left: 2px solid var(--rule-light); color: var(--muted); font-style: italic; }

        /* Pretexts */
        .card { border: 1px solid var(--rule); border-radius: 4px; padding: 10px 12px; margin: 0 0 12px; break-inside: avoid; }
        .card h3 { margin: 0 0 4px; }
        .card .meta { font-size: 9pt; color: var(--muted); margin-bottom: 6px; }
        .card dl { display: grid; grid-template-columns: max-content 1fr; gap: 4px 12px; margin: 6px 0 0; }
        .card dt { color: var(--muted); font-weight: 600; font-size: 9pt; }
        .card dd { margin: 0; }
        .script { white-space: pre-wrap; background: var(--panel); border: 1px solid var(--rule-light); border-radius: 3px; padding: 8px 10px; margin: 0; font-size: 10pt; }
        ul.plain { margin: 0; padding-left: 1.2em; }
        ul.plain li { padding: 1px 0; }
        a.flag-link { text-decoration: none; color: inherit; font-weight: 600; font-variant-numeric: tabular-nums; }
        a.flag-link:hover { text-decoration: underline; }
        .pts { color: var(--muted); font-size: 9pt; white-space: nowrap; }
        .bridge { display: block; font-style: italic; color: var(--muted); margin: 1px 0 3px; }
        .section-note {
            display: inline-block; font-size: 9pt; font-weight: 600; color: var(--ink); border: 1px dashed var(--ink);
            border-radius: 3px; padding: 0 6px; margin: 0 0 10px; vertical-align: middle;
        }
        /* Coverage: role is carried by the chip style, not colour (print and greyscale safe) */
        table.coverage th.col-ref { width: 4em; }
        table.coverage th.col-n { width: 7em; }
        table.reach th.col-id { width: 3.6em; }
        table.reach th.col-pts { width: 3.6em; }
        table.reach th.col-status { width: 6em; }
        table.reach th.col-refs { width: 12em; }
        .ref.pivot { border-style: dashed; border-width: 1.5px; font-weight: 500; }
        .ref.pivot::after { content: "\2192"; margin-left: 3px; font-weight: 400; }
        .role-key { font-size: 9pt; color: var(--muted); margin: 0 0 6px; }
        details.fold > summary { cursor: pointer; font-size: 9pt; font-weight: 600; color: var(--link); margin: 6px 0 2px; list-style: none; }
        details.fold > summary::before { content: "+ "; }
        details.fold[open] > summary::before { content: "\2212 "; }
        details.fold > summary .less { display: none; }
        details.fold[open] > summary .more { display: none; }
        details.fold[open] > summary .less { display: inline; }

        /* Appendix */
        ol.appendix { margin: 0; padding: 0; list-style: none; }
        ol.appendix li { position: relative; padding: 6px 0 6px 3em; border-bottom: 1px solid var(--rule-light); break-inside: avoid; }
        ol.appendix li .n { position: absolute; left: 0; top: 6px; font-weight: 700; font-variant-numeric: tabular-nums; }
        ol.appendix .src-meta { font-size: 9pt; color: var(--muted); margin-top: 1px; }
        ol.appendix li:target { background: #fff8d6; }

        footer { margin-top: 36px; padding-top: 8px; border-top: 1px solid var(--rule); font-size: 9pt; color: var(--muted); }

        /* Click-to-copy numbers (screen only; they print as plain text) */
        .copy { cursor: copy; user-select: all; -webkit-user-select: all; border-radius: 3px; padding: 0 2px; margin: 0 -2px; }
        .copy:hover, .copy:focus-visible { background: #fff8d6; outline: 1px solid var(--rule); }
        .copy.copied { background: #d9f2d9; outline: 1px solid #2e7d32; }
        .copy.copied::after { content: "\2713 " attr(data-done); font: 600 8pt system-ui, sans-serif; color: #2e7d32; margin-left: 6px; }

        @media (max-width: 640px) {
            body { padding: 14px 12px 32px; font-size: 10pt; }
            h1 { font-size: 18pt; }
            nav.toc ol { columns: 1; }
            .stat.wide { grid-column: 1 / -1; }
            dl.facts, .card dl { grid-template-columns: 1fr; gap: 1px 0; }
            dl.facts dd, .card dd { margin-bottom: 6px; }
            .category-head .bar { width: 80px; }
        }

        @media print {
            body { padding: 0; max-width: none; font-size: 10pt; }
            .toolbar, .readiness { display: none; }
            /* Folded lists print in full: the script opens them on beforeprint; this covers engines without that event. */
            details.fold > summary { display: none; }
            details.fold::details-content { content-visibility: visible; display: block; }
            .copy:hover, .copy:focus-visible, .copy.copied { background: transparent; outline: 0; }
            .copy.copied::after { content: none; }
            a { color: inherit; }
            a.url, a.cite { color: var(--link); }
            a.ref { color: var(--ink); }
            .table-wrap { overflow: visible; }
            ol.appendix li:target { background: transparent; }
            section { margin-top: 20px; }
        }
    </style>
</head>
<body>

<div class="toolbar" role="note">
    <button type="button" onclick="window.print()">{{ __('dossier.toolbar.print') }}</button>
    <button type="button" id="copy-numbers" data-done="{{ __('dossier.copy.done') }}">{{ __('dossier.copy.all') }}</button>
    <span>{{ __('dossier.toolbar.hint') }}</span>
</div>
@if (! empty($readiness['gaps']))
    <div class="readiness" role="note" data-test="dossier-readiness">
        <b>{{ __('dossier.readiness.heading') }}</b>
        <ul>
            @foreach ($readiness['gaps'] as $gap)
                <li>{{ $gapSentence($gap) }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- 1. Title block --}}
<header class="title-block">
    <div class="eyebrow">{{ __('dossier.eyebrow', ['event' => $report['event']['name'], 'phase' => $report['event']['phase']]) }}</div>
    <h1 data-test="dossier-title">{{ $target['name'] }}</h1>
    <div class="sub">{{ __('dossier.subtitle', ['event' => $report['event']['name'], 'phase' => $report['event']['phase']]) }}</div>
    <dl class="facts">
        <dt>{{ __('dossier.facts.prepared_by') }}</dt>
        <dd>{{ $target['owner'] ?? '—' }}@if (count($method['contributors'] ?? []) > 1) <span class="muted">({{ __('dossier.facts.contributors', ['names' => implode(', ', $method['contributors'])]) }})</span>@endif</dd>
        <dt>{{ __('dossier.facts.generated') }}</dt>
        <dd>{{ $dt($report['generated_at']) }}</dd>
        @if (! empty($target['url']))
            <dt>{{ __('dossier.facts.target_website') }}</dt>
            <dd><a class="url" href="{{ $target['url'] }}" target="_blank" rel="noreferrer">{{ $target['url'] }}</a></dd>
        @endif
        <dt>{{ __('dossier.facts.domains') }}</dt>
        <dd>@if (! empty($target['domains'])){{ implode(', ', $target['domains']) }}@else <span class="muted">{{ __('dossier.none_recorded') }}</span>@endif</dd>
        @if (! empty($target['registry_id']))
            <dt>{{ __('dossier.facts.registry_id') }}</dt>
            <dd>{{ $target['registry_id'] }}</dd>
        @endif
    </dl>
</header>

<nav class="toc" aria-label="{{ __('dossier.toc.heading') }}">
    <h3>{{ __('dossier.toc.heading') }}</h3>
    <ol>
        <li><a href="#summary">{{ __('dossier.sections.summary') }}</a></li>
        <li><a href="#profile">{{ __('dossier.sections.profile') }}</a></li>
        <li><a href="#flags">{{ __('dossier.sections.flags') }}</a></li>
        <li><a href="#phones">{{ __('dossier.sections.phones') }}</a></li>
        <li><a href="#people">{{ __('dossier.sections.people') }}</a></li>
        <li><a href="#pretexts">{{ __('dossier.sections.pretexts') }}</a></li>
        <li><a href="#methodology">{{ __('dossier.sections.methodology') }}</a></li>
        <li><a href="#sources">{{ __('dossier.sections.sources') }}</a></li>
    </ol>
</nav>

{{-- 2. Executive summary --}}
<section id="summary">
    <h2><span class="num">1.</span>{{ __('dossier.sections.summary') }}</h2>

    <div class="stats">
        <div class="stat wide">
            <div class="label">{{ __('dossier.summary.banked') }}</div>
            <div class="value num">{{ $pts($summary['osint_points_banked']) }} <small>{{ __('dossier.summary.of_available', ['n' => $pts($summary['osint_points_available'])]) }}</small></div>
            <div class="bar" role="img" aria-label="{{ __('dossier.summary.banked_aria', ['pct' => $bankedPct]) }}"><div class="fill" style="width: {{ $bankedPct }}%"></div></div>
            <div class="bar-label">{{ __('dossier.summary.banked_bar', ['pct' => $bankedPct]) }}</div>
        </div>
        <div class="stat">
            <div class="label">{{ __('dossier.summary.found') }}</div>
            <div class="value num">{{ $summary['flags_found'] }} <small>{{ __('dossier.summary.of_flags', ['n' => $flagTotal]) }}</small></div>
        </div>
        <div class="stat">
            <div class="label">{{ __('dossier.summary.partial') }}</div>
            <div class="value num">{{ $summary['flags_partial'] }}</div>
        </div>
        <div class="stat">
            <div class="label">{{ __('dossier.summary.open') }}</div>
            <div class="value num">{{ $summary['flags_open'] }}</div>
        </div>
    </div>

    <h3>{{ __('dossier.summary.by_category') }}</h3>
    @if (empty($summary['by_category']))
        <p class="none">{{ __('dossier.none_recorded') }}</p>
    @else
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>{{ __('dossier.summary.col_category') }}</th>
                        <th class="right">{{ __('dossier.summary.col_found_flags') }}</th>
                        <th class="right">{{ __('dossier.summary.col_banked') }}</th>
                        <th class="right">{{ __('dossier.summary.col_available') }}</th>
                        <th style="width: 30%;">{{ __('dossier.summary.col_progress') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['by_category'] as $row)
                        <tr>
                            <td>{{ $row['category'] }}</td>
                            <td class="right num">{{ $row['found'] }} / {{ $row['total'] }}</td>
                            <td class="right num">{{ $pts($row['banked']) }}</td>
                            <td class="right num">{{ $pts($row['available']) }}</td>
                            <td>
                                <div class="bar thin" role="img" aria-label="{{ $pct($row['banked'], $row['available']) }}%"><div class="fill" style="width: {{ $pct($row['banked'], $row['available']) }}%"></div></div>
                                <span class="small muted num">{{ $pct($row['banked'], $row['available']) }}%</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>{{ __('dossier.summary.total') }}</td>
                        <td class="right num">{{ $summary['flags_found'] }} / {{ $flagTotal }}</td>
                        <td class="right num">{{ $pts($summary['osint_points_banked']) }}</td>
                        <td class="right num">{{ $pts($summary['osint_points_available']) }}</td>
                        <td class="num">{{ $bankedPct }}%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <h3>{{ __('dossier.summary.contains') }}</h3>
    <div class="table-wrap">
        <table>
            <tbody>
                <tr><td>{{ __('dossier.summary.row_findings') }}</td><td class="right num">{{ $summary['findings'] }}</td><td>{{ __('dossier.summary.row_findings_desc') }}</td></tr>
                <tr><td>{{ __('dossier.summary.row_sources') }}</td><td class="right num">{{ $summary['sources'] }}</td><td>{{ __('dossier.summary.row_sources_desc') }}</td></tr>
                <tr><td>{{ __('dossier.summary.row_phones') }}</td><td class="right num">{{ $summary['phones'] }}</td><td>{{ __('dossier.summary.row_phones_desc', ['count' => $summary['phones_ring_verified']]) }}</td></tr>
                <tr><td>{{ __('dossier.summary.row_people') }}</td><td class="right num">{{ $summary['people'] }}</td><td>{{ __('dossier.summary.row_people_desc') }}</td></tr>
                <tr><td>{{ __('dossier.summary.row_pretexts') }}</td><td class="right num">{{ $summary['pretexts'] }}</td><td>{{ __('dossier.summary.row_pretexts_desc') }}</td></tr>
                <tr><td>{{ __('dossier.summary.row_searches') }}</td><td class="right num">{{ $summary['searches'] }}</td><td>{{ __('dossier.summary.row_searches_desc') }}</td></tr>
            </tbody>
        </table>
    </div>
</section>

{{-- 3. Target profile --}}
<section id="profile">
    <h2><span class="num">2.</span>{{ __('dossier.sections.profile') }}</h2>
    <h4>{{ __('dossier.profile.notes') }}</h4>
    @if (! empty($target['notes']))
        <p style="white-space: pre-wrap;">{{ $target['notes'] }}{!! $enTag($target) !!}</p>
    @else
        <p class="none">{{ __('dossier.none_recorded') }}</p>
    @endif

    @if (! empty($report['general_findings']))
        <h4>{{ __('dossier.profile.general') }}</h4>
        @foreach ($report['general_findings'] as $finding)
            @include('dossier.finding', ['finding' => $finding, 'dt' => $dt, 'd' => $d, 'confWord' => $confWord])
        @endforeach
    @endif
</section>

{{-- 4. Flag findings --}}
<section id="flags" class="page">
    <h2><span class="num">3.</span>{{ __('dossier.sections.flags') }}</h2>
    <p class="muted small">
        {{ __('dossier.flags.intro_order') }}
        {!! __('dossier.flags.intro_points', ['pts' => '<b>'.e(__('dossier.flags.col_pts')).'</b>', 'osint' => '<b>'.e(__('dossier.flags.col_osint')).'</b>']) !!}
        {!! __('dossier.flags.intro_status', [
            'found' => '<span class="status status-found">'.e(__('dossier.status.found')).'</span>',
            'partial' => '<span class="status status-partial">'.e(__('dossier.status.partial')).'</span>',
            'open' => '<span class="status status-open">'.e(__('dossier.status.open')).'</span>',
        ]) !!}
        {{ __('dossier.flags.intro_confidence') }}
    </p>

    @forelse ($report['categories'] as $category)
        <div class="category-head">
            <h3>{{ $category['id'] }} · {{ $category['name'] }}</h3>
            <span class="score num">{{ __('dossier.flags.osint_pts', ['banked' => $pts($category['banked']), 'available' => $pts($category['available'])]) }}</span>
            <div class="bar" role="img" aria-label="{{ $pct($category['banked'], $category['available']) }}%"><div class="fill" style="width: {{ $pct($category['banked'], $category['available']) }}%"></div></div>
        </div>

        @if (empty($category['flags']))
            <p class="none">{{ __('dossier.none_recorded') }}</p>
        @else
            <div class="table-wrap">
                <table class="flags">
                    <thead>
                        <tr>
                            <th class="col-id">{{ __('dossier.flags.col_flag') }}</th>
                            <th>{{ __('dossier.flags.col_question') }}</th>
                            <th class="col-pts right">{{ __('dossier.flags.col_pts') }}</th>
                            <th class="col-pts right">{{ __('dossier.flags.col_osint') }}</th>
                            <th class="col-status">{{ __('dossier.flags.col_status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($category['flags'] as $flag)
                            @php($hasFindings = ! empty($flag['findings']))
                            <tr class="flag {{ $hasFindings ? 'has-findings' : '' }}" id="flag-{{ $flag['id'] }}">
                                <td class="num nowrap"><b>{{ $flag['id'] }}</b></td>
                                <td>
                                    {{ $flag['question'] }}
                                    @if ($flag['bonus'])<span class="tag">{{ __('dossier.tags.bonus') }}</span>@endif
                                    @if ($flag['call_only'])<span class="tag">{{ __('dossier.tags.call_only') }}</span>@endif
                                    @if (! empty($flag['related_flag_id']))<span class="tag">{{ __('dossier.tags.duplicate_of', ['id' => $flag['related_flag_id']]) }}</span>@endif
                                    @if (! empty($flag['notes']))<div class="flag-notes">{{ $flag['notes'] }}</div>@endif
                                </td>
                                <td class="right num">{{ $pts($flag['points']) }}</td>
                                <td class="right num">{{ $pts($flag['osint_points']) }}</td>
                                <td><span class="status status-{{ $flag['status'] }}">{{ __('dossier.status.'.$flag['status']) }}</span></td>
                            </tr>
                            @if ($hasFindings)
                                <tr class="findings">
                                    <td colspan="5">
                                        @foreach ($flag['findings'] as $finding)
                                            @include('dossier.finding', ['finding' => $finding, 'dt' => $dt, 'd' => $d, 'confWord' => $confWord])
                                        @endforeach
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @empty
        <p class="none">{{ __('dossier.none_recorded') }}</p>
    @endforelse
</section>

{{-- 5. Phone directory --}}
<section id="phones" class="page">
    <h2><span class="num">4.</span>{{ __('dossier.sections.phones') }}</h2>
    <p class="muted small">
        {!! __('dossier.phones.intro_refs', [
            'reference' => '<b>'.e(__('dossier.phones.reference_word')).'</b>',
            'a1' => '<span class="ref">A-1</span>',
            'd12' => '<span class="ref">D-12</span>',
        ]) !!}
        {{ __('dossier.phones.intro_pretexts') }}
        {{ __('dossier.phones.intro_booth') }}
        {{ __('dossier.phones.intro_format') }}
    </p>
    @if (empty($report['phones']))
        <p class="none" data-test="dossier-phones">{{ __('dossier.none_recorded') }}</p>
    @else
        <p class="muted small" data-test="dossier-phone-kinds">
            <b>{{ __('dossier.phones.kinds_summary', ['numbers' => count($report['phones']), 'kinds' => $kindCounts->count()]) }}</b>
            @if ($kindCounts->isNotEmpty())
                · {{ $kindCounts->sortDesc()->map(fn ($n, $kind) => $kindWord($kind).' '.$n)->implode(', ') }}
            @endif
        </p>
        <h3>{{ __('dossier.phones.call_sheet') }}</h3>
        <div class="table-wrap">
            <table class="callsheet" data-test="dossier-callsheet">
                <thead>
                    <tr>
                        <th class="col-ref">{{ __('dossier.phones.col_ref') }}</th>
                        <th class="col-num">{{ __('dossier.phones.col_number') }}</th>
                        <th class="col-ext">{{ __('dossier.phones.col_ext') }}</th>
                        <th>{{ __('dossier.phones.col_label') }}</th>
                        <th class="col-kind">{{ __('dossier.phones.col_kind') }}</th>
                        <th class="col-pretexts">{{ __('dossier.phones.col_pretexts') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['phone_groups'] as $group)
                        <tr class="group"><th colspan="6">{{ $group['letter'] }} · {{ $group['name'] }}</th></tr>
                        @foreach ($group['phones'] as $phone)
                            <tr id="phone-{{ $phone['ref'] }}">
                                <td><a class="ref" href="#phone-{{ $phone['ref'] }}-details">{{ $phone['ref'] }}</a></td>
                                <td class="mono nowrap"><b class="copy" data-copy="{{ $phone['number'] }}" tabindex="0" role="button" title="{{ __('dossier.copy.hint') }}">{{ $phone['number'] }}</b></td>
                                <td class="mono">@if (! empty($phone['extension']))<span class="copy" data-copy="{{ $phone['extension'] }}" tabindex="0" role="button" title="{{ __('dossier.copy.hint') }}">{{ $phone['extension'] }}</span>@endif</td>
                                <td>{{ $phone['label'] ?? '' }}{!! $enTag($phone) !!}</td>
                                <td class="kind">{{ $kindWord($phone['kind'] ?? null) ?? '—' }}</td>
                                <td class="nowrap">@forelse ($phone['pretexts'] as $ref)<a class="ref" href="#pretext-{{ $ref }}">{{ $ref }}</a> @empty <span class="faint">—</span>@endforelse</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        <h3>{{ __('dossier.phones.details') }}</h3>
        <div class="table-wrap">
            <table class="directory" data-test="dossier-phones">
                <thead>
                    <tr>
                        <th class="col-ref">{{ __('dossier.phones.col_ref') }}</th>
                        <th class="col-num">{{ __('dossier.phones.col_number') }}</th>
                        <th class="col-ext">{{ __('dossier.phones.col_ext') }}</th>
                        <th class="col-label">{{ __('dossier.phones.col_label') }}</th>
                        <th class="col-kind">{{ __('dossier.phones.col_kind') }}</th>
                        <th class="col-dept">{{ __('dossier.phones.col_department') }}</th>
                        <th class="col-person">{{ __('dossier.phones.col_person') }}</th>
                        <th class="col-ring">{{ __('dossier.phones.col_ring') }}</th>
                        <th class="col-cite">{{ __('dossier.phones.col_cite') }}</th>
                        <th>{{ __('dossier.phones.col_notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['phones'] as $phone)
                        <tr id="phone-{{ $phone['ref'] }}-details">
                            <td><a class="ref" href="#phone-{{ $phone['ref'] }}">{{ $phone['ref'] }}</a></td>
                            <td class="mono nowrap"><span class="copy" data-copy="{{ $phone['number'] }}" tabindex="0" role="button" title="{{ __('dossier.copy.hint') }}">{{ $phone['number'] }}</span></td>
                            <td class="mono">{{ $phone['extension'] ?? '' }}</td>
                            <td>{{ $phone['label'] ?? '' }}{!! $enTag($phone) !!}</td>
                            <td class="kind">{{ $kindWord($phone['kind'] ?? null) ?? '—' }}</td>
                            <td>{{ $phone['department'] ?? '' }}</td>
                            <td>{{ $phone['person']['name'] ?? '' }}</td>
                            <td>@if (! empty($phone['ring_verified_at']))<b>{{ __('dossier.yes') }}</b>, {{ $dt($phone['ring_verified_at']) }}@else {{ __('dossier.no') }} @endif</td>
                            <td class="num">@if (! empty($phone['citation']))<a class="cite" href="#src-{{ $phone['citation'] }}">[{{ $phone['citation'] }}]</a>@else <span class="faint">—</span>@endif</td>
                            <td>{{ $phone['notes'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- 6. People --}}
<section id="people">
    <h2><span class="num">5.</span>{{ __('dossier.sections.people') }}</h2>
    <p class="muted small">{{ __('dossier.people.intro') }}</p>
    @if (empty($report['people']))
        <p class="none" data-test="dossier-people">{{ __('dossier.none_recorded') }}</p>
    @else
        <div class="table-wrap">
            <table class="directory" data-test="dossier-people">
                <thead>
                    <tr>
                        <th class="col-name">{{ __('dossier.people.col_name') }}</th>
                        <th class="col-title">{{ __('dossier.people.col_title') }}</th>
                        <th class="col-dept">{{ __('dossier.people.col_department') }}</th>
                        <th class="col-tenure">{{ __('dossier.people.col_tenure') }}</th>
                        <th class="col-profile">{{ __('dossier.people.col_profile') }}</th>
                        <th class="col-cite">{{ __('dossier.people.col_cite') }}</th>
                        <th>{{ __('dossier.people.col_notes') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['people'] as $person)
                        <tr>
                            <td class="name"><b>{{ $person['name'] }}</b>{!! $enTag($person) !!}</td>
                            <td>{{ $person['title'] ?? '' }}</td>
                            <td>{{ $person['department'] ?? '' }}</td>
                            <td>{{ $person['tenure'] ?? '' }}</td>
                            <td>@if (! empty($person['profile_url']))<a class="url" href="{{ $person['profile_url'] }}" target="_blank" rel="noreferrer">{{ $person['profile_url'] }}</a>@endif</td>
                            <td class="num">@if (! empty($person['citation']))<a class="cite" href="#src-{{ $person['citation'] }}">[{{ $person['citation'] }}]</a>@else <span class="faint">—</span>@endif</td>
                            <td>{{ $person['notes'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>

{{-- 7. Pretexts --}}
<section id="pretexts" class="page">
    <h2><span class="num">6.</span>{{ __('dossier.sections.pretexts') }}</h2>
    @if (($readiness['pretexts']['have'] ?? count($report['pretexts'])) < ($readiness['pretexts']['need'] ?? 0))
        <span class="section-note" data-test="dossier-pretexts-min">{{ __('dossier.pretexts.min_note', ['have' => $readiness['pretexts']['have'], 'need' => $readiness['pretexts']['need']]) }}</span>
    @endif

    {{-- Coverage: one row per pretext --}}
    <h3 style="margin-top: 6px;">{{ __('dossier.pretexts.coverage') }}</h3>
    @if (empty($report['pretexts']))
        <p class="none">{{ __('dossier.none_recorded') }}</p>
    @else
        <div class="table-wrap">
            <table class="coverage" data-test="dossier-pretext-coverage">
                <thead>
                    <tr>
                        <th class="col-ref">{{ __('dossier.pretexts.col_ref') }}</th>
                        <th>{{ __('dossier.pretexts.col_title') }}</th>
                        <th class="col-n right">{{ __('dossier.pretexts.col_primary') }}</th>
                        <th class="col-n right">{{ __('dossier.pretexts.col_pivots') }}</th>
                        <th class="col-n right">{{ __('dossier.pretexts.col_phones') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($report['pretexts'] as $pretext)
                        <tr>
                            <td><a class="ref" href="#pretext-{{ $pretext['ref'] }}">{{ $pretext['ref'] }}</a></td>
                            <td>{{ $pretext['title'] }}{!! $enTag($pretext) !!}</td>
                            <td class="right num">{{ count($pretext['flags'] ?? []) }}</td>
                            <td class="right num">{{ count($pretext['pivot_flags'] ?? []) }}</td>
                            <td class="right num">{{ count($pretext['phone_numbers'] ?? []) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Which flags any pretext can reach --}}
    <h3>{{ __('dossier.pretexts.reachable') }}</h3>
    <p class="role-key">{!! __('dossier.pretexts.reachable_intro', [
        'primary' => '<span class="ref">P1</span> '.e(__('dossier.pretexts.role_primary')),
        'pivot' => '<span class="ref pivot">P1</span> '.e(__('dossier.pretexts.role_pivot')),
    ]) !!}</p>
    @if (empty($reachable))
        <p class="none">{{ __('dossier.none_recorded') }}</p>
    @else
        <div class="table-wrap">
            <table class="reach" data-test="dossier-pretext-reach">
                <thead>
                    <tr>
                        <th class="col-id">{{ __('dossier.pretexts.col_flag') }}</th>
                        <th>{{ __('dossier.pretexts.col_question') }}</th>
                        <th class="col-pts right">{{ __('dossier.flags.col_osint') }}</th>
                        <th class="col-status">{{ __('dossier.flags.col_status') }}</th>
                        <th class="col-refs">{{ __('dossier.pretexts.col_pretexts') }}</th>
                    </tr>
                </thead>
                <tbody>{!! $reachRows(array_slice($reachable, 0, $foldAfter)) !!}</tbody>
            </table>
            @if (count($reachable) > $foldAfter)
                <details class="fold">
                    <summary><span class="more">{{ __('dossier.pretexts.more', ['n' => count($reachable) - $foldAfter]) }}</span><span class="less">{{ __('dossier.pretexts.less') }}</span></summary>
                    <table class="reach">
                        <tbody>{!! $reachRows(array_slice($reachable, $foldAfter)) !!}</tbody>
                    </table>
                </details>
            @endif
        </div>
    @endif

    {{-- Unresolved flags nothing reaches --}}
    <h3>{{ __('dossier.pretexts.uncovered') }}</h3>
    @if (empty($coverage['uncovered']))
        <p class="muted small" data-test="dossier-pretext-uncovered">{{ __('dossier.pretexts.uncovered_none') }}</p>
    @else
        <div class="table-wrap">
            <table class="reach" data-test="dossier-pretext-uncovered">
                <thead>
                    <tr>
                        <th class="col-id">{{ __('dossier.pretexts.col_flag') }}</th>
                        <th>{{ __('dossier.pretexts.col_question') }}</th>
                        <th class="col-pts right">{{ __('dossier.flags.col_osint') }}</th>
                        <th class="col-status">{{ __('dossier.flags.col_status') }}</th>
                    </tr>
                </thead>
                <tbody>{!! $uncoveredRows(array_slice($coverage['uncovered'], 0, $foldAfter)) !!}</tbody>
            </table>
            @if (count($coverage['uncovered']) > $foldAfter)
                <details class="fold">
                    <summary><span class="more">{{ __('dossier.pretexts.more', ['n' => count($coverage['uncovered']) - $foldAfter]) }}</span><span class="less">{{ __('dossier.pretexts.less') }}</span></summary>
                    <table class="reach">
                        <tbody>{!! $uncoveredRows(array_slice($coverage['uncovered'], $foldAfter)) !!}</tbody>
                    </table>
                </details>
            @endif
        </div>
    @endif

    @if (! empty($report['pretexts']))
        <h3>{{ __('dossier.sections.pretexts') }}</h3>
        @foreach ($report['pretexts'] as $pretext)
            <article class="card" id="pretext-{{ $pretext['ref'] }}">
                <h3><span class="ref">{{ $pretext['ref'] }}</span> {{ $pretext['title'] }}{!! $enTag($pretext) !!}</h3>
                <div class="meta">
                    @if (isset($pretext['confidence'])){{ __('dossier.pretexts.confidence') }}{{ __('dossier.colon') }} <b>{{ $confWord($pretext['confidence']) }}</b> ({{ $pretext['confidence'] }})@endif
                    @if (! empty($pretext['by'])) · {{ __('dossier.pretexts.prepared_by', ['name' => $pretext['by']]) }}@endif
                </div>
                <dl>
                    <dt>{{ __('dossier.pretexts.persona') }}</dt>
                    <dd>@if (! empty($pretext['persona'])){{ $pretext['persona'] }}@else <span class="none">{{ __('dossier.none_recorded') }}</span>@endif</dd>
                    <dt>{{ __('dossier.pretexts.script') }}</dt>
                    <dd>@if (! empty($pretext['script']))<pre class="script">{{ $pretext['script'] }}</pre>@else <span class="none">{{ __('dossier.none_recorded') }}</span>@endif</dd>
                    <dt>{{ __('dossier.pretexts.target_flags') }}</dt>
                    <dd>
                        @if (empty($pretext['flags']))
                            <span class="none">{{ __('dossier.none_recorded') }}</span>
                        @else
                            <ul class="plain">
                                @foreach ($pretext['flags'] as $flag)
                                    <li><a href="#flag-{{ $flag['id'] }}" class="flag-link">{{ $flag['id'] }}</a> — {{ $flag['question'] }}@if ($flag['points'] !== null) <span class="pts">({{ $pts($flag['points']) }} {{ __('dossier.flags.col_osint') }})</span>@endif {!! $statusChip($flag['status'] ?? null) !!}</li>
                                @endforeach
                            </ul>
                        @endif
                    </dd>
                    <dt>{{ __('dossier.pretexts.pivot_flags') }}</dt>
                    <dd data-test="dossier-pretext-pivots">
                        @if (empty($pretext['pivot_flags']))
                            <span class="none">{{ __('dossier.none_recorded') }}</span>
                        @else
                            <ul class="plain">
                                @foreach ($pretext['pivot_flags'] as $flag)
                                    <li>
                                        <a href="#flag-{{ $flag['id'] }}" class="flag-link">{{ $flag['id'] }}</a> — {{ $flag['question'] }}@if ($flag['points'] !== null) <span class="pts">({{ $pts($flag['points']) }} {{ __('dossier.flags.col_osint') }})</span>@endif {!! $statusChip($flag['status'] ?? null) !!}
                                        @if (! empty($flag['bridge']))<span class="bridge">{{ $flag['bridge'] }}</span>@endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </dd>
                    <dt>{{ __('dossier.pretexts.numbers_to_dial') }}</dt>
                    <dd>
                        @if (empty($pretext['phone_numbers']))
                            <span class="none">{{ __('dossier.none_recorded') }}</span>
                        @else
                            <ul class="plain">
                                @foreach ($pretext['phone_numbers'] as $phone)
                                    <li>@if (! empty($phone['ref']))<a class="ref" href="#phone-{{ $phone['ref'] }}">{{ $phone['ref'] }}</a> @endif<span class="mono"><span class="copy" data-copy="{{ $phone['number'] }}" tabindex="0" role="button" title="{{ __('dossier.copy.hint') }}">{{ $phone['number'] }}</span>@if (! empty($phone['extension'])) {{ __('dossier.pretexts.ext', ['n' => $phone['extension']]) }}@endif</span>@if (! empty($phone['label'])) — {{ $phone['label'] }}@endif</li>
                                @endforeach
                            </ul>
                        @endif
                    </dd>
                </dl>
            </article>
        @endforeach
    @endif
</section>

{{-- 8. Methodology --}}
<section id="methodology" class="page">
    <h2><span class="num">7.</span>{{ __('dossier.sections.methodology') }}</h2>
    <p>{{ __('dossier.methodology.paragraph') }}</p>

    <dl class="facts">
        <dt>{{ __('dossier.methodology.contributors') }}</dt>
        <dd>@if (! empty($method['contributors'])){{ implode(', ', $method['contributors']) }}@else <span class="muted">{{ __('dossier.none_recorded') }}</span>@endif</dd>
        <dt>{{ __('dossier.methodology.searches_logged') }}</dt>
        <dd class="num">{{ $method['searches'] }}</dd>
        <dt>{{ __('dossier.methodology.searches_ran') }}</dt>
        <dd>@if ($method['first_search_at']){{ $dt($method['first_search_at']) }} → {{ $dt($method['last_search_at']) }}@else <span class="muted">{{ __('dossier.none_recorded') }}</span>@endif</dd>
        <dt>{{ __('dossier.methodology.findings_filed') }}</dt>
        <dd>@if ($method['first_finding_at']){{ $dt($method['first_finding_at']) }} → {{ $dt($method['last_finding_at']) }}@else <span class="muted">{{ __('dossier.none_recorded') }}</span>@endif</dd>
    </dl>

    <h4>{{ __('dossier.methodology.by_channel') }}</h4>
    @if (empty($method['searches_by_channel']))
        <p class="none">{{ __('dossier.none_recorded') }}</p>
    @else
        <table style="max-width: 360px;">
            <thead><tr><th>{{ __('dossier.methodology.col_channel') }}</th><th class="right">{{ __('dossier.methodology.col_searches') }}</th></tr></thead>
            <tbody>
                @foreach ($method['searches_by_channel'] as $channel => $count)
                    <tr><td>{{ $channel }}</td><td class="right num">{{ $count }}</td></tr>
                @endforeach
            </tbody>
            <tfoot><tr><td>{{ __('dossier.methodology.total') }}</td><td class="right num">{{ $method['searches'] }}</td></tr></tfoot>
        </table>
    @endif
</section>

{{-- 9. Evidence appendix --}}
<section id="sources" class="page">
    <h2><span class="num">8.</span>{{ __('dossier.sections.sources') }}</h2>
    <p class="muted small">{{ __('dossier.sources.intro') }}</p>
    @if (empty($report['sources']))
        <p class="none" data-test="dossier-sources">{{ __('dossier.none_recorded') }}</p>
    @else
        <ol class="appendix" data-test="dossier-sources">
            @foreach ($report['sources'] as $source)
                <li id="src-{{ $source['n'] }}">
                    <span class="n">[{{ $source['n'] }}]</span>
                    <div><a class="url" href="{{ $source['url'] }}" target="_blank" rel="noreferrer">{{ $source['url'] }}</a></div>
                    <div class="src-meta">
                        @if (! empty($source['title']))<b>{{ $source['title'] }}</b> · @endif
                        {{ __('dossier.sources.retrieved', ['at' => $dt($source['retrieved_at'])]) }}
                        @if (! empty($source['content_date'])) · {{ __('dossier.sources.content_dated', ['date' => $d($source['content_date'])]) }}@if (! empty($source['content_date_basis'])) ({{ $source['content_date_basis'] }})@endif @endif
                        @if (! empty($source['cited_by'])) · {{ __('dossier.sources.cited_by', ['refs' => implode(', ', array_map($citedBy, $source['cited_by']))]) }}@endif
                    </div>
                </li>
            @endforeach
        </ol>
    @endif
</section>

<footer>
    {{ __('dossier.footer.line', [
        'target' => $target['name'],
        'event' => $report['event']['name'],
        'phase' => $report['event']['phase'],
        'at' => $dt($report['generated_at']),
        'owner' => $target['owner'] ?? __('dossier.footer.team'),
    ]) }}
    {{ __('dossier.footer.totals', [
        'sources' => $summary['sources'],
        'findings' => $summary['findings'],
        'banked' => $pts($summary['osint_points_banked']),
        'available' => $pts($summary['osint_points_available']),
    ]) }}
</footer>

<script>
    (function () {
        function write(text) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(text);
            }
            return new Promise(function (resolve, reject) {
                var area = document.createElement('textarea');
                area.value = text;
                area.style.position = 'fixed';
                area.style.opacity = '0';
                document.body.appendChild(area);
                area.select();
                try { document.execCommand('copy') ? resolve() : reject(); } catch (e) { reject(e); } finally { area.remove(); }
            });
        }

        document.querySelectorAll('.copy').forEach(function (el) {
            el.setAttribute('data-done', @json(__('dossier.copy.done')));
            function copy() {
                write(el.dataset.copy).then(function () {
                    el.classList.add('copied');
                    setTimeout(function () { el.classList.remove('copied'); }, 1200);
                }, function () {});
            }
            el.addEventListener('click', copy);
            el.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); copy(); }
            });
        });

        // Folded lists open for printing so the PDF is complete, then fold back.
        var folds = document.querySelectorAll('details.fold');
        var wasOpen = [];
        window.addEventListener('beforeprint', function () {
            wasOpen = Array.prototype.map.call(folds, function (d) { return d.open; });
            folds.forEach(function (d) { d.open = true; });
        });
        window.addEventListener('afterprint', function () {
            folds.forEach(function (d, i) { d.open = !! wasOpen[i]; });
        });

        // "Copy all numbers": one tab-separated line per call-sheet row (ref, number, ext, label), pastes cleanly into a sheet.
        var all = document.getElementById('copy-numbers');
        all.addEventListener('click', function () {
            var rows = document.querySelectorAll('table.callsheet tbody tr[id^="phone-"]');
            var lines = Array.prototype.map.call(rows, function (row) {
                var cells = row.querySelectorAll('td');
                var num = row.querySelector('[data-copy]');
                return [cells[0].textContent.trim(), num ? num.dataset.copy : '', cells[2].textContent.trim(), cells[3].textContent.trim()].join('\t');
            });
            write(lines.join('\n')).then(function () {
                var old = all.textContent;
                all.textContent = all.dataset.done;
                setTimeout(function () { all.textContent = old; }, 1200);
            }, function () {});
        });
    })();
</script>
</body>
</html>
