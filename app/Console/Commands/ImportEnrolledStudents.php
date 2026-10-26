<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportEnrolledStudents extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:import-enrolled
                            {--force : Skip the confirmation prompt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Replace the student registry with the enrolled-students export (students_import staging table), including their RFID cards';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('students_import')) {
            $this->error('Staging table students_import not found. Import the SQL dump into students_import first.');

            return self::FAILURE;
        }

        $total = DB::table('students_import')->count();
        $this->info("Found {$total} enrolled students in the staging table.");

        if ($total === 0) {
            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This REPLACES the entire student registry (existing students and their RFID cards are removed). Continue?')) {
            return self::FAILURE;
        }

        $cases = DB::table('student_offenses')->count();
        if ($cases > 0) {
            $this->error("There are {$cases} offense cases referencing students. Student records cannot be replaced while cases exist.");

            return self::FAILURE;
        }

        $adminId = (int) (DB::table('users')->where('is_active', 1)->orderBy('id')->value('id') ?? 0);

        $imported = DB::transaction(function () use ($total, $adminId) {
            DB::table('student_rfid')->delete();
            DB::table('students')->delete();

            $students = [];
            $cards = [];
            $count = 0;

            $rows = DB::table('students_import')->orderBy('id')->get();

            foreach ($rows as $row) {
                [$first, $middle, $last, $suffix] = $this->splitName($row->firstname, $row->middlename, $row->lastname);
                [$level, $yearLevel] = $this->resolveLevel($row->year, $row->grade);

                $students[] = [
                    'student_number' => trim((string) $row->sid),
                    'first_name' => $first,
                    'middle_name' => $middle,
                    'last_name' => $last,
                    'suffix' => $suffix,
                    'education_level' => $level,
                    'course_or_grade' => $this->normalizeCourse($row->course),
                    'year_level' => $yearLevel,
                    'section' => $this->clean($row->section),
                    'photo_path' => null,
                    'enrollment_status' => 'enrolled',
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                $rfid = $this->clean($row->rfid);
                if ($rfid !== null) {
                    $cards[] = [
                        'card_uid' => $rfid,
                        'student_index' => count($students) - 1,
                        'assigned_by' => $adminId,
                        'assigned_at' => now(),
                    ];
                }

                $count++;

                if (count($students) === 500) {
                    $this->flush($students, $cards, $count, $total);
                    $students = [];
                    $cards = [];
                }
            }

            $this->flush($students, $cards, $count, $total);

            return $count;
        });

        $cards = DB::table('student_rfid')->where('is_active', true)->count();
        $this->info("Imported {$imported} students and {$cards} active RFID cards.");

        return self::SUCCESS;
    }

    /**
     * Insert a chunk of students, then their cards mapped to real IDs.
     *
     * @param  array<int, array<string, mixed>>  $students
     * @param  array<int, array<string, mixed>>  $cards
     */
    private function flush(array $students, array $cards, int $count, int $total): void
    {
        if ($students === []) {
            return;
        }

        DB::table('students')->insert($students);

        $numbers = array_column($students, 'student_number');
        $ids = DB::table('students')
            ->whereIn('student_number', $numbers)
            ->pluck('id', 'student_number');

        $cardRows = [];
        foreach ($cards as $card) {
            $studentNumber = $students[$card['student_index']]['student_number'];
            $studentId = $ids[$studentNumber] ?? null;

            if ($studentId === null) {
                continue;
            }

            $cardRows[] = [
                'student_id' => $studentId,
                'card_uid' => $card['card_uid'],
                'is_active' => true,
                'assigned_by' => $card['assigned_by'],
                'assigned_at' => $card['assigned_at'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($cardRows !== []) {
            DB::table('student_rfid')->insert($cardRows);
        }

        $this->info("  … {$count} / {$total} processed");
    }

    /**
     * Proper-case a Filipino name; suffixes embedded in the last name
     * ("NOVELA JR.") are moved to the suffix column.
     *
     * @return array{0: string, 1: ?string, 2: string, 3: ?string}
     */
    private function splitName(?string $firstname, ?string $middlename, string $lastname): array
    {
        $properCase = fn (?string $value) => $value === null || trim($value) === ''
            ? null
            : ucwords(mb_strtolower(trim($value)), " \t\r\n\f\v\-'");

        $last = trim($lastname);
        $suffix = null;

        if (preg_match('/^(.*\S)\s+(JR\.?|SR\.?|III|IV)$/i', $last, $matches)) {
            $last = $matches[1];
            $raw = strtoupper(str_replace('.', '', $matches[2]));
            $suffix = in_array($raw, ['III', 'IV'], true) ? $raw : $raw.'.';
        }

        return [
            $properCase($firstname) ?? '',
            $properCase($middlename),
            $properCase($last) ?? '',
            $suffix,
        ];
    }

    /**
     * Derive education level and a clean year label from the export's
     * inconsistent year/grade columns.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveLevel(?string $year, ?string $grade): array
    {
        $year = trim((string) $year);
        $grade = trim((string) $grade);

        if (preg_match('/^([1-4])(ST|ND|RD|TH)\s*YEAR/i', $year)) {
            $ordinal = strtolower(substr(trim($year), 0, 2));
            $ordinal = match ($ordinal) {
                '1s' => '1st Year',
                '2n' => '2nd Year',
                '3r' => '3rd Year',
                default => '4th Year',
            };

            return ['College', $ordinal];
        }

        foreach ([$year, $grade] as $candidate) {
            if (preg_match('/^KINDER/i', $candidate)) {
                return ['Grade School', ucwords(strtolower($candidate))];
            }

            if (preg_match('/^GRADE\s*([1-9]|1[0-2])$/i', $candidate, $matches)) {
                $n = (int) $matches[1];
                $level = match (true) {
                    $n >= 11 => 'Senior High School',
                    $n >= 7 => 'Junior High School',
                    default => 'Grade School',
                };

                return [$level, 'Grade '.$n];
            }
        }

        return [null, null];
    }

    /**
     * Normalize course/strand names to their common display forms.
     */
    private function normalizeCourse(?string $course): ?string
    {
        $course = trim((string) $course);
        if ($course === '' || strcasecmp($course, 'N/A') === 0) {
            return null;
        }

        $strands = [
            'SCIENCE, TECHNOLOGY, ENGINEERING AND MATHEMATICS' => 'STEM',
            'HUMANITIES AND SOCIAL SCIENCES' => 'HUMSS',
            'ACCOUNTANCY, BUSINESS, AND MANAGEMENT' => 'ABM',
            'GENERAL ACADEMIC STRAND' => 'GAS',
            'TECHNICAL-VOCATIONAL-LIVELIHOOD' => 'TVL',
        ];
        foreach ($strands as $from => $to) {
            if (strcasecmp($course, $from) === 0) {
                return $to;
            }
        }

        $collapsed = str_replace(['-', '.', '. '], ' ', strtoupper($course));
        $collapsed = trim((string) preg_replace('/\s+/', ' ', $collapsed));
        $collapsed = str_replace('B S ', 'BS ', $collapsed);

        $programs = [
            'BS IN CRIMINOLOGY' => 'BS Criminology',
            'BS CRIMINOLOGY' => 'BS Criminology',
            'BS CRIM' => 'BS Criminology',
            'BSCRIM' => 'BS Criminology',
            'CRIMINOLOGY' => 'BS Criminology',
            'CRIM' => 'BS Criminology',
            'BSHM' => 'BS Hospitality Management',
            'BS IN HOSPITALITY MANAGEMENT' => 'BS Hospitality Management',
            'BS HOSPITALITY MANAGEMENT' => 'BS Hospitality Management',
            'HM' => 'BS Hospitality Management',
            'BSBA FM' => 'BSBA Financial Management',
            'BSBA FINANCIAL MANAGEMENT' => 'BSBA Financial Management',
            'FM' => 'BSBA Financial Management',
            'BSBA MM' => 'BSBA Marketing Management',
            'BSBA MARKETING MANAGEMENT' => 'BSBA Marketing Management',
            'MM' => 'BSBA Marketing Management',
            'BSBA HRDM' => 'BSBA Human Resource Management',
            'BSBA HR' => 'BSBA Human Resource Management',
            'HRDM' => 'BSBA Human Resource Management',
            'BSED ENGLISH' => 'BSED English',
            'ENGLISH' => 'BSED English',
            'BSED SOCIAL STUDIES' => 'BSED Social Studies',
            'SOCIAL STUDIES' => 'BSED Social Studies',
            'BSED SCIENCE' => 'BSED Science',
            'SCIENCE' => 'BSED Science',
            'BSED MATH' => 'BSED Mathematics',
            'BSED MATHEMATICS' => 'BSED Mathematics',
            'MATH' => 'BSED Mathematics',
            'BSIT' => 'BSIT',
            'BEED GENERALIST' => 'BEED Generalist',
            'BEED' => 'BEED',
            'BTLE HE' => 'BTLE-HE',
            'BTLED' => 'BTLED',
            'BSNED' => 'BSNED',
            'BSED' => 'BSED',
            'HME' => 'HME',
            'CJE' => 'CJE',
            'KINDER 2' => null,
        ];

        if (array_key_exists($collapsed, $programs)) {
            return $programs[$collapsed];
        }

        return ucwords(strtolower($collapsed));
    }

    /**
     * Trim a value; empty and N/A markers become null.
     */
    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return ($value === '' || strcasecmp($value, 'N/A') === 0) ? null : $value;
    }
}
