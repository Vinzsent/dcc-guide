<?php

namespace App\Http\Controllers;

use App\Models\OffenseAction;
use App\Models\OffenseType;
use App\Models\Student;
use App\Models\StudentOffense;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class OffenseController extends Controller
{
    /**
     * The offense ledger.
     */
    public function index(Request $request): View
    {
        $offenses = $this->filteredOffenses($request)
            ->with(['student', 'offenseType'])
            ->orderByDesc('incident_at')
            ->paginate(10)
            ->withQueryString();

        return view('offenses.index', [
            'offenses' => $offenses,
            'monthCount' => StudentOffense::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'statuses' => StudentOffense::STATUSES,
            'years' => StudentOffense::query()
                ->select('incident_at')
                ->get()
                ->map(fn ($offense) => $offense->incident_at->year)
                ->unique()
                ->sortDesc()
                ->values(),
        ]);
    }

    /**
     * Download the (filtered) ledger as CSV.
     */
    public function export(Request $request): Response
    {
        $offenses = $this->filteredOffenses($request)
            ->with(['student', 'offenseType', 'reportedBy'])
            ->orderByDesc('incident_at')
            ->cursor();

        // php://temp keeps fputcsv's escaping, buffers in memory (spilling to
        // disk past 2 MB), and lets us send a normal downloadable response
        $tmp = fopen('php://temp', 'r+');

        // UTF-8 BOM so Excel renders names like "Muñoz" correctly
        fwrite($tmp, "\xEF\xBB\xBF");

        fputcsv($tmp, [
            'Case No.', 'Student No.', 'Student', 'Program / Year', 'Offense',
            'Severity', 'Status', 'Incident At', 'Location', 'Witnesses',
            'Description', 'Reported By', 'Recorded At',
        ]);

        foreach ($offenses as $offense) {
            fputcsv($tmp, [
                $offense->reference_no,
                $offense->student->student_number,
                $offense->student->full_name,
                $offense->student->program_label,
                $offense->offenseType?->name ?? 'Other',
                $offense->offenseType?->severity ? ucfirst($offense->offenseType->severity) : 'Other',
                ucfirst($offense->statusLabel()),
                $offense->incident_at->format('Y-m-d H:i'),
                $offense->location,
                $offense->witnesses,
                $offense->description,
                $offense->reportedBy?->name,
                $offense->recorded_at?->format('Y-m-d H:i'),
            ]);
        }

        rewind($tmp);
        $contents = stream_get_contents($tmp);
        fclose($tmp);

        return response($contents, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="offense-ledger-'.now()->format('Y-m-d').'.csv"',
        ]);
    }

    /**
     * Ledger query with the search/filter terms from the request,
     * shared by the index page and the CSV export.
     */
    private function filteredOffenses(Request $request): Builder
    {
        return StudentOffense::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('reference_no', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('student', function ($student) use ($term) {
                            $student->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('student_number', 'like', $term);
                        });
                });
            })
            ->when($request->filled('severity'), fn ($query) => $query->whereHas('offenseType', fn ($type) => $type->where('severity', $request->string('severity'))))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('year'), fn ($query) => $query->whereYear('incident_at', $request->integer('year')));
    }

    /**
     * Show the file-an-offense form.
     */
    public function create(?Student $student = null): View
    {
        return view('offenses.create', [
            'student' => $student,
            'offenseTypes' => OffenseType::where('is_active', true)->orderBy('name')->get(),
            'nextReferenceNo' => StudentOffense::nextReferenceNo(),
        ]);
    }

    /**
     * Open a new case in the ledger.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateOffense($request);

        $offense = DB::transaction(function () use ($request, $validated) {
            $offense = StudentOffense::create($validated + [
                'reference_no' => StudentOffense::nextReferenceNo(),
                'reported_by' => $request->user()->id,
                'recorded_at' => now(),
                'status' => 'recorded',
            ]);

            if ($request->filled('initial_action')) {
                OffenseAction::create([
                    'offense_id' => $offense->id,
                    'action_type' => $request->string('initial_action'),
                    'notes' => $request->string('action_notes')->trim() ?: null,
                    'recorded_by' => $request->user()->id,
                ]);
            }

            $this->logAudit($request, 'offense.recorded', $offense->id, [
                'reference_no' => $offense->reference_no,
                'offense_type_id' => $offense->offense_type_id,
            ]);

            return $offense;
        });

        return redirect()
            ->route('students.show', $offense->student_id)
            ->with('success', 'Case '.$offense->reference_no.' was filed.');
    }

    /**
     * Show the edit form for a case.
     */
    public function edit(StudentOffense $offense): View
    {
        $offense->load(['student', 'offenseType', 'actions.recordedBy']);

        return view('offenses.edit', [
            'offense' => $offense,
            'offenseTypes' => OffenseType::where('is_active', true)->orderBy('name')->get(),
            'statuses' => StudentOffense::STATUSES,
        ]);
    }

    /**
     * Update a case.
     */
    public function update(Request $request, StudentOffense $offense): RedirectResponse
    {
        $validated = $this->validateOffense($request, $offense, withStatus: true);

        $offense->update($validated);

        $this->logAudit($request, 'offense.updated', $offense->id, [
            'status' => $offense->status,
        ]);

        return redirect()
            ->route('offenses.index')
            ->with('success', 'Case '.$offense->reference_no.' was updated.');
    }

    /**
     * Remove a case and its follow-up actions.
     */
    public function destroy(Request $request, StudentOffense $offense): RedirectResponse
    {
        DB::transaction(function () use ($request, $offense) {
            // Audit before the delete — the log survives with its FK nulled out.
            $this->logAudit($request, 'offense.removed', $offense->id, [
                'reference_no' => $offense->reference_no,
            ]);

            $offense->actions()->delete();
            $offense->delete();
        });

        return redirect()->route('offenses.index')->with('success', 'Case '.$offense->reference_no.' was removed from the ledger.');
    }

    /**
     * Append an entry to the discipline audit log.
     *
     * @param  array<string, mixed>  $details
     */
    private function logAudit(Request $request, string $action, int $offenseId, array $details = []): void
    {
        DB::table('offense_audit_logs')->insert([
            'user_id' => $request->user()->id,
            'offense_id' => $offenseId,
            'action' => $action,
            'entity_type' => 'student_offense',
            'entity_id' => $offenseId,
            'details' => json_encode($details),
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500) ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * Printable case report for a single case.
     */
    public function report(Request $request, StudentOffense $offense): View
    {
        $offense->load(['student', 'offenseType', 'reportedBy', 'actions.recordedBy']);

        return view('offenses.report', [
            'offense' => $offense,
            'generatedBy' => $request->user(),
        ]);
    }

    /**
     * Shared offense validation rules.
     *
     * @return array<string, mixed>
     */
    private function validateOffense(Request $request, ?StudentOffense $offense = null, bool $withStatus = false): array
    {
        $rules = [
            'student_id' => ['required', Rule::exists('students', 'id')],
            'offense_type_id' => ['required', Rule::exists('offense_types', 'id')->where('is_active', true)],
            'incident_at' => ['required', 'date', 'before_or_equal:now'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'witnesses' => ['nullable', 'string', 'max:500'],
        ];

        if ($withStatus) {
            $rules['status'] = ['required', Rule::in(StudentOffense::STATUSES)];
        }

        return $request->validate($rules);
    }
}
