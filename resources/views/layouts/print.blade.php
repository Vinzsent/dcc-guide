<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Report — DCC Care')</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&family=Instrument+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap');

        :root {
            --green-950: #0A1F15;
            --green-800: #14442C;
            --green-100: #DCEBE1;
            --gold-400: #D4A017;
            --gold-700: #8A6A0D;
            --ink-1: #14231B;
            --ink-2: #3D4A42;
            --ink-3: #6B776E;
            --line-1: #E6E3D7;
            --line-2: #D5D1C1;
            --red-700: #93221B;
            --paper: #FFFFFF;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font: 12.5px/1.6 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            color: var(--ink-2);
            background: #EDEBE2;
        }

        .doc {
            max-width: 820px;
            margin: 24px auto;
            background: var(--paper);
            box-shadow: 0 2px 6px rgba(20, 42, 30, 0.10), 0 16px 40px -18px rgba(20, 42, 30, 0.25);
            padding: 46px 52px;
            min-height: 1000px;
            position: relative;
        }

        /* Toolbar (screen only) */
        .toolbar {
            max-width: 820px;
            margin: 20px auto -8px;
            display: flex; align-items: center; gap: 10px;
        }
        .toolbar .hint { font-size: 12.5px; color: var(--ink-3); margin-left: auto; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px;
            font: 600 13px/1 'Instrument Sans', sans-serif;
            padding: 9px 16px; border-radius: 4px; cursor: pointer;
            border: 1px solid transparent; text-decoration: none;
        }
        .btn-primary { background: var(--green-800); color: #F4F9F5; }
        .btn-primary:hover { background: #1B5739; }
        .btn-ghost { background: #fff; border-color: var(--line-2); color: var(--ink-1); }
        .btn-ghost:hover { background: #F1EFE6; }

        /* Document header */
        .doc-head { display: flex; align-items: flex-start; gap: 14px; padding-bottom: 16px; border-bottom: 3px double var(--green-800); }
        .doc-mark {
            width: 40px; height: 40px; border-radius: 4px; flex-shrink: 0;
            background: var(--gold-400); color: var(--green-950);
            display: grid; place-items: center; font: 700 18px/1 'Fraunces', Georgia, serif;
            overflow: hidden;
        }
        .doc-mark img { width: 100%; height: 100%; object-fit: contain; background: #fff; border-radius: 3px; }
        .doc-mark:has(img) { background: #fff; border: 1px solid var(--line-2); padding: 2px; }
        .doc-head .who b { display: block; font: 600 17px/1.2 'Fraunces', Georgia, serif; color: var(--ink-1); }
        .doc-head .who span { font: 600 10px/1.6 'Instrument Sans', sans-serif; letter-spacing: 0.16em; text-transform: uppercase; color: var(--ink-3); }
        .doc-head .doc-type { margin-left: auto; text-align: right; }
        .doc-head .doc-type .kicker { font: 600 10px/1 'Instrument Sans', sans-serif; letter-spacing: 0.2em; text-transform: uppercase; color: var(--gold-700); }
        .doc-head .doc-type .ref { display: block; margin-top: 6px; font: 600 19px/1 'JetBrains Mono', monospace; letter-spacing: 0.06em; color: var(--ink-1); }

        /* Info grids */
        .grid-2col { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; margin-top: 20px; }
        .panel { border: 1px solid var(--line-1); border-radius: 6px; padding: 14px 16px; }
        .panel .panel-title { font: 600 10px/1 'Instrument Sans', sans-serif; letter-spacing: 0.14em; text-transform: uppercase; color: var(--ink-3); margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid var(--line-1); }
        .row { display: flex; justify-content: space-between; gap: 14px; padding: 5px 0; font-size: 12.5px; }
        .row .k { color: var(--ink-3); flex-shrink: 0; }
        .row .v { color: var(--ink-1); font-weight: 500; text-align: right; }
        .row .v.mono { font: 500 12px/1.5 'JetBrains Mono', monospace; }
        .name-lg { font: 600 21px/1.15 'Fraunces', Georgia, serif; color: var(--ink-1); }

        /* Stamps */
        .stamp {
            display: inline-flex; align-items: center; gap: 6px;
            font: 600 10px/1 'Instrument Sans', sans-serif; letter-spacing: 0.1em; text-transform: uppercase;
            padding: 4px 8px; border-radius: 3px; border: 1px solid currentColor;
            white-space: nowrap; vertical-align: middle;
        }
        .stamp::before { content: ""; width: 6px; height: 6px; border-radius: 1px; background: currentColor; }
        .stamp.ok { color: var(--green-800); }
        .stamp.warn { color: var(--gold-700); }
        .stamp.serious { color: #8A5A0D; }
        .stamp.grave { color: var(--red-700); }
        .stamp.info { color: #1E4F8A; }
        .stamp.muted { color: var(--ink-3); }
        .stamp.filled { background: var(--gold-400); color: var(--green-950); border-color: var(--gold-400); }

        /* Sections */
        .section { margin-top: 24px; }
        .section > h2 { font: 600 11px/1 'Instrument Sans', sans-serif; letter-spacing: 0.16em; text-transform: uppercase; color: var(--ink-3); padding-bottom: 7px; border-bottom: 1px solid var(--line-2); margin-bottom: 12px; }
        .prose { font-size: 13px; line-height: 1.75; color: var(--ink-1); white-space: pre-wrap; }
        .muted { color: var(--ink-3); font-size: 12px; }

        /* Case entries (history report) */
        .case-entry { padding: 13px 0 14px; border-bottom: 1px dashed var(--line-1); break-inside: avoid; page-break-inside: avoid; }
        .case-entry .case-top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .case-entry .case-top .ref { font: 600 12px/1 'JetBrains Mono', monospace; color: var(--ink-1); }
        .case-entry .case-top b { font-weight: 600; color: var(--ink-1); font-size: 13px; }
        .case-entry p.desc { margin-top: 5px; font-size: 12.5px; line-height: 1.6; color: var(--ink-2); }
        .case-entry .case-meta { margin-top: 4px; font-size: 11.5px; color: var(--ink-3); }
        .followup { margin-top: 7px; padding: 7px 10px; background: #F7F6F0; border-radius: 4px; font-size: 11.5px; color: var(--ink-2); }

        /* Summary strip */
        .tallies { display: flex; gap: 22px; margin-top: 14px; }
        .tallies .t { text-align: center; }
        .tallies .t b { display: block; font: 600 22px/1 'Fraunces', Georgia, serif; color: var(--ink-1); }
        .tallies .t span { font: 600 9.5px/1.8 'Instrument Sans', sans-serif; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-3); }

        /* Ledger table (history report) */
        table.ledger { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.ledger th { font: 600 9.5px/1.3 'Instrument Sans', sans-serif; letter-spacing: 0.12em; text-transform: uppercase; color: var(--ink-3); text-align: left; background: #F1EFE6; padding: 8px 10px; border-bottom: 1px solid var(--line-2); }
        table.ledger td { padding: 9px 10px; border-bottom: 1px solid var(--line-1); font-size: 12px; color: var(--ink-2); vertical-align: top; }
        table.ledger .mono { font: 500 11.5px/1.5 'JetBrains Mono', monospace; color: var(--ink-1); }

        /* Signatures */
        .signatures { display: grid; grid-template-columns: repeat(3, 1fr); gap: 26px; margin-top: 44px; }
        .sig .line { border-bottom: 1px solid var(--ink-1); height: 34px; }
        .sig .cap { margin-top: 6px; font: 600 10px/1.5 'Instrument Sans', sans-serif; letter-spacing: 0.1em; text-transform: uppercase; color: var(--ink-3); }
        .sig .sub { font-size: 11px; color: var(--ink-3); }

        .doc-foot { margin-top: 40px; padding-top: 12px; border-top: 1px solid var(--line-1); display: flex; justify-content: space-between; gap: 14px; font-size: 10.5px; color: var(--ink-3); }

        /* Empty */
        .empty-note { padding: 18px 0; font-size: 12.5px; color: var(--ink-3); font-style: italic; }

        @media print {
            @page { size: A4; margin: 14mm 13mm; }
            body { background: #fff; }
            .doc { margin: 0; box-shadow: none; max-width: none; padding: 0; min-height: 0; }
            .toolbar { display: none; }
            .stamp, .panel, table.ledger th, .followup, .doc-mark { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="btn btn-primary" type="button" onclick="window.print()">Print report</button>
        <a class="btn btn-ghost" href="javascript:history.back()">Back</a>
        <span class="hint">Prints on A4 · margins are handled for you</span>
    </div>

    <div class="doc">
        @yield('content')
    </div>
</body>
</html>
