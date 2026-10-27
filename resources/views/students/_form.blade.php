@php($statusOptions = ['enrolled' => 'Enrolled', 'pending_verification' => 'Pending verification', 'inactive' => 'Inactive', 'graduated' => 'Graduated', 'withdrawn' => 'Withdrawn'])

<section class="card" style="margin-bottom: 18px;">
    <div class="card-head"><h2>Personal information</h2></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="field">
                <label for="first_name">First name <span class="req">*</span></label>
                <input class="input @error('first_name') invalid @enderror" id="first_name" name="first_name" value="{{ old('first_name', $student->first_name ?? '') }}" required>
                @error('first_name')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="middle_name">Middle name</label>
                <input class="input" id="middle_name" name="middle_name" value="{{ old('middle_name', $student->middle_name ?? '') }}">
            </div>
            <div class="field">
                <label for="last_name">Last name <span class="req">*</span></label>
                <input class="input @error('last_name') invalid @enderror" id="last_name" name="last_name" value="{{ old('last_name', $student->last_name ?? '') }}" required>
                @error('last_name')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="suffix">Suffix</label>
                <input class="input" id="suffix" name="suffix" value="{{ old('suffix', $student->suffix ?? '') }}" placeholder="Jr., III — optional">
            </div>
            <div class="field span-2">
                <label>Gender <span class="req">*</span></label>
                <div class="status-picker" style="grid-template-columns: repeat(2, 240px);">
                    <div class="status-opt">
                        <input type="radio" name="gender" id="g-female" value="female" @checked(old('gender', $student->gender ?? '') === 'female') required>
                        <label for="g-female">Female</label>
                    </div>
                    <div class="status-opt">
                        <input type="radio" name="gender" id="g-male" value="male" @checked(old('gender', $student->gender ?? '') === 'male') required>
                        <label for="g-male">Male</label>
                    </div>
                </div>
                @error('gender')<p class="err">{{ $message }}</p>@enderror
                <p class="help">Sets the default ID photo shown when no picture is uploaded.</p>
            </div>
            <div class="field span-2">
                <label for="student_number">Student number <span class="req">*</span></label>
                <input class="input mono @error('student_number') invalid @enderror" id="student_number" name="student_number" value="{{ old('student_number', $student->student_number ?? $suggestedNumber ?? '') }}" required>
                @error('student_number')<p class="err">{{ $message }}</p>@enderror
                @if (!isset($student))
                    <p class="help">Auto-suggested from the current school year. Overwrite only when migrating records.</p>
                @endif
            </div>
        </div>
    </div>
</section>

<section class="card" style="margin-bottom: 18px;">
    <div class="card-head"><h2>Academic details</h2></div>
    <div class="card-body">
        <div class="form-grid">
            <div class="field">
                <label for="education_level">Education level <span class="req">*</span></label>
                <select class="select @error('education_level') invalid @enderror" id="education_level" name="education_level" required>
                    @php($levels = collect(['College']))
                    @if (isset($student) && $student->education_level && $student->education_level !== 'College')
                        @php($levels->push($student->education_level))
                    @endif
                    @foreach ($levels as $level)
                        <option value="{{ $level }}" @selected(old('education_level', $student->education_level ?? '') === $level)>{{ $level }}</option>
                    @endforeach
                </select>
                @error('education_level')<p class="err">{{ $message }}</p>@enderror
            </div>
            <div class="field">
                <label for="course_or_grade">Course / Strand / Grade</label>
                <input class="input" id="course_or_grade" name="course_or_grade" value="{{ old('course_or_grade', $student->course_or_grade ?? '') }}" placeholder="e.g. STEM, BSIT, Grade 7">
            </div>
            <div class="field">
                <label for="year_level">Year level / Grade</label>
                <input class="input" id="year_level" name="year_level" value="{{ old('year_level', $student->year_level ?? '') }}" placeholder="e.g. 11">
            </div>
            <div class="field">
                <label for="section">Section</label>
                <input class="input" id="section" name="section" value="{{ old('section', $student->section ?? '') }}" placeholder="e.g. STEM-A">
            </div>
        </div>

        <fieldset style="margin-top: 6px;">
            <legend>Enrollment status</legend>
            <p class="fieldset-sub">Drives whether the student appears in gate scanning and reports.</p>
            <div class="status-picker">
                @foreach ($statusOptions as $value => $label)
                    <div class="status-opt">
                        <input type="radio" name="enrollment_status" id="st-{{ $value }}" value="{{ $value }}" @checked(old('enrollment_status', $student->enrollment_status ?? 'enrolled') === $value) required>
                        <label for="st-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
            @error('enrollment_status')<p class="err">{{ $message }}</p>@enderror
        </fieldset>
    </div>
</section>

<section class="card" style="margin-bottom: 0;">
    <div class="card-head">
        <h2>RFID card</h2>
        <span class="meta">@if (isset($student) && $student->activeRfid) currently assigned @else optional — can be assigned later @endif</span>
    </div>
    <div class="card-body">
        @if (isset($student) && $student->activeRfid)
            <div class="rfid-strip">
                <div class="ph">CARD</div>
                <div>
                    <div class="rid">{{ $student->activeRfid->card_uid }}</div>
                    <div class="rsub">Assigned {{ $student->activeRfid->assigned_at?->format('M j, Y') }} by {{ $student->activeRfid->assignedBy?->name ?? '—' }}</div>
                </div>
                <div style="margin-left:auto; display:flex; gap:8px;">
                    <a class="btn btn-ghost btn-sm" href="{{ route('scan', ['card' => $student->activeRfid->card_uid]) }}">Test at scanner</a>
                </div>
            </div>
            <p class="help" style="margin-top:10px;">To replace this card, enter the new card's number below — the old one is retired automatically.</p>
        @endif
        <div class="field" style="margin-top: 12px; margin-bottom: 0;">
            <label for="card_uid">Card number</label>
            <input class="input mono @error('card_uid') invalid @enderror" id="card_uid" name="card_uid" value="{{ old('card_uid') }}" placeholder="Tap the card on the reader, or type the code">
            @error('card_uid')<p class="err">{{ $message }}</p>@enderror
            <p class="help">Tap the card on the USB reader while this field is focused, or type the code printed on the back. Each card belongs to one student.</p>
        </div>
    </div>
</section>
