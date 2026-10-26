@extends('layouts.app')

@section('title', 'RFID Scan — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>RFID Scan</b>
@endsection

@section('topbar-actions')
    <span class="stamp ok"><span class="dot live" style="margin-right:2px"></span> Reader ready</span>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Scan a student ID</h1>
            <p class="sub">Tap a card on the reader, or type the student's ID number manually. Both routes open the student's record.</p>
        </div>
        <div class="seg" role="tablist">
            <button type="button" class="on" id="tab-scan" onclick="setMode('scan')">
                <svg viewBox="0 0 16 16" fill="none"><path d="M1.5 5V3a1.5 1.5 0 0 1 1.5-1.5h2M11 1.5h2A1.5 1.5 0 0 1 14.5 3v2M14.5 11v2a1.5 1.5 0 0 1-1.5 1.5h-2M5 14.5H3A1.5 1.5 0 0 1 1.5 13v-2M2.5 8h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                Scan with reader
            </button>
            <button type="button" id="tab-manual" onclick="setMode('manual')">
                <svg viewBox="0 0 16 16" fill="none"><rect x="2" y="4.5" width="12" height="8" rx="1.5" stroke="currentColor" stroke-width="1.3"/><path d="M4.5 12.5v1.2M11.5 12.5v1.2M4.5 2.3v1.2M11.5 2.3v1.2M2 8.5h12" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>
                Type manually
            </button>
        </div>
    </div>

    <div class="scan-grid">

        <section>
            <div id="pane-scan">
                <div class="scan-stage">
                    <div class="stage-kicker" id="stage-kicker"><span class="dot live"></span> Awaiting card</div>
                    <div class="ring" id="ring">
                        <span class="wave"></span><span class="wave w2"></span>
                        <div class="core">
                            <svg viewBox="0 0 24 24" fill="none"><path d="M3 8V5.5A2.5 2.5 0 0 1 5.5 3H8M16 3h2.5A2.5 2.5 0 0 1 21 5.5V8M21 16v2.5a2.5 2.5 0 0 1-2.5 2.5H16M8 21H5.5A2.5 2.5 0 0 1 3 18.5V16M6 12h12" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                        </div>
                    </div>
                    <div class="stage-title">Tap the card on the reader</div>
                    <p class="stage-hint">Hold the ID within a few centimeters. The card's number is captured automatically — no typing needed.</p>
                    <div class="wedge" id="wedge"><span id="wedge-txt">READY</span><span class="caret"></span></div>
                    <div class="stage-foot">
                        <span class="kbd">USB reader · keyboard emulation</span>
                        <span>Type on your keyboard to test the capture.</span>
                    </div>
                </div>
            </div>

            <div id="pane-manual" hidden>
                <div class="card manual-card">
                    <div class="card-head"><h2>Enter the ID number</h2><span class="meta">as printed on the ID</span></div>
                    <div class="card-body">
                        <form onsubmit="lookupManual(event)">
                            <div class="field">
                                <label for="manual-id">ID number</label>
                                <input class="input mono" id="manual-id" placeholder="e.g. 1162513380" autocomplete="off"
                                       oninput="document.getElementById('id-preview').textContent = (this.value || '— — — — — —').toUpperCase();">
                                <p class="help">The ID number printed on the student's ID. RFID numbers aren't visible on the card, so lookup uses the ID number instead.</p>
                            </div>
                            <div class="id-preview" style="margin-bottom:16px;"><span class="lbl" style="margin-right:8px">ID NO.</span><span id="id-preview">— — — — — —</span></div>
                            <button class="btn btn-primary" style="width:100%" type="submit">Look up student</button>
                        </form>
                    </div>
                </div>
            </div>
        </section>

        <section style="display:grid; gap:18px;">
            <div id="result-slot">
                <div class="card">
                    <div class="card-body empty" style="padding: 34px 24px;">
                        <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><path d="M1.5 5V3a1.5 1.5 0 0 1 1.5-1.5h2M11 1.5h2A1.5 1.5 0 0 1 14.5 3v2M14.5 11v2a1.5 1.5 0 0 1-1.5 1.5h-2M5 14.5H3A1.5 1.5 0 0 1 1.5 13v-2M2.5 8h11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></div>
                        <b>No card read yet</b>
                        Scan a card or type an ID number to open the student's record.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Recent scans</h2><span class="meta">this session</span></div>
                <div class="card-body" style="padding-top:4px;">
                    <ul class="recent" id="recent"></ul>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    const recent = JSON.parse(sessionStorage.getItem('dcc-recent-scans') || '[]');

    function renderRecent() {
        const ul = document.getElementById('recent');
        ul.innerHTML = recent.slice(0, 6).map((r) =>
            '<li><span class="rt">' + r.t + '</span><span class="rid">' + escapeHtml(r.id) + '</span><span class="who2">' +
            (r.name ? '<b>' + escapeHtml(r.name) + '</b> · ' + escapeHtml(r.program || '') : 'Unknown card') +
            '</span>' + (r.name ? '<span class="stamp ok">Matched</span>' : '<span class="stamp grave">No match</span>') + '</li>'
        ).join('') || '<li><span class="who2" style="color:var(--fg-muted)">Nothing scanned yet.</span></li>';
    }

    function escapeHtml(s) {
        const A = '&' + 'amp;', L = '&' + 'lt;', G = '&' + 'gt;', Q = '&' + 'quot;';
        return String(s ?? '').replace(/&/g, A).replace(/</g, L).replace(/>/g, G).replace(/"/g, Q);
    }

    function escapeAttr(s) {
        const A = '&' + 'amp;', L = '&' + 'lt;', G = '&' + 'gt;', Q = '&' + 'quot;', S = '&' + '#39;';
        return String(s ?? '').replace(/&/g, A).replace(/</g, L).replace(/>/g, G).replace(/"/g, Q).replace(/'/g, S);
    }

    function setMode(mode) {
        const scan = mode === 'scan';
        document.getElementById('pane-scan').hidden = !scan;
        document.getElementById('pane-manual').hidden = scan;
        document.getElementById('tab-scan').classList.toggle('on', scan);
        document.getElementById('tab-manual').classList.toggle('on', !scan);
    }

    function lookupManual(event) {
        event.preventDefault();
        const id = document.getElementById('manual-id').value.trim();
        if (id.length > 0) {
            resolve(id.toUpperCase());
        }
    }

    async function resolve(cardUid) {
        const ring = document.getElementById('ring');
        ring.classList.add('fired');
        setTimeout(() => ring.classList.remove('fired'), 400);
        document.getElementById('wedge-txt').textContent = cardUid;
        document.getElementById('stage-kicker').innerHTML = '<span class="dot live"></span> Looking up…';

        const slot = document.getElementById('result-slot');

        try {
            const res = await fetch('/api/student/' + encodeURIComponent(cardUid), { headers: { 'Accept': 'application/json' } });
            const text = await res.text();

            let data = null;
            try { data = JSON.parse(text); } catch (parseError) { data = null; }

            if (!res.ok || data === null) {
                document.getElementById('stage-kicker').innerHTML = '<span class="dot"></span> Reader ready';
                slot.innerHTML =
                    '<div class="card"><div class="card-body empty" style="padding: 34px 24px;">' +
                        '<div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v3.4M8 10.8v.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></div>' +
                        '<b>The lookup service didn\'t respond correctly</b>' +
                        'Your session may have expired. Refresh the page and scan again.' +
                    '</div></div>';
                return;
            }

            renderResult(data, cardUid);
        } catch (err) {
            document.getElementById('stage-kicker').innerHTML = '<span class="dot"></span> Reader ready';
            slot.innerHTML =
                '<div class="card"><div class="card-body empty" style="padding: 34px 24px;">' +
                    '<div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M8 5v3.4M8 10.8v.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></div>' +
                    '<b>Can\'t reach the server</b>' +
                    'The app isn\'t responding — make sure the server is running, then refresh this page and scan again.' +
                '</div></div>';
        }
    }

    function renderResult(data, queriedId) {
        const now = new Date();
        const time = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');
        const slot = document.getElementById('result-slot');

        if (data.exists) {
            const s = data.student;
            recent.unshift({ id: data.card_uid || queriedId, name: s.full_name, program: s.program, t: time });
            const statusStamp = s.enrollment_status === 'enrolled'
                ? '<span class="stamp ok">Enrolled</span>'
                : '<span class="stamp warn">' + escapeHtml(s.enrollment_status.replace(/_/g, ' ')) + '</span>';
            const caseLine = s.cases > 0
                ? s.cases + ' prior case' + (s.cases === 1 ? '' : 's') + ' on record'
                : 'No prior cases — a clean record';
            slot.innerHTML =
                '<div class="found">' +
                    '<div class="found-head">' +
                        '<img class="avatar-photo" src="' + escapeAttr(s.avatar) + '" alt="Photo of ' + escapeAttr(s.full_name) + '" width="52" height="52">' +
                        '<div><h3>' + escapeHtml(s.full_name) + '</h3><div class="meta">' + escapeHtml(s.program) + '</div></div>' +
                        '<span style="margin-left:auto">' + statusStamp + '</span>' +
                    '</div>' +
                    '<div class="found-body">' +
                        '<div class="kv"><span class="k">Student no.</span><span class="v mono">' + escapeHtml(s.student_number) + '</span></div>' +
                        '<div class="kv"><span class="k">RFID card</span><span class="v mono">' + (data.card_uid ? escapeHtml(data.card_uid) : 'No card assigned') + '</span></div>' +
                        '<div class="kv"><span class="k">Offense record</span><span class="v">' + caseLine + '</span></div>' +
                    '</div>' +
                    '<div class="found-actions">' +
                        '<a class="btn btn-accent" href="/offenses/create/' + s.id + '">File an offense</a>' +
                        '<a class="btn btn-ghost" href="/students/' + s.id + '">Open profile</a>' +
                    '</div>' +
                '</div>';
            document.getElementById('stage-kicker').innerHTML = '<span class="dot live"></span> Match found';
        } else {
            recent.unshift({ id: queriedId, name: null, t: time });
            slot.innerHTML =
                '<div class="found">' +
                    '<div class="found-head" style="background: var(--red-50); border-bottom-color: var(--red-100);">' +
                        '<div class="ph">UNKNOWN</div>' +
                        '<div><h3>Not recognized</h3><div class="meta">No student or active card matches <span class="mono">' + escapeHtml(data.card_uid || queriedId) + '</span>.</div></div>' +
                    '</div>' +
                    '<div class="found-body"><p style="font-size:13.5px;">Check the ID number and try again, or this could be an unregistered card. Assign it to a student from their profile, or register it during enrollment.</p></div>' +
                    '<div class="found-actions"><a class="btn btn-primary" href="/students?no_rfid=1">Find student to assign</a></div>' +
                '</div>';
            document.getElementById('stage-kicker').innerHTML = '<span class="dot"></span> Not recognized';
        }

        sessionStorage.setItem('dcc-recent-scans', JSON.stringify(recent.slice(0, 12)));
        renderRecent();
    }

    // Keyboard-wedge capture: USB readers "type" the card UID in a rapid burst ending with Enter.
    let buffer = '', lastKey = 0;
    document.addEventListener('keydown', (e) => {
        if (!document.getElementById('pane-manual').hidden) return;
        if (document.activeElement && document.activeElement.tagName === 'INPUT') return;
        const now = Date.now();
        if (now - lastKey > 300) buffer = '';
        lastKey = now;
        if (e.key === 'Enter') {
            if (buffer.length >= 4) resolve(buffer.toUpperCase());
            buffer = '';
            return;
        }
        if (/^[0-9a-zA-Z-]$/.test(e.key)) {
            buffer += e.key;
            document.getElementById('wedge-txt').textContent = buffer.toUpperCase();
        }
    });

    renderRecent();

    @if (preg_match('/^[A-Za-z0-9\-]{4,}$/', (string) request('card')))
        resolve('{{ preg_replace("/[^A-Za-z0-9\-]/", "", strtoupper((string) request("card"))) }}');
    @endif
</script>
@endpush
