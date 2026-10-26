<?php

namespace App\Console\Commands;

use App\Models\Student;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InferStudentGenders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'students:infer-gender
                            {--force : Re-assign gender even when already set}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign student gender based on first-name patterns common in Filipino rosters';

    /**
     * Common Filipino female given names that defeat the ending rules.
     */
    private const FEMALE_NAMES = [
        'karen', 'angel', 'angeline', 'justine', 'kim', 'nicole', 'michelle', 'mich',
        'erika', 'erica', 'april', 'crystal', 'hazel', 'iris', 'mercy', 'grace',
        'joy', 'jenny', 'jennie', 'jen', 'jhen', 'ging', 'bing', 'donna', 'wena',
        'trisha', 'trixie', 'jessa', 'lyka', 'nika', 'rosario', 'sandra', 'wanda',
        'vina', 'divina', 'melinda', 'riza', 'crissa', 'jane', 'jean', 'may',
        'kate', 'kaye', 'claire', 'jade', 'faye', 'joyce', 'shane', 'mariz',
        'monique', 'louise', 'heart', 'princess', 'fe', 'abegail', 'abigail',
        'mariel', 'rachel', 'ashley', 'sharon', 'agnes', 'trish', 'jill',
        'vivian', 'renee', 'mica', 'jamaica', 'samantha', 'jennifer', 'chloe',
        'irish', 'loren', 'sam', 'juliet', 'desiree', 'genevieve', 'love',
        'flor', 'meriam', 'jannel', 'susan', 'joan', 'jewel', 'maricel',
        'janel', 'jezel', 'charmel', 'jasmen', 'kathleen', 'caren', 'gwen',
        'poleen', 'esheen', 'amber', 'apple', 'nichole', 'gayle', 'michele',
        'avrille', 'marylle', 'rosille', 'jasmin', 'hasmin', 'nasmin',
        'karyll', 'mechell', 'franchell', 'corazon', 'maricon', 'chloey',
        'lesley', 'hayley', 'kelsey', 'ainsley', 'tracey', 'summer', 'leigh',
    ];

    /**
     * Common Filipino male given names that defeat the ending rules.
     */
    private const MALE_NAMES = [
        'dave', 'david', 'jerome', 'josh', 'keith', 'ralph', 'louie', 'jesse',
        'ariel', 'joel', 'noel', 'michael', 'mike', 'rafael', 'gabriel', 'daniel',
        'emanuel', 'emmanuel', 'kyle', 'vince', 'vincent', 'joshua', 'israel',
        'isaiah', 'jeremiah', 'raphael', 'nathaniel', 'jun', 'julius', 'marlon',
        'marvin', 'ronnel', 'ronald', 'jerald', 'gerald', 'harold', 'arnel',
        'arnold', 'rolly', 'johnny', 'tony', 'robert', 'eric', 'erick', 'dennis',
        'genesis', 'james', 'carl', 'karl', 'andrei', 'ivan', 'ryan', 'lloyd',
        'kent', 'ethan', 'andrew', 'matthew', 'joseph', 'bryan', 'renz',
        'charles', 'aj', 'ace', 'chris', 'nash', 'jasper', 'rain', 'raven',
        'gian', 'marc', 'justin', 'allen', 'lawrence', 'laurence', 'jhon',
        'benedict', 'francis', 'marlou', 'tyron', 'averrill', 'averill', 'khem',
        'harvey', 'jeremy', 'billy', 'zachary', 'wayne', 'dwayne', 'maurice',
        'jake', 'joe', 'dexter', 'raymond', 'jomar', 'raffy', 'wendel', 'ronel',
        'boy', 'daryl', 'reniel', 'andre', 'ken', 'earl', 'jm', 'ross',
        'ezekiel', 'noah', 'jonah', 'russel', 'axel', 'ismael', 'darrel',
        'rodel', 'raffael', 'maxel', 'stephen', 'glen', 'ruben', 'jayden',
        'austin', 'edwin', 'darwin', 'aldwin', 'warren', 'augustin', 'hussien',
        'spencer', 'denver', 'diether', 'asher', 'rainer', 'dale', 'johndale',
        'aldrin', 'joaquin', 'klein', 'vinjamin', 'wendell', 'russell',
        'daryll', 'maxwell', 'bryll', 'simon', 'zion', 'dion', 'von', 'aljon',
        'marjon', 'zeon', 'lennon', 'elthon', 'rodney', 'joey', 'jayvee',
        'rj', 'rc', 'jhonny',
    ];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = (bool) $this->option('force');

        $query = Student::query();
        $query->when(! $force, fn ($q) => $q->whereNull('gender'));

        $total = (clone $query)->count();
        $this->info("Assigning gender to {$total} student".($total === 1 ? '' : 's').'…');

        $male = 0;
        $female = 0;
        $unresolved = 0;

        $updates = [];

        foreach ($query->orderBy('id')->cursor() as $student) {
            $gender = $this->infer($student->first_name);

            if ($gender === null) {
                $unresolved++;
                $this->line("  ? unresolved: {$student->full_name} ({$student->student_number})");

                continue;
            }

            $gender === 'male' ? $male++ : $female++;

            $updates[$student->id] = $gender;

            if (count($updates) === 500) {
                $this->flush($updates);
                $updates = [];
            }
        }

        $this->flush($updates);

        $this->info("Done. Male: {$male} · Female: {$female} · Unresolved: {$unresolved}");
        if ($unresolved > 0) {
            $this->warn('Unresolved names are left unassigned — set them manually on the student profile.');
        }

        return self::SUCCESS;
    }

    /**
     * Persist a batch of gender updates.
     *
     * @param  array<int, string>  $updates  student id => gender
     */
    private function flush(array $updates): void
    {
        foreach ($updates as $id => $gender) {
            DB::table('students')->where('id', $id)->update(['gender' => $gender]);
        }
    }

    /**
     * Guess a gender from a Filipino first name.
     */
    public function infer(string $fullName): ?string
    {
        $name = mb_strtolower(trim($fullName));
        if ($name === '') {
            return null;
        }

        $tokens = preg_split('/[\s.]+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($tokens === []) {
            return null;
        }

        $first = $tokens[0];
        $last = $tokens[count($tokens) - 1];

        // 1. Maria / Mary / Ma. prefixes are female even in long compound names
        if (in_array($first, ['maria', 'mary', 'ma'], true)) {
            return 'female';
        }

        // 2. John / Mark / Jose / Juan compounds are male ("John Paul", "John Lloyd"…)
        if (in_array($first, ['john', 'jhon', 'jon', 'mark', 'marc', 'paul', 'jose', 'joseph', 'juan', 'jesus', 'christian', 'angelo', 'joshua', 'josh'], true)) {
            return 'male';
        }

        // Strong male given names appearing anywhere in a compound ("Rey John")
        $strongMale = ['john', 'jhon', 'jon', 'mark', 'marc', 'paul', 'jr', 'iii', 'ii'];
        foreach ($tokens as $token) {
            if (in_array($token, $strongMale, true)) {
                return 'male';
            }
        }

        // 3. Exact-name lexicon for names that defeat the ending rules
        if (in_array($last, self::FEMALE_NAMES, true) || in_array($name, self::FEMALE_NAMES, true) || in_array($first, self::FEMALE_NAMES, true)) {
            return 'female';
        }

        if (in_array($last, self::MALE_NAMES, true) || in_array($name, self::MALE_NAMES, true) || in_array($first, self::MALE_NAMES, true)) {
            return 'male';
        }

        // 4. Compound-name endings ("* Mae", "* Joy", "* Anthony"…)
        if ($this->endsWith($last, ['mae', 'joy', 'grace', 'rose', 'ann', 'anne', 'faith', 'hope', 'marie', 'belle', 'bel'])) {
            return 'female';
        }

        if ($this->endsWith($last, ['anthony', 'rey', 'james', 'jay', 'paul', 'mark', 'vincent', 'james'])) {
            return 'male';
        }

        // 5. Ending-pattern rules — last token first, then the first token
        foreach ([$last, $first] as $candidate) {
            if ($this->endsWith($candidate, ['lyn', 'lynn', 'lin', 'line', 'rine', 'nine', 'mine', 'elle', 'ette', 'ina', 'ena', 'ica', 'ika', 'issa', 'essa', 'osa', 'anda', 'inda', 'berly', 'ie', 'ah', 'th', 'ly', 'fer', 'iz', 'que', 'ise', 'aire', 'ade', 'aye', 'ate', 'dy', 'ne', 'sy', 'bby', 'oe', 'ea', 'min', 'een', 'ley', 'ese', 'ish'])) {
                return 'female';
            }

            if ($this->endsWith($candidate, ['son', 'bert', 'vin', 'ton', 'don', 'ander', 'ito', 'erto', 'ico', 'icko', 'wald', 'fred', 'pher', 'us', 'uel', 'ew', 'nce', 'ent', 'per', 'ck', 'ry', 'ic', 'lou', 'il', 'al', 'ed', 'nz', 'ard', 'ron', 'ei', 'ill', 'es', 'yan', 'an', 'iel', 'ter', 'mar', 'arl', 'den', 'mer', 'win', 'nny', 'un', 'ix', 'im', 'ld', 'oy', 'or', 'as', 'on'])) {
                return 'male';
            }
        }

        // 6. Spanish-style vowel endings, with a small exception set
        if (in_array($first, ['joshua', 'israel', 'isaiah', 'jeremiah'], true) || in_array($last, ['joshua', 'israel', 'isaiah', 'jeremiah'], true)) {
            return 'male';
        }

        if (mb_substr($last, -1) === 'a') {
            return 'female';
        }

        if (mb_substr($last, -1) === 'o') {
            return 'male';
        }

        return null;
    }

    /**
     * Case-insensitive suffix check.
     *
     * @param  array<int, string>  $suffixes
     */
    private function endsWith(string $token, array $suffixes): bool
    {
        foreach ($suffixes as $suffix) {
            if (mb_strlen($token) >= mb_strlen($suffix) && str_ends_with($token, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
