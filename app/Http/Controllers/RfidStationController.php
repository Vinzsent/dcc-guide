<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentRfid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RfidStationController extends Controller
{
    /**
     * Bulk card registration station.
     */
    public function show(Request $request): View
    {
        $queue = Student::query()
            ->whereDoesntHave('rfids', fn ($rfid) => $rfid->where('is_active', true))
            ->whereIn('enrollment_status', ['enrolled', 'pending_verification'])
            ->orderBy('education_level')
            ->orderBy('student_number')
            ->get(['id', 'student_number', 'first_name', 'middle_name', 'last_name', 'suffix', 'gender', 'education_level', 'course_or_grade', 'year_level', 'section', 'enrollment_status']);

        $totalWithoutCards = Student::query()
            ->whereDoesntHave('rfids', fn ($rfid) => $rfid->where('is_active', true))
            ->whereIn('enrollment_status', ['enrolled', 'pending_verification'])
            ->count();

        return view('rfid.register', [
            'queue' => $queue,
            'totalWithoutCards' => $totalWithoutCards,
            'cardCount' => StudentRfid::where('is_active', true)->count(),
        ]);
    }

    /**
     * Assign the scanned card to the current queue target.
     */
    public function assign(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')],
            'card_uid' => ['required', 'string', 'max:100'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $cardUid = mb_strtoupper(trim($data['card_uid']));

        $status = StudentRfid::assignTo($student, $cardUid, $request->user()->id);

        return response()->json([
            'ok' => true,
            'status' => $status,
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'student_number' => $student->student_number,
                'program' => $student->program_label,
                'education_level' => $student->education_level,
            ],
            'card_uid' => $cardUid,
            'remaining' => $this->remainingCount(),
        ]);
    }

    /**
     * Undo an assignment made from the station this session.
     */
    public function undo(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', Rule::exists('students', 'id')],
        ]);

        $updated = StudentRfid::query()
            ->where('student_id', $data['student_id'])
            ->where('is_active', true)
            ->update(['is_active' => false, 'deactivated_at' => now()]);

        if ($updated === 0) {
            return response()->json([
                'ok' => false,
                'message' => 'No active card to undo for that student.',
            ], 422);
        }

        $student = Student::query()->findOrFail($data['student_id']);

        return response()->json([
            'ok' => true,
            'student' => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'student_number' => $student->student_number,
                'program' => $student->program_label,
                'education_level' => $student->education_level,
            ],
            'remaining' => $this->remainingCount(),
        ]);
    }

    /**
     * Students still waiting for a card.
     */
    private function remainingCount(): int
    {
        return Student::query()
            ->whereDoesntHave('rfids', fn ($rfid) => $rfid->where('is_active', true))
            ->whereIn('enrollment_status', ['enrolled', 'pending_verification'])
            ->count();
    }
}
