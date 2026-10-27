<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentRfid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StudentController extends Controller
{
    /**
     * Student registry with search and filters — College students only.
     */
    public function index(Request $request): View
    {
        $students = Student::query()
            ->where('education_level', 'College')
            ->with('activeRfid')
            ->withCount('offenses')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('middle_name', 'like', $term)
                        ->orWhere('student_number', 'like', $term)
                        ->orWhereHas('rfids', fn ($rfid) => $rfid->where('card_uid', 'like', $term));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('enrollment_status', $request->string('status')))
            ->when($request->boolean('no_rfid'), fn ($query) => $query->whereDoesntHave('rfids', fn ($rfid) => $rfid->where('is_active', true)))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        return view('students.index', [
            'students' => $students,
            'enrolledCount' => Student::where('education_level', 'College')->where('enrollment_status', 'enrolled')->count(),
            'cardCount' => StudentRfid::activeForCollege()->count(),
        ]);
    }

    /**
     * Show the enrollment form.
     */
    public function create(): View
    {
        return view('students.create', [
            'suggestedNumber' => $this->nextStudentNumber(),
        ]);
    }

    /**
     * Store a new student, optionally assigning an RFID card.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateStudent($request);

        $student = DB::transaction(function () use ($request, $validated) {
            $student = Student::create($validated + ['created_by' => $request->user()->id]);

            if ($request->filled('card_uid')) {
                StudentRfid::assignTo($student, $request->string('card_uid')->upper(), $request->user()->id);
            }

            return $student;
        });

        return redirect()
            ->route('students.show', $student)
            ->with('success', $student->full_name.' was enrolled.');
    }

    /**
     * Student case file.
     */
    public function show(Student $student): View
    {
        $student->load(['activeRfid.assignedBy', 'offenses.offenseType', 'offenses.actions.recordedBy']);

        $tallies = ['minor' => 0, 'major' => 0, 'critical' => 0];
        foreach ($student->offenses as $offense) {
            $severity = $offense->offenseType?->severity;
            if ($severity !== null && array_key_exists($severity, $tallies)) {
                $tallies[$severity]++;
            }
        }

        return view('students.profile', [
            'student' => $student,
            'tallies' => $tallies,
        ]);
    }

    /**
     * Show the edit form.
     */
    public function edit(Student $student): View
    {
        $student->load('activeRfid');

        return view('students.edit', ['student' => $student]);
    }

    /**
     * Update a student record.
     */
    public function update(Request $request, Student $student): RedirectResponse
    {
        $validated = $this->validateStudent($request, $student->id);

        $student->update($validated + ['updated_by' => $request->user()->id]);

        return redirect()
            ->route('students.show', $student)
            ->with('success', $student->full_name.' was updated.');
    }

    /**
     * Delete a student — only allowed when the record has no cases.
     */
    public function destroy(Student $student): RedirectResponse
    {
        if ($student->offenses()->exists()) {
            return back()->with('error', $student->full_name.' has cases on the ledger. Mark the record withdrawn instead of deleting it.');
        }

        $student->rfids()->delete();
        $student->delete();

        return redirect()->route('students.index')->with('success', 'Student record deleted.');
    }

    /**
     * JSON search for pickers (offense form, scanner assignment) — College students only.
     */
    public function search(Request $request): JsonResponse
    {
        $term = '%'.$request->string('q')->trim().'%';

        $students = Student::query()
            ->where('education_level', 'College')
            ->when(mb_strlen($request->string('q')->trim()) > 0, function ($query) use ($term) {
                $query->where(function ($inner) use ($term) {
                    $inner->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('student_number', 'like', $term);
                });
            })
            ->orderBy('last_name')
            ->limit(10)
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'course_or_grade', 'year_level', 'section', 'enrollment_status']);

        return response()->json([
            'results' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'program' => $student->program_label,
                'enrollment_status' => $student->enrollment_status,
            ]),
        ]);
    }

    /**
     * JSON lookup by scanned card UID or typed ID number — used by the RFID scanner page.
     */
    public function findByRfid(string $rfid): JsonResponse
    {
        $rfid = mb_strtoupper(trim($rfid));

        $card = StudentRfid::activeForCard($rfid);
        $student = $card?->student;

        // Manual entry on the scanner page uses the visible ID number, not the card UID.
        if ($student === null) {
            $student = Student::query()->where('student_number', $rfid)->first();
        }

        if ($student === null) {
            return response()->json(['exists' => false, 'card_uid' => $rfid]);
        }

        $cardUid = $card?->card_uid
            ?? StudentRfid::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->value('card_uid');

        return response()->json([
            'exists' => true,
            'card_uid' => $cardUid,
            'student' => [
                'id' => $student->id,
                'student_number' => $student->student_number,
                'full_name' => $student->full_name,
                'program' => $student->program_label,
                'enrollment_status' => $student->enrollment_status,
                'avatar' => $student->avatarUrl(),
                'cases' => $student->offenses()->count(),
                'last_case_at' => optional($student->offenses()->first())->incident_at?->toISOString(),
            ],
        ]);
    }

    /**
     * Assign (or reassign) an RFID card to a student.
     */
    public function assignRfid(Request $request, Student $student): RedirectResponse
    {
        $request->validate([
            'card_uid' => ['required', 'string', 'max:100'],
        ]);

        $status = StudentRfid::assignTo($student, $request->string('card_uid')->upper(), $request->user()->id);

        $message = match ($status) {
            StudentRfid::ASSIGN_UNCHANGED => 'Card '.$request->string('card_uid')->upper().' is already assigned to '.$student->full_name.'.',
            StudentRfid::ASSIGN_REASSIGNED => 'Retired card '.$request->string('card_uid')->upper().' re-issued to '.$student->full_name.'.',
            default => 'Card '.$request->string('card_uid')->upper().' assigned to '.$student->full_name.'.',
        };

        return back()->with($status === StudentRfid::ASSIGN_UNCHANGED ? 'error' : 'success', $message);
    }

    /**
     * Deactivate a student's active card.
     */
    public function unassignRfid(Request $request, Student $student): RedirectResponse
    {
        $updated = StudentRfid::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->update(['is_active' => false, 'deactivated_at' => now()]);

        if ($updated === 0) {
            return back()->with('error', $student->full_name.' has no active card.');
        }

        return back()->with('success', 'Card unassigned from '.$student->full_name.'.');
    }

    /**
     * Shared student validation rules.
     *
     * @return array<string, mixed>
     */
    private function validateStudent(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'student_number' => ['required', 'string', 'max:50', Rule::unique('students', 'student_number')->ignore($ignoreId)],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'education_level' => ['required', 'string', 'max:50'],
            'course_or_grade' => ['nullable', 'string', 'max:100'],
            'year_level' => ['nullable', 'string', 'max:50'],
            'section' => ['nullable', 'string', 'max:100'],
            'enrollment_status' => ['required', Rule::in(['enrolled', 'pending_verification', 'inactive', 'graduated', 'withdrawn'])],
        ]);
    }

    /**
     * Printable case file for a student.
     */
    public function report(Request $request, Student $student): View
    {
        $student->load(['offenses.offenseType', 'offenses.actions', 'offenses.reportedBy', 'activeRfid']);

        $tallies = ['minor' => 0, 'major' => 0, 'critical' => 0];
        foreach ($student->offenses as $offense) {
            $severity = $offense->offenseType?->severity;
            if ($severity !== null && array_key_exists($severity, $tallies)) {
                $tallies[$severity]++;
            }
        }

        return view('students.report', [
            'student' => $student,
            'tallies' => $tallies,
            'generatedBy' => $request->user(),
        ]);
    }

    /**
     * Suggest the next student number for the current school year.
     */
    private function nextStudentNumber(): string
    {
        $year = now()->format('Y');
        $latest = Student::query()->where('student_number', 'like', $year.'-%')->orderByDesc('student_number')->value('student_number');
        $next = $latest === null ? 1 : ((int) mb_substr($latest, 5)) + 1;

        return $year.'-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
