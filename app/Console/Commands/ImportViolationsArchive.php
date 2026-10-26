<?php

namespace App\Console\Commands;

use App\Models\OffenseType;
use App\Models\Student;
use App\Models\StudentOffense;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ImportViolationsArchive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'violations:import-archive
                            {--path= : Directory containing per-school-year CSV files}
                            {--force : Replace previously imported archive cases (ARC-*)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import historical student violations (per-school-year CSV exports) into the offense ledger as archived cases';

    /**
     * Category letters from the source files mapped to severity.
     */
    private const SEVERITY_BY_CATEGORY = ['A' => 'minor', 'B' => 'major', 'C' => 'critical'];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $path = $this->option('path') ?: storage_path('app/imports/violations');

        if (! is_dir($path)) {
            $this->error("CSV directory not found: {$path}");

            return self::FAILURE;
        }

        $files = collect(glob(rtrim($path, '/\\').'/*.csv'))->sort()->values();

        if ($files->isEmpty()) {
            $this->error("No CSV files found in {$path}");

            return self::FAILURE;
        }

        $alreadyImported = StudentOffense::query()->where('reference_no', 'like', 'ARC-%')->exists();

        if ($alreadyImported && ! $this->option('force')) {
            $this->error('Archive cases (ARC-*) are already imported. Use --force to replace them.');

            return self::FAILURE;
        }

        $adminId = (int) (DB::table('users')->where('is_active', 1)->orderBy('id')->value('id') ?? 0);

        // Read every CSV into memory (historical archives are small)
        $rows = [];
        foreach ($files as $file) {
            $sy = basename($file, '.csv');
            $startYear = (int) substr($sy, 0, 4);
            $handle = fopen($file, 'r');
            fgetcsv($handle); // header
            $lastName = '';

            while (($row = fgetcsv($handle)) !== false) {
                $cells = array_pad(array_map(fn ($v) => trim((string) $v), $row), 8, '');
                [$syValue, $section, $name, $dateRaw, $violation, $category, $offense, $remarks] = $cells;

                if ($violation === '') {
                    continue;
                }

                $studentName = $name !== '' ? $name : $lastName;
                if ($studentName === '') {
                    $this->warn('  skipped a row without any student name in '.$sy);

                    continue;
                }
                $lastName = $studentName;

                $rows[] = [
                    'sy' => $sy,
                    'startYear' => $startYear,
                    'section' => $section,
                    'name' => $studentName,
                    'dateRaw' => $dateRaw,
                    'violation' => $violation,
                    'category' => strtoupper($category),
                    'offense' => $offense,
                    'remarks' => $remarks,
                ];
            }

            fclose($handle);
        }

        $this->info('Found '.count($rows).' historical violation rows.');

        $stats = DB::transaction(function () use ($rows, $adminId) {
            if ($this->option('force')) {
                $existing = StudentOffense::query()->where('reference_no', 'like', 'ARC-%')->pluck('id');
                DB::table('offense_actions')->whereIn('offense_id', $existing)->delete();
                StudentOffense::query()->whereIn('id', $existing)->delete();

                $archiveStudents = Student::query()->where('student_number', 'like', 'ARC-%')->pluck('id');
                DB::table('student_rfid')->whereIn('student_id', $archiveStudents)->delete();
                Student::query()->whereIn('id', $archiveStudents)->delete();

                $this->info('Removed '.$existing->count().' previously imported cases and '.$archiveStudents->count().' archive students.');
            }

            // --- Pass 1: violation types from the distinct violation names ---
            $categoryCounts = [];
            foreach ($rows as $row) {
                $key = $this->violationKey($row['violation']);
                $categoryCounts[$key] = $categoryCounts[$key] ?? [];
                $severity = self::SEVERITY_BY_CATEGORY[$row['category']] ?? null;

                if ($severity !== null) {
                    $categoryCounts[$key][$severity] = ($categoryCounts[$key][$severity] ?? 0) + 1;
                }
            }

            $existingTypes = OffenseType::query()
                ->get()
                ->mapWithKeys(fn (OffenseType $type) => [$this->violationKey($type->name) => $type]);

            $typesByKey = [];
            $typesCreated = 0;
            foreach ($categoryCounts as $key => $severities) {
                if (isset($existingTypes[$key])) {
                    $typesByKey[$key] = $existingTypes[$key];

                    continue;
                }

                $severity = 'minor';
                $best = 0;
                foreach ($severities as $candidate => $count) {
                    if ($count > $best) {
                        $best = $count;
                        $severity = $candidate;
                    }
                }

                $displayName = $key;
                foreach ($rows as $row) {
                    if ($this->violationKey($row['violation']) === $key) {
                        $displayName = $row['violation'];
                        break;
                    }
                }

                $typesByKey[$key] = OffenseType::create([
                    'name' => $displayName,
                    'severity' => $severity,
                    'is_active' => true,
                    'created_by' => $adminId,
                ]);
                $typesCreated++;
            }

            // --- Registry name index for matching ---
            $registryKeys = [];
            foreach (Student::query()->get(['id', 'first_name', 'middle_name', 'last_name']) as $student) {
                foreach ($this->nameVariants($student->first_name.' '.$student->middle_name.' '.$student->last_name) as $variant) {
                    $registryKeys[$variant][] = $student->id;
                }
            }

            // --- Pass 2: create students and cases ---
            $caseSeq = [];
            $archSeq = [];
            $stats = ['cases' => 0, 'matched' => 0, 'archived' => 0, 'skipped' => 0];
            $lastNameBySy = [];

            foreach ($rows as $row) {
                $studentName = $row['name'] !== '' ? $row['name'] : ($lastNameBySy[$row['sy']] ?? '');
                if ($studentName === '') {
                    $stats['skipped']++;

                    continue;
                }
                $lastNameBySy[$row['sy']] = $studentName;

                // Resolve or archive the student
                $studentId = null;
                foreach ($this->nameVariants($studentName) as $variant) {
                    if (isset($registryKeys[$variant])) {
                        $studentId = $registryKeys[$variant][0];
                        break;
                    }
                }

                if ($studentId !== null) {
                    $stats['matched']++;
                } else {
                    $seq = ($archSeq[$row['startYear']] ?? 0) + 1;
                    $archSeq[$row['startYear']] = $seq;

                    [$first, $last] = $this->splitArchiveName($studentName);
                    $archive = Student::create([
                        'student_number' => sprintf('ARC-%s-%04d', $row['startYear'], $seq),
                        'first_name' => $first,
                        'last_name' => $last,
                        'gender' => null,
                        'education_level' => 'College',
                        'course_or_grade' => $row['section'] === 'UNNAMED PERSON/PROGRAM' ? null : $this->titleCase($row['section']),
                        'enrollment_status' => 'inactive',
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]);
                    $registryKeys = array_merge_recursive(
                        $registryKeys,
                        collect($this->nameVariants($studentName))
                            ->mapWithKeys(fn ($variant) => [$variant => [$archive->id]])
                            ->all()
                    );
                    $registryKeys[$this->normalizeName($studentName)][] = $archive->id;
                    $registryKeys = array_map('array_unique', $registryKeys);

                    $studentId = $archive->id;
                    $stats['archived']++;
                }

                // Reference number
                $seq = ($caseSeq[$row['startYear']] ?? 0) + 1;
                do {
                    $referenceNo = sprintf('ARC-%s-%04d', $row['startYear'], $seq);
                    $taken = StudentOffense::query()->where('reference_no', $referenceNo)->exists();
                    if ($taken) {
                        $seq++;
                    }
                } while ($taken);
                $caseSeq[$row['startYear']] = $seq;

                // Date: real when the sheet recorded one, else the school year's start
                $incidentAt = Carbon::create($row['startYear'], 8, 1, 8, 0);
                if (preg_match('/^(\d{5})(\.\d+)?$/', $row['dateRaw'], $m)) {
                    $incidentAt = Carbon::instance((new DateTime('1899-12-30'))->modify("+{$m[1]} days"))->setTime(8, 0);
                }

                $description = $row['offense'] !== '' ? $row['offense'] : $row['violation'];
                if ($row['remarks'] !== '') {
                    $description .= "\n\nRemarks: ".$row['remarks'];
                }

                $status = 'closed';
                if (preg_match('/pending|not yet/i', $row['remarks'])) {
                    $status = 'pending_review';
                }

                $type = $typesByKey[$this->violationKey($row['violation'])];

                $case = StudentOffense::create([
                    'reference_no' => $referenceNo,
                    'student_id' => $studentId,
                    'offense_type_id' => $type->id,
                    'incident_at' => $incidentAt,
                    'location' => $row['section'] === 'UNNAMED PERSON/PROGRAM' ? null : $this->titleCase($row['section']),
                    'description' => $description,
                    'witnesses' => null,
                    'status' => $status,
                    'reported_by' => $adminId,
                    'recorded_at' => $incidentAt,
                    'created_at' => $incidentAt,
                    'updated_at' => $incidentAt,
                ]);

                DB::table('offense_audit_logs')->insert([
                    'user_id' => $adminId,
                    'offense_id' => $case->id,
                    'action' => 'offense.imported',
                    'entity_type' => 'student_offense',
                    'entity_id' => $case->id,
                    'details' => json_encode([
                        'reference_no' => $referenceNo,
                        'school_year' => $row['sy'],
                        'source' => 'historical archive import',
                    ]),
                    'created_at' => now(),
                ]);

                $stats['cases']++;
            }

            return $stats;
        });

        $this->info("Imported {$stats['cases']} cases.");
        $this->info("Students matched to the registry: {$stats['matched']} · archived as inactive: {$stats['archived']} · skipped: {$stats['skipped']}");

        $this->warn('Tip: run "php artisan students:infer-gender" to assign genders to newly archived students.');

        return self::SUCCESS;
    }

    /**
     * Uppercase, whitespace-collapsed comparison key.
     */
    private function normalizeName(string $name): string
    {
        $name = preg_replace('/\s+/', ' ', mb_strtoupper(trim($name)));

        return trim((string) $name, ' ,.');
    }

    /**
     * Match variants for a name: as-is, and without middle initials
     * (single letters or two-letter dotted tokens).
     *
     * @return array<int, string>
     */
    private function nameVariants(string $name): array
    {
        $full = $this->normalizeName($name);
        $tokens = explode(' ', $full);
        $noInitials = [];

        foreach ($tokens as $token) {
            $stripped = rtrim($token, '.');
            $isInitial = mb_strlen($stripped) === 1 || (mb_strlen($token) === 2 && str_ends_with($token, '.'));
            if (! $isInitial) {
                $noInitials[] = $token;
            }
        }

        $variants = array_values(array_unique([$full, implode(' ', $noInitials)]));

        return array_filter($variants, fn ($v) => $v !== '');
    }

    /**
     * Split an archived name: the last token becomes the surname, the
     * rest fold into the first name.
     *
     * @return array{0: string, 1: string}
     */
    private function splitArchiveName(string $name): array
    {
        $proper = ucwords(mb_strtolower(preg_replace('/\s+/', ' ', trim($name))));
        $tokens = explode(' ', $proper);

        if (count($tokens) === 1) {
            return ['', $tokens[0]];
        }

        $last = array_pop($tokens);

        return [implode(' ', $tokens), $last];
    }

    /**
     * Case-insensitive violation comparison key.
     */
    private function violationKey(string $violation): string
    {
        $collapsed = preg_replace('/\s+/', ' ', mb_strtolower(trim($violation)));

        return trim((string) $collapsed);
    }

    /**
     * Title-case a department name for display.
     */
    private function titleCase(string $section): string
    {
        return ucwords(mb_strtolower(preg_replace('/\s+/', ' ', trim($section))));
    }
}
