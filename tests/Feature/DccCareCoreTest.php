<?php

namespace Tests\Feature;

use App\Console\Commands\InferStudentGenders;
use App\Models\OffenseType;
use App\Models\Student;
use App\Models\StudentOffense;
use App\Models\StudentRfid;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DccCareCoreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Juan Santos',
            'username' => 'j.santos',
            'email' => 'j.santos@dcc.test',
            'password' => 'discipline-2026',
            'role' => 'administrator',
            'is_active' => true,
        ]);
    }

    public function test_login_page_is_public_and_dashboard_requires_auth(): void
    {
        $this->get('/')->assertOk()->assertSee('Sign in');
        $this->get('/dashboard')->assertRedirect('/');
    }

    public function test_login_rejects_inactive_accounts(): void
    {
        User::create([
            'name' => 'Inactive Ivy',
            'username' => 'i.inactive',
            'password' => 'password123',
            'role' => 'coordinator',
            'is_active' => false,
        ]);

        $this->post('/login', ['username' => 'i.inactive', 'password' => 'password123'])
            ->assertSessionHasErrors();
    }

    public function test_admin_can_login_and_sees_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Juan');
    }

    public function test_student_crud_flow(): void
    {
        // Create
        $response = $this->actingAs($this->admin)->post('/students', [
            'student_number' => '2026-0001',
            'first_name' => 'Marco',
            'middle_name' => 'Reyes',
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
            'education_level' => 'Senior High School',
            'course_or_grade' => 'STEM',
            'year_level' => '11',
            'section' => 'STEM-A',
            'enrollment_status' => 'enrolled',
            'card_uid' => 'RFID-NEW-0001',
        ]);

        $student = Student::where('student_number', '2026-0001')->firstOrFail();

        $response->assertRedirect('/students/'.$student->id);
        $this->assertDatabaseHas('student_rfid', [
            'student_id' => $student->id,
            'card_uid' => 'RFID-NEW-0001',
            'is_active' => true,
        ]);

        // Read
        $this->actingAs($this->admin)
            ->get('/students/'.$student->id)
            ->assertOk()
            ->assertSee('Dela Cruz, Marco')
            ->assertSee('RFID-NEW-0001')
            ->assertSee('Male');

        // The avatar follows the assigned gender
        $this->assertStringContainsString('kid-boy.svg', $student->fresh()->avatarUrl());

        // Index with search
        $this->actingAs($this->admin)
            ->get('/students?q=marco')
            ->assertOk()
            ->assertSee('Dela Cruz, Marco');

        // Update
        $this->actingAs($this->admin)
            ->put('/students/'.$student->id, [
                'student_number' => '2026-0001',
                'first_name' => 'Marco',
                'middle_name' => 'Reyes',
                'last_name' => 'Dela Cruz',
                'suffix' => 'Jr.',
                'gender' => 'male',
                'education_level' => 'Senior High School',
                'course_or_grade' => 'STEM',
                'year_level' => '11',
                'section' => 'STEM-B',
                'enrollment_status' => 'enrolled',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('students', ['id' => $student->id, 'section' => 'STEM-B', 'suffix' => 'Jr.', 'gender' => 'male']);

        // Unassign card
        $this->actingAs($this->admin)
            ->delete('/students/'.$student->id.'/rfid')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_rfid', ['card_uid' => 'RFID-NEW-0001', 'is_active' => false]);
    }

    public function test_student_delete_is_blocked_when_cases_exist(): void
    {
        $student = Student::create([
            'student_number' => '2026-0002',
            'first_name' => 'Aina',
            'last_name' => 'Villanueva',
            'enrollment_status' => 'enrolled',
        ]);

        $type = $this->createOffenseType();

        StudentOffense::create([
            'reference_no' => 'OFF-2026-0001',
            'student_id' => $student->id,
            'offense_type_id' => $type->id,
            'incident_at' => now(),
            'description' => 'Test case',
            'status' => 'recorded',
            'reported_by' => $this->admin->id,
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->delete('/students/'.$student->id)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_rfid_lookup_api_returns_student(): void
    {
        $student = Student::create([
            'student_number' => '2026-0003',
            'first_name' => 'Joshua',
            'last_name' => 'Reyes',
            'enrollment_status' => 'enrolled',
        ]);

        StudentRfid::create([
            'student_id' => $student->id,
            'card_uid' => 'RFID-SCAN-001',
            'is_active' => true,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/student/RFID-SCAN-001')
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('student.full_name', 'Reyes, Joshua')
            ->assertJsonPath('card_uid', 'RFID-SCAN-001')
            ->assertJsonPath('student.avatar', Student::where('student_number', '2026-0003')->first()->avatarUrl());

        $this->actingAs($this->admin)
            ->getJson('/api/student/UNKNOWN-CARD')
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    public function test_rfid_lookup_api_accepts_student_number(): void
    {
        $student = Student::create([
            'student_number' => '1162513380',
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'enrollment_status' => 'enrolled',
        ]);

        StudentRfid::create([
            'student_id' => $student->id,
            'card_uid' => 'RFID-SCAN-002',
            'is_active' => true,
            'assigned_by' => $this->admin->id,
            'assigned_at' => now(),
        ]);

        // Manual entry by ID number resolves the same student and reports their card.
        $this->actingAs($this->admin)
            ->getJson('/api/student/1162513380')
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('student.full_name', 'Santos, Maria')
            ->assertJsonPath('card_uid', 'RFID-SCAN-002');

        // A student without a card still resolves; card_uid is null.
        $cardless = Student::create([
            'student_number' => '1162514509',
            'first_name' => 'Ana',
            'last_name' => 'Cruz',
            'enrollment_status' => 'enrolled',
        ]);

        $this->actingAs($this->admin)
            ->getJson('/api/student/1162514509')
            ->assertOk()
            ->assertJsonPath('exists', true)
            ->assertJsonPath('student.full_name', 'Cruz, Ana')
            ->assertJsonPath('card_uid', null);

        // Unknown numbers still report a miss.
        $this->actingAs($this->admin)
            ->getJson('/api/student/9999999999')
            ->assertOk()
            ->assertJsonPath('exists', false);
    }

    public function test_offense_crud_flow_with_reference_numbers(): void
    {
        $student = Student::create([
            'student_number' => '2026-0004',
            'first_name' => 'Kyle',
            'last_name' => 'Bautista',
            'enrollment_status' => 'enrolled',
        ]);

        $type = $this->createOffenseType();

        $this->actingAs($this->admin)
            ->get('/offenses/create/'.$student->id)
            ->assertOk()
            ->assertSee('Opening case');

        // Store with an initial follow-up action
        $this->actingAs($this->admin)
            ->post('/offenses', [
                'student_id' => $student->id,
                'offense_type_id' => $type->id,
                'incident_at' => now()->format('Y-m-d\TH:i'),
                'description' => 'Caught past the gate without a pass.',
                'initial_action' => 'reprimand',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $offense = StudentOffense::where('student_id', $student->id)->firstOrFail();

        $this->assertMatchesRegularExpression('/^OFF-\d{4}-\d{4}$/', $offense->reference_no);
        $this->assertSame('recorded', $offense->status);
        $this->assertDatabaseHas('offense_actions', [
            'offense_id' => $offense->id,
            'action_type' => 'reprimand',
        ]);
        $this->assertDatabaseHas('offense_audit_logs', [
            'offense_id' => $offense->id,
            'action' => 'offense.recorded',
        ]);

        // Ledger index + edit + update
        $this->actingAs($this->admin)->get('/offenses')->assertOk()->assertSee($offense->reference_no);
        $this->actingAs($this->admin)->get('/offenses/'.$offense->id.'/edit')->assertOk();

        // School-year filter renders without error and finds the case
        $this->actingAs($this->admin)
            ->get('/offenses?year='.now()->year)
            ->assertOk()
            ->assertSee($offense->reference_no);

        $this->actingAs($this->admin)
            ->put('/offenses/'.$offense->id, [
                'student_id' => $student->id,
                'offense_type_id' => $type->id,
                'incident_at' => now()->format('Y-m-d\TH:i'),
                'description' => 'Updated description.',
                'status' => 'under_investigation',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('student_offenses', ['id' => $offense->id, 'status' => 'under_investigation']);

        // Destroy
        $this->actingAs($this->admin)
            ->delete('/offenses/'.$offense->id)
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('student_offenses', ['id' => $offense->id]);
    }

    public function test_user_management_is_admin_only(): void
    {
        $coordinator = User::create([
            'name' => 'Maria Ramos',
            'username' => 'm.ramos',
            'password' => 'password123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->actingAs($coordinator)->get('/users')->assertForbidden();
        $this->actingAs($coordinator)->get('/users/create')->assertForbidden();

        $this->actingAs($this->admin)->get('/users')->assertOk()->assertSee('Maria Ramos');

        $this->actingAs($this->admin)
            ->post('/users', [
                'name' => 'Pedro Garcia',
                'username' => 'p.garcia',
                'role' => 'scanner',
                'password' => 'gate-pass-2026',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['username' => 'p.garcia', 'role' => 'scanner', 'is_active' => true]);

        $created = User::where('username', 'p.garcia')->firstOrFail();

        $this->actingAs($this->admin)
            ->patch('/users/'.$created->id.'/toggle')
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['id' => $created->id, 'is_active' => false]);
    }

    public function test_gender_inference_from_names(): void
    {
        $infer = new InferStudentGenders;

        $this->assertSame('female', $infer->infer('Mary Joy'));
        $this->assertSame('female', $infer->infer('Maria Cristina'));
        $this->assertSame('female', $infer->infer('Angel Mae'));
        $this->assertSame('female', $infer->infer('Kimberly'));
        $this->assertSame('female', $infer->infer('Jamaica'));
        $this->assertSame('female', $infer->infer('Karen'));
        $this->assertSame('male', $infer->infer('John Paul'));
        $this->assertSame('male', $infer->infer('Rey John'));
        $this->assertSame('male', $infer->infer('Joshua'));
        $this->assertSame('male', $infer->infer('Angelo'));
        $this->assertSame('male', $infer->infer('Christian Jay'));
        $this->assertSame('male', $infer->infer('Miguel'));
        $this->assertSame('male', $infer->infer('Noah'));
        $this->assertNull($infer->infer(''));
    }

    public function test_avatar_follows_gender(): void
    {
        $male = Student::create([
            'student_number' => '2026-3001',
            'first_name' => 'Marco',
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
            'enrollment_status' => 'enrolled',
        ]);

        $female = Student::create([
            'student_number' => '2026-3002',
            'first_name' => 'Aina',
            'last_name' => 'Villanueva',
            'gender' => 'female',
            'enrollment_status' => 'enrolled',
        ]);

        $this->assertStringContainsString('kid-boy.svg', $male->avatarUrl());
        $this->assertStringContainsString('kid-girl.svg', $female->avatarUrl());
    }

    public function test_scanner_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get('/scan')
            ->assertOk()
            ->assertSee('Scan with reader')
            ->assertSee('Type manually');
    }

    public function test_bulk_rfid_registration_flow(): void
    {
        $first = Student::create([
            'student_number' => '2026-1001',
            'first_name' => 'Ana',
            'last_name' => 'Garcia',
            'enrollment_status' => 'enrolled',
        ]);

        $second = Student::create([
            'student_number' => '2026-1002',
            'first_name' => 'Carlo',
            'last_name' => 'Mendoza',
            'enrollment_status' => 'enrolled',
        ]);

        // Station page renders with the queue
        $this->actingAs($this->admin)
            ->get('/rfid/register')
            ->assertOk()
            ->assertSee('Garcia, Ana')
            ->assertSee('Mendoza, Carlo');

        // Assign a card to the first student
        $this->actingAs($this->admin)
            ->postJson('/rfid/register/assign', ['student_id' => $first->id, 'card_uid' => 'rfid-bulk-0001'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('card_uid', 'RFID-BULK-0001')
            ->assertJsonPath('student.full_name', 'Garcia, Ana');

        $this->assertDatabaseHas('student_rfid', [
            'student_id' => $first->id,
            'card_uid' => 'RFID-BULK-0001',
            'is_active' => true,
        ]);

        // The same card cannot be taken from an active holder
        $this->actingAs($this->admin)
            ->postJson('/rfid/register/assign', ['student_id' => $second->id, 'card_uid' => 'RFID-BULK-0001'])
            ->assertStatus(422);

        $this->assertDatabaseHas('student_rfid', ['card_uid' => 'RFID-BULK-0001', 'student_id' => $first->id]);

        // Assigning the same card to the same student is a harmless no-op
        $this->actingAs($this->admin)
            ->postJson('/rfid/register/assign', ['student_id' => $first->id, 'card_uid' => 'RFID-BULK-0001'])
            ->assertOk()
            ->assertJsonPath('status', 'unchanged');

        // Undo releases the card and returns the student to the queue
        $this->actingAs($this->admin)
            ->postJson('/rfid/register/undo', ['student_id' => $first->id])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseHas('student_rfid', ['card_uid' => 'RFID-BULK-0001', 'is_active' => false]);

        // A retired card can be re-issued to a different student
        $this->actingAs($this->admin)
            ->postJson('/rfid/register/assign', ['student_id' => $second->id, 'card_uid' => 'RFID-BULK-0001'])
            ->assertOk()
            ->assertJsonPath('status', 'reassigned');

        $this->assertDatabaseHas('student_rfid', ['card_uid' => 'RFID-BULK-0001', 'student_id' => $second->id, 'is_active' => true]);
    }

    public function test_printable_reports_render(): void
    {
        $student = Student::create([
            'student_number' => '2026-2001',
            'first_name' => 'Marco',
            'last_name' => 'Dela Cruz',
            'education_level' => 'Senior High School',
            'course_or_grade' => 'STEM',
            'year_level' => '11',
            'section' => 'STEM-A',
            'enrollment_status' => 'enrolled',
        ]);

        $type = $this->createOffenseType();

        $offense = StudentOffense::create([
            'reference_no' => 'OFF-2026-0099',
            'student_id' => $student->id,
            'offense_type_id' => $type->id,
            'incident_at' => now(),
            'location' => 'Main gate',
            'description' => 'Caught past the gate without a pass.',
            'status' => 'recorded',
            'reported_by' => $this->admin->id,
            'recorded_at' => now(),
        ]);

        // Single case report
        $this->actingAs($this->admin)
            ->get('/offenses/'.$offense->id.'/report')
            ->assertOk()
            ->assertSee('OFF-2026-0099')
            ->assertSee('Dela Cruz, Marco')
            ->assertSee('Description of the incident')
            ->assertSee('Noted by');

        // Student case history report
        $this->actingAs($this->admin)
            ->get('/students/'.$student->id.'/report')
            ->assertOk()
            ->assertSee('Case History Report')
            ->assertSee('OFF-2026-0099')
            ->assertSee('Main gate');
    }

    public function test_students_pagination_renders_across_pages(): void
    {
        // A full page of students plus one forces page 2 to render
        for ($i = 1; $i <= 11; $i++) {
            Student::create([
                'student_number' => sprintf('2026-9%03d', $i),
                'first_name' => 'Test'.$i,
                'last_name' => 'Paginate'.$i,
                'enrollment_status' => 'enrolled',
            ]);
        }

        $this->actingAs($this->admin)
            ->get('/students?page=2')
            ->assertOk()
            ->assertSee('Paginate11')
            ->assertSee('Go to page 1');
    }

    public function test_offense_csv_export_respects_filters(): void
    {
        $student = Student::create([
            'student_number' => '2026-4001',
            'first_name' => 'Marco',
            'last_name' => 'Dela Cruz',
            'gender' => 'male',
            'enrollment_status' => 'enrolled',
        ]);

        $type = $this->createOffenseType();

        StudentOffense::create([
            'reference_no' => 'OFF-2026-0777',
            'student_id' => $student->id,
            'offense_type_id' => $type->id,
            'incident_at' => now(),
            'description' => 'CSV export test case, with comma',
            'status' => 'recorded',
            'reported_by' => $this->admin->id,
            'recorded_at' => now(),
        ]);

        // Full export contains the case, with the comma-containing description quoted
        $this->actingAs($this->admin)
            ->get('/offenses/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->assertSee('Case No.', false)
            ->assertSee('OFF-2026-0777', false)
            ->assertSee('Dela Cruz, Marco', false)
            ->assertSee('"CSV export test case, with comma"', false);

        // A severity filter that excludes the case empties the export
        $this->actingAs($this->admin)
            ->get('/offenses/export?severity=minor')
            ->assertOk()
            ->assertDontSee('OFF-2026-0777');
    }

    public function test_pod_role_has_full_access_except_accounts(): void
    {
        $pod = User::create([
            'name' => 'Ramon Torres',
            'username' => 'r.torres',
            'password' => 'prefect-2026',
            'role' => 'pod',
            'is_active' => true,
        ]);

        // Full access to operational features
        $this->actingAs($pod)->get('/students')->assertOk();
        $this->actingAs($pod)->get('/offenses')->assertOk();
        $this->actingAs($pod)->get('/scan')->assertOk();
        $this->actingAs($pod)->get('/rfid/register')->assertOk();

        // But not account management — the server blocks direct access
        $this->actingAs($pod)->get('/users')->assertForbidden();
        $this->actingAs($pod)->get('/users/create')->assertForbidden();

        // The sidebar shows a restricted-access modal instead of the page link
        $this->actingAs($pod)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Access restricted')
            ->assertSee('MIS Programmer');

        // The administrator can create a POD account and see it in Accounts
        $this->actingAs($this->admin)
            ->post('/users', [
                'name' => 'Pedro Garcia',
                'username' => 'p.garcia',
                'role' => 'pod',
                'password' => 'gate-pass-2026',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', ['username' => 'p.garcia', 'role' => 'pod']);

        $this->actingAs($this->admin)
            ->get('/users')
            ->assertOk()
            ->assertSee('Prefect of Discipline');
    }

    public function test_profile_page_and_password_change(): void
    {
        $user = User::create([
            'name' => 'Maria Ramos',
            'username' => 'm.ramos',
            'email' => 'm.ramos@dcc.test',
            'password' => 'old-password-1',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        $this->actingAs($user)->get('/profile')
            ->assertOk()
            ->assertSee('Maria Ramos')
            ->assertSee('m.ramos')
            ->assertSee('Coordinator');

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password-9',
                'password_confirmation' => 'new-password-9',
            ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)
            ->put('/profile/password', [
                'current_password' => 'old-password-1',
                'password' => 'new-password-9',
                'password_confirmation' => 'new-password-9',
            ])->assertRedirect()->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-password-9', $user->fresh()->password));
    }

    public function test_theme_preference_save_and_render(): void
    {
        $user = User::create([
            'name' => 'Maria Ramos',
            'username' => 'm.ramos',
            'password' => 'password-123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        // Default palette on first render
        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('data-theme="evergreen"', false)
            ->assertSee('Evergreen', false);

        // Save a palette
        $this->actingAs($user)
            ->putJson('/profile/theme', ['theme' => 'ocean'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('theme', 'ocean');

        $this->assertDatabaseHas('users', ['id' => $user->id, 'theme' => 'ocean']);

        // Every page then renders with the chosen palette
        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('data-theme="ocean"', false);

        // Invalid palettes are rejected
        $this->actingAs($user)
            ->putJson('/profile/theme', ['theme' => 'rainbow'])
            ->assertStatus(422);
    }

    public function test_profile_edit_page_and_update(): void
    {
        $user = User::create([
            'name' => 'Maria Ramos',
            'username' => 'm.ramos',
            'password' => 'password-123',
            'role' => 'coordinator',
            'is_active' => true,
        ]);

        User::create([
            'name' => 'Other User',
            'username' => 'other.user',
            'password' => 'password-123',
            'role' => 'scanner',
            'is_active' => true,
        ]);

        // Edit page renders with the signed-in user's details
        $this->actingAs($user)
            ->get('/profile/edit')
            ->assertOk()
            ->assertSee('Edit your profile')
            ->assertSee('m.ramos');

        // Update own name and email
        $this->actingAs($user)
            ->put('/profile/edit', [
                'name' => 'Maria S. Ramos',
                'username' => 'm.ramos',
                'email' => 'maria@dcc.test',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Maria S. Ramos',
            'email' => 'maria@dcc.test',
            'role' => 'coordinator',
        ]);

        // Duplicate username from another account is rejected
        $this->actingAs($user)
            ->put('/profile/edit', [
                'name' => 'Maria Ramos',
                'username' => 'other.user',
            ])
            ->assertSessionHasErrors('username');

        // Role cannot be escalated through the profile edit
        $this->actingAs($user)
            ->put('/profile/edit', [
                'name' => 'Maria Ramos',
                'username' => 'm.ramos',
                'role' => 'administrator',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'role' => 'coordinator']);
    }

    private function createOffenseType(): OffenseType
    {
        return OffenseType::create([
            'name' => 'Cutting classes',
            'severity' => 'major',
            'is_active' => true,
        ]);
    }
}
