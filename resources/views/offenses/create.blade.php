@extends('layouts.app')

@section('title', 'File an offense — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('offenses.index') }}">Offense ledger</a> <span class="sep">/</span> <b>New case</b>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>File an offense</h1>
            <p class="sub">Opens a numbered case in the ledger, with the student's record linked.</p>
        </div>
    </div>

    <div class="casebanner">
        <span><span class="lbl">Opening case</span><span class="no">{{ $nextReferenceNo }}</span></span>
        <span style="font-size:12px; color: rgba(244,249,245,.6);">{{ now()->format('l, F j, Y') }} · filed by {{ auth()->user()->name }}</span>
    </div>

    <form class="formcard" method="POST" action="{{ route('offenses.store') }}">
        @csrf
        @include('offenses._form', ['offenseTypes' => $offenseTypes, 'student' => $student])

        <div class="savebar">
            <button class="btn btn-primary" type="submit">File case {{ $nextReferenceNo }}</button>
            <a class="btn btn-ghost" href="{{ $student ? route('students.show', $student) : route('offenses.index') }}">Cancel</a>
            <span class="note">Filing returns to the student's profile with the new case on top.</span>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    @unless (isset($student))
    (function () {
        const input = document.getElementById('student-picker');
        const hidden = document.getElementById('student-id');
        const menu = document.getElementById('ta-menu');
        let timer = null;

        input.addEventListener('input', () => {
            hidden.value = '';
            clearTimeout(timer);
            timer = setTimeout(() => {
                fetch('/students/search?q=' + encodeURIComponent(input.value), { headers: { 'Accept': 'application/json' } })
                    .then((r) => r.json())
                    .then((data) => {
                        menu.innerHTML = '';
                        if (data.results.length === 0) {
                            menu.innerHTML = '<span class="ta-item" style="cursor:default; color:var(--fg-muted);">No students found</span>';
                        }
                        data.results.forEach((s) => {
                            const b = document.createElement('button');
                            b.type = 'button';
                            b.className = 'ta-item';
                            b.innerHTML = '<b>' + s.full_name + '</b> <span class="mono">' + s.student_number + '</span><br><span style="font-size:12px; color:var(--fg-muted);">' + s.program + '</span>';
                            b.addEventListener('click', () => {
                                hidden.value = s.id;
                                input.value = s.full_name;
                                menu.hidden = true;
                            });
                            menu.appendChild(b);
                        });
                        menu.hidden = false;
                    });
            }, 200);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.typeahead')) menu.hidden = true;
        });
    })();
    @endunless
</script>
@endpush
