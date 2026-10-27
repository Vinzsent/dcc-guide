@php($severities = ['minor' => 'Minor', 'major' => 'Major', 'critical' => 'Critical'])
@php($actions = ['' => 'None yet — to be decided', 'counseling' => 'Counseling session', 'reprimand' => 'Written reprimand', 'open_reprimand' => 'Open reprimand', 'parent_conference' => 'Parent conference', 'community_service' => 'Community service', 'hearing' => 'Disciplinary hearing'])

<section class="card" style="margin-bottom: 18px;">
    <div class="card-head"><h2>Student</h2>@if (isset($student))<span class="meta">pre-selected</span>@endif</div>
    <div class="card-body">
        @isset($student)
            <div class="student-pick">
                <img class="avatar-photo" src="{{ $student->avatarUrl() }}" alt="Photo of {{ $student->full_name }}" width="44" height="52">
                <div>
                    <b>{{ $student->full_name }}</b>
                    <span>{{ $student->student_number }} · {{ $student->program_label }}</span>
                </div>
                <a class="btn btn-ghost btn-sm chg" href="{{ route('offenses.create') }}">Change student</a>
            </div>
            <input type="hidden" name="student_id" value="{{ $student->id }}">
        @else
            <div class="field" style="margin-bottom: 0;">
                <label for="student-picker">Search the registry <span class="req">*</span></label>
                <div class="typeahead">
                    <input class="input @error('student_id') invalid @enderror" id="student-picker" autocomplete="off"
                           placeholder="Type a name or student number…"
                           value="{{ old('student_display', isset($student) ? $student->full_name : '') }}">
                    <div class="ta-menu" id="ta-menu" hidden></div>
                </div>
                <input type="hidden" name="student_id" id="student-id" value="{{ old('student_id', $student->id ?? '') }}">
                <p class="help">Came from the RFID scanner? The student is pre-selected on this page.</p>
                @error('student_id')<p class="err">{{ $message }}</p>@enderror
            </div>
        @endisset
    </div>
</section>

<section class="card" style="margin-bottom: 18px;">
    <div class="card-head"><h2>Offense type</h2><span class="meta" id="type-count"></span></div>
    <div class="card-body">
        <div class="toolbar" style="margin-bottom: 12px;">
            <div class="search">
                <svg viewBox="0 0 16 16" fill="none"><circle cx="7" cy="7" r="4.4" stroke="currentColor" stroke-width="1.4"/><path d="M10.4 10.4 14 14" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <input class="input" id="type-search" type="search" placeholder="Search offense type…" oninput="filterOffenseTypes()">
            </div>
            <select class="select" id="type-sev" style="width: auto;" onchange="filterOffenseTypes()">
                <option value="">All severities</option>
                <option value="minor">Minor</option>
                <option value="major">Major</option>
                <option value="critical">Critical</option>
            </select>
        </div>
        <div class="typegrid" id="type-grid">
            @foreach ($offenseTypes as $type)
                <div class="typecard" data-name="{{ mb_strtolower($type->name) }}" data-severity="{{ $type->severity }}">
                    <input type="radio" name="offense_type_id" id="ot-{{ $type->id }}" value="{{ $type->id }}" @checked(old('offense_type_id') == $type->id || (isset($offense) && $offense->offense_type_id === $type->id && !old('offense_type_id'))) required>
                    <label for="ot-{{ $type->id }}">
                        <b>{{ $type->name }}</b>
                        @if ($type->severity)
                            <span class="{{ $type->severityStampClass() }}">{{ $severities[$type->severity] }}</span>
                        @else
                            <span class="stamp muted">Unclassified</span>
                        @endif
                    </label>
                </div>
            @endforeach
        </div>
        <p class="err" id="type-empty" hidden>No offense type matches — try a different search or severity.</p>
        @error('offense_type_id')<p class="err">{{ $message }}</p>@enderror
    </div>
</section>

<section class="card" style="margin-bottom: 0;">
    <div class="card-head"><h2>Details</h2></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="field">
                <label for="incident_at">Date & time of incident <span class="req">*</span></label>
                <input class="input @error('incident_at') invalid @enderror" type="datetime-local" id="incident_at" name="incident_at" value="{{ old('incident_at', isset($offense) ? $offense->incident_at->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')) }}" required>
                @error('incident_at')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="location">Location <span class="opt">optional</span></label>
                <input class="input" id="location" name="location" value="{{ old('location', $offense->location ?? '') }}" placeholder="e.g. Main gate, Room 204">
            </div>
            <div class="field span-2">
                <label for="description">Description <span class="req">*</span></label>
                <textarea class="input @error('description') invalid @enderror" id="description" name="description" rows="4" placeholder="What happened, where, and who witnessed it…">{{ old('description', $offense->description ?? '') }}</textarea>
                @error('description')<p class="err">{{ $message }}</p>@enderror
                <p class="help">Stick to facts and direct quotes. This text appears verbatim on the case record.</p>
            </div>
            <div class="field">
                <label for="witnesses">Witnesses <span class="opt">optional</span></label>
                <input class="input" id="witnesses" name="witnesses" value="{{ old('witnesses', $offense->witnesses ?? '') }}" placeholder="Names, comma-separated">
            </div>
            @unless (isset($offense))
                <div class="field">
                    <label for="initial_action">Sanction / follow-up</label>
                    <select class="select" id="initial_action" name="initial_action">
                        @foreach ($actions as $value => $label)
                            <option value="{{ $value }}" @selected(old('initial_action') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="help">Recorded as the case's first follow-up action.</p>
                </div>
            @endunless
        </div>
    </div>
</section>

@push('scripts')
<script>
    function filterOffenseTypes() {
        const q = (document.getElementById('type-search').value || '').trim().toLowerCase();
        const sev = document.getElementById('type-sev').value;
        const cards = document.querySelectorAll('#type-grid .typecard');
        let visible = 0;
        let only = null;

        cards.forEach((card) => {
            const okQ = q === '' || card.dataset.name.includes(q);
            const okS = sev === '' || card.dataset.severity === sev;
            const show = okQ && okS;
            card.style.display = show ? '' : 'none';
            if (show) {
                visible++;
                only = card;
            }
        });

        const count = document.getElementById('type-count');
        if (count) count.textContent = visible + ' of ' + cards.length + ' types';

        const empty = document.getElementById('type-empty');
        if (empty) empty.hidden = visible !== 0;

        // When the search narrows to exactly one type, select it
        if (visible === 1 && only) {
            const input = only.querySelector('input[type="radio"]');
            if (input) input.checked = true;
        }
    }

    filterOffenseTypes();
</script>
@endpush
