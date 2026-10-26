@extends('layouts.app')

@section('title', 'Register RFID cards — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <b>Register cards</b>
@endsection

@section('topbar-actions')
    <span class="stamp ok"><span class="dot live" style="margin-right:2px"></span> Reader ready</span>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Register RFID cards</h1>
            <p class="sub">Scan a card to assign it to the highlighted student, then the queue advances automatically. Mis-scan? Undo from the activity log.</p>
        </div>
        <div class="page-head-actions" style="text-align: right;">
            <div style="font: 600 30px/1 var(--font-display); color: var(--fg-strong);"><span id="done-count">0</span> <span style="font-size: 16px; color: var(--fg-muted); font-family: var(--font-ui); font-weight: 500;">registered</span></div>
            <div style="font-size: 12px; color: var(--fg-muted); margin-top: 4px;"><span id="remaining-count">{{ $totalWithoutCards }}</span> students still without cards</div>
        </div>
    </div>

    <div class="scan-grid">

        <!-- Queue -->
        <section class="card table-card" style="overflow: hidden;">
            <div class="card-head" style="flex-wrap: wrap; row-gap: 10px;">
                <h2>Queue — students without cards</h2>
                <div class="toolbar" style="margin-left: auto;">
                    <div class="search" style="min-width: 170px; max-width: 220px;">
                        <svg viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.4" stroke="currentColor" stroke-width="1.4"/><path d="M10.4 10.4 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                        <input class="input" id="queue-search" type="search" placeholder="Search name or student no…" oninput="applyQueueFilter()">
                    </div>
                    <select class="select" id="queue-level" style="width: auto;" onchange="applyQueueFilter()">
                        <option value="College" selected>College</option>
                        <option value="Senior High School">Senior High</option>
                        <option value="Junior High School">Junior High</option>
                        <option value="Grade School">Elementary</option>
                        <option value="">All levels</option>
                    </select>
                </div>
                <span class="meta" id="queue-shown" style="width: 100%; text-align: right;">{{ $queue->count() }} loaded</span>
            </div>
            @if ($queue->isEmpty())
                <div class="empty">
                    <div class="glyph"><svg width="18" height="18" viewBox="0 0 16 16" fill="none"><path d="M2.5 8.5 6 12l7.5-8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                    <b>Everyone has a card</b>
                    No enrolled students are waiting for cards. Enroll new students to add them to this queue.
                </div>
            @else
                <ul class="queue-list" id="queue">
                    @foreach ($queue as $student)
                        <li class="queue-item @if ($loop->first) current @endif" data-id="{{ $student->id }}" data-name="{{ $student->full_name }}" data-number="{{ $student->student_number }}" data-program="{{ $student->program_label }}" data-level="{{ $student->education_level }}" onclick="selectStudent(this)">
                            <span class="avatar-sm">{{ mb_strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name, 0, 1)) }}</span>
                            <span class="q-main">
                                <b>{{ $student->full_name }}</b>
                                <span class="q-sub">{{ $student->student_number }} · {{ $student->program_label }}@if ($student->enrollment_status === 'pending_verification') · <span class="stamp warn" style="padding:2px 6px; font-size:9px;">pending</span>@endif</span>
                            </span>
                            @if ($loop->first)
                                <button type="button" class="btn btn-ghost btn-sm q-skip" onclick="event.stopPropagation(); skipStudent()">Skip</button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <!-- Capture + log -->
        <section style="display: grid; gap: 18px;">
            <div class="card">
                <div class="card-head"><h2>Card capture</h2><span class="meta">for <b id="target-name" style="color: var(--fg-strong);">{{ $queue->first()?->full_name ?? '—' }}</b></span></div>
                <div class="card-body">
                    <div class="wedge" id="wedge" style="max-width: none;"><span id="wedge-txt">READY</span><span class="caret"></span></div>
                    <p class="help" style="margin: 10px 0 14px;">Tap a card on the reader — or type its number and press Enter. Typing on your keyboard also works.</p>
                    <div style="display: flex; gap: 8px;">
                        <input class="input mono" id="manual-card" placeholder="Type card number…" autocomplete="off" style="flex: 1;">
                        <button class="btn btn-primary" type="button" onclick="submitManual()">Assign</button>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h2>Activity</h2><span class="meta">this session</span></div>
                <div class="card-body" style="padding-top: 6px;">
                    <ul class="log-list" id="log">
                        <li class="log-empty">Nothing registered yet. Scan the first card.</li>
                    </ul>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let doneCount = 0;
    let busy = false;

    function queueItems() {
        return [...document.querySelectorAll('.queue-item')];
    }

    function visibleItems() {
        return queueItems().filter((el) => el.style.display !== 'none');
    }

    function currentTarget() {
        return document.querySelector('.queue-item.current');
    }

    function selectStudent(el) {
        queueItems().forEach((item) => item.classList.remove('current'));
        el.classList.add('current');
        el.scrollIntoView({ block: 'nearest' });
        document.getElementById('target-name').textContent = el.dataset.name;
    }

    function advanceTarget() {
        const items = visibleItems();
        if (items.length > 0) {
            selectStudent(items[0]);
        } else {
            document.getElementById('target-name').textContent = 'queue complete';
        }
    }

    function applyQueueFilter() {
        const q = (document.getElementById('queue-search').value || '').trim().toLowerCase();
        const level = document.getElementById('queue-level').value;
        let visible = 0;
        let firstVisible = null;

        queueItems().forEach((item) => {
            const okLevel = level === '' || item.dataset.level === level;
            const hay = (item.dataset.name + ' ' + item.dataset.number).toLowerCase();
            const okQuery = q === '' || hay.includes(q);
            const show = okLevel && okQuery;

            item.style.display = show ? '' : 'none';
            if (show) {
                visible++;
                if (firstVisible === null) firstVisible = item;
            }
        });

        const shown = document.getElementById('queue-shown');
        if (shown) shown.textContent = visible + ' shown of ' + queueItems().length;

        const current = currentTarget();
        if (!current || current.style.display === 'none') {
            if (firstVisible) {
                selectStudent(firstVisible);
            } else {
                queueItems().forEach((item) => item.classList.remove('current'));
                document.getElementById('target-name').textContent = 'no match';
            }
        }
    }

    function skipStudent() {
        const current = currentTarget();
        const list = document.getElementById('queue');
        if (!current) return;
        current.classList.remove('current');
        list.appendChild(current);
        advanceTarget();
    }

    function updateProgress(remaining) {
        document.getElementById('done-count').textContent = doneCount;
        if (typeof remaining === 'number') {
            document.getElementById('remaining-count').textContent = remaining;
        }
    }

    function logEntry(kind, html, undoData) {
        const log = document.getElementById('log');
        const empty = log.querySelector('.log-empty');
        if (empty) empty.remove();

        const li = document.createElement('li');
        li.className = 'log-item ' + kind;
        li.innerHTML = html + (undoData
            ? ' <button type="button" class="undo-btn" onclick="undoAssignment(this, ' + undoData.studentId + ', \'' + escapeAttr(undoData.cardUid) + '\', \'' + escapeAttr(undoData.name) + '\', \'' + escapeAttr(undoData.number) + '\', \'' + escapeAttr(undoData.program) + '\', \'' + escapeAttr(undoData.level || '') + '\')">Undo</button>'
            : '');
        log.prepend(li);
    }

    function escapeAttr(s) {
        const A = '&' + 'amp;', L = '&' + 'lt;', G = '&' + 'gt;', Q = '&' + '#39;';
        return String(s ?? '').replace(/&/g, A).replace(/</g, L).replace(/>/g, G).replace(/'/g, Q).replace(/"/g, '&' + 'quot;');
    }

    async function assignCard(cardUid) {
        if (busy) return;
        const target = currentTarget();
        if (!target || target.style.display === 'none') {
            logEntry('err', '<span class="log-msg">No student matches the current search or level — clear the search to continue.</span>');
            return;
        }
        busy = true;
        const payload = { student_id: target.dataset.id, card_uid: cardUid };

        try {
            const res = await fetch('{{ route('rfid.bulk.assign') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify(payload),
            });
            const data = await res.json();

            if (!res.ok) {
                const msg = data.errors ? Object.values(data.errors)[0][0] : (data.message || 'Assignment failed.');
                logEntry('err', '<span class="log-msg">' + escapeAttr(msg) + '</span>');
                return;
            }

            doneCount++;
            const time = new Date().toTimeString().slice(0, 5);
            const reassigned = data.status === 'reassigned';
            logEntry(reassigned ? 'warn' : 'ok',
                '<span class="rt">' + time + '</span><span class="rid">' + escapeAttr(data.card_uid) + '</span>' +
                '<span class="log-msg">assigned to <b>' + escapeAttr(data.student.full_name) + '</b></span>' +
                (reassigned ? '<span class="stamp warn" style="margin-left:auto;">re-issued</span>' : ''),
                { studentId: data.student.id, cardUid: data.card_uid, name: data.student.full_name, number: data.student.student_number, program: data.student.program, level: data.student.education_level }
            );
            target.remove();
            updateProgress(data.remaining);
            advanceTarget();
        } catch (err) {
            logEntry('err', '<span class="log-msg">Could not reach the server — check your connection and try again.</span>');
        } finally {
            busy = false;
        }
    }

    async function undoAssignment(button, studentId, cardUid, name, number, program, level) {
        if (busy) return;
        busy = true;
        try {
            const res = await fetch('{{ route('rfid.bulk.undo') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                body: JSON.stringify({ student_id: studentId }),
            });
            const data = await res.json();

            if (!res.ok) {
                logEntry('err', '<span class="log-msg">' + escapeAttr(data.message || 'Undo failed.') + '</span>');
                return;
            }

            doneCount = Math.max(0, doneCount - 1);
            button.closest('.log-item').remove();
            const list = document.getElementById('queue');
            const li = document.createElement('li');
            li.className = 'queue-item';
            li.dataset.id = studentId;
            li.dataset.name = name;
            li.dataset.number = number;
            li.dataset.program = program;
            li.setAttribute('onclick', 'selectStudent(this)');
            li.innerHTML = '<span class="avatar-sm">' + escapeAttr((name.replace(',', '')).split(' ').map((w) => w[0]).slice(0, 2).join('')) + '</span>' +
                '<span class="q-main"><b>' + escapeAttr(name) + '</b><span class="q-sub">' + escapeAttr(number) + ' · ' + escapeAttr(program) + '</span></span>';
            list.prepend(li);
            applyQueueFilter();
            updateProgress(data.remaining);
            logEntry('info', '<span class="log-msg">Undone — card ' + escapeAttr(cardUid) + ' released, <b>' + escapeAttr(name) + '</b> back in queue.</span>');
        } catch (err) {
            logEntry('err', '<span class="log-msg">Could not reach the server.</span>');
        } finally {
            busy = false;
        }
    }

    function submitManual() {
        const input = document.getElementById('manual-card');
        const value = input.value.trim();
        if (value.length > 0) {
            assignCard(value.toUpperCase());
            input.value = '';
        }
    }

    document.getElementById('manual-card').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); submitManual(); }
    });

    // Keyboard-wedge capture: rapid burst of characters ending with Enter.
    let buffer = '', lastKey = 0;
    document.addEventListener('keydown', (e) => {
        if (document.activeElement && document.activeElement.tagName === 'INPUT') return;
        const now = Date.now();
        if (now - lastKey > 300) buffer = '';
        lastKey = now;
        if (e.key === 'Enter') {
            if (buffer.length >= 4) {
                assignCard(buffer.toUpperCase());
                document.getElementById('wedge-txt').textContent = 'READY';
            }
            buffer = '';
            return;
        }
        if (/^[0-9a-zA-Z-]$/.test(e.key)) {
            buffer += e.key;
            document.getElementById('wedge-txt').textContent = buffer.toUpperCase();
        }
    });

    updateProgress();
    applyQueueFilter();
</script>
@endpush
