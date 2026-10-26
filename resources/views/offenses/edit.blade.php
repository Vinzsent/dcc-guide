@extends('layouts.app')

@section('title', 'Edit case — DCC Care')

@section('crumbs')
    <a href="{{ route('dashboard') }}">Dashboard</a> <span class="sep">/</span> <a href="{{ route('offenses.index') }}">Offense ledger</a> <span class="sep">/</span> <b>{{ $offense->reference_no }}</b>
@endsection

@section('topbar-actions')
    <a class="btn btn-ghost btn-sm" href="{{ route('offenses.report', $offense) }}" target="_blank">Print report</a>
@endsection

@section('content')
    <div class="page-head">
        <div>
            <h1>Edit case {{ $offense->reference_no }}</h1>
            <p class="sub">Filed {{ $offense->recorded_at?->format('M j, Y · g:i a') }} by {{ $offense->reportedBy?->name ?? '—' }}.</p>
        </div>
    </div>

    <form class="formcard" method="POST" action="{{ route('offenses.update', $offense) }}">
        @csrf
        @method('PUT')
        @include('offenses._form', [
            'offenseTypes' => $offenseTypes,
            'student' => $offense->student,
            'offense' => $offense,
        ])

        <div class="field" style="max-width: 360px; margin-top: 18px;">
            <label for="status">Case status</label>
            <select class="select" id="status" name="status">
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(old('status', $offense->status) === $status)>{{ str_replace('_', ' ', ucfirst($status)) }}</option>
                @endforeach
            </select>
        </div>

        <div class="savebar">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-ghost" href="{{ route('students.show', $offense->student) }}">Cancel</a>
        </div>
    </form>

    <form method="POST" action="{{ route('offenses.destroy', $offense) }}"
          onsubmit="return confirm('Remove case {{ $offense->reference_no }} from the ledger? This also removes its follow-ups.');"
          style="max-width:880px; margin-top:14px; padding:14px 16px; border:1px solid var(--red-100); border-radius:var(--r-md); display:flex; align-items:center; gap:14px; background:var(--surface-primary);">
        @csrf
        @method('DELETE')
        <span style="font-size:13px; color:var(--fg-muted);"><b style="color:var(--red-700);">Danger zone.</b> Removing takes the case and its follow-ups off the ledger permanently.</span>
        <button type="submit" class="btn btn-danger btn-sm" style="margin-left:auto;">Remove case</button>
    </form>
@endsection
