<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OffenseController;
use App\Http\Controllers\RfidStationController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Authentication
Route::get('/', [AuthController::class, 'showLogin'])->name('login');
Route::post('login', [AuthController::class, 'login'])->name('login.post');
Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function (): void {
    // Dashboard
    Route::get('dashboard', [DashboardController::class, 'showDashboard'])->name('dashboard');

    // RFID scanner station
    Route::get('scan', fn () => view('scanner'))->name('scan');

    // Own profile (every signed-in role)
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::get('profile/edit', [UserController::class, 'editProfile'])->name('profile.edit');
    Route::put('profile/edit', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::put('profile/password', [UserController::class, 'updatePassword'])->name('profile.password');
    Route::put('profile/theme', [UserController::class, 'updateTheme'])->name('profile.theme');

    // Bulk RFID card registration
    Route::get('rfid/register', [RfidStationController::class, 'show'])->name('rfid.register');
    Route::get('rfid/unassign', [RfidStationController::class, 'unassign'])->name('rfid.unassign');
    Route::post('rfid/register/assign', [RfidStationController::class, 'assign'])->name('rfid.bulk.assign');
    Route::post('rfid/register/undo', [RfidStationController::class, 'undo'])->name('rfid.bulk.undo');

    // Students
    Route::get('students', [StudentController::class, 'index'])->name('students.index');
    Route::get('students/create', [StudentController::class, 'create'])->name('students.create');
    Route::post('students', [StudentController::class, 'store'])->name('students.store');
    Route::get('students/search', [StudentController::class, 'search'])->name('students.search');
    Route::get('students/{student}', [StudentController::class, 'show'])->name('students.show');
    Route::get('students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::get('students/{student}/report', [StudentController::class, 'report'])->name('students.report');
    Route::put('students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');
    Route::post('students/{student}/rfid', [StudentController::class, 'assignRfid'])->name('students.rfid.assign');
    Route::delete('students/{student}/rfid', [StudentController::class, 'unassignRfid'])->name('students.rfid.unassign');

    // Offenses (the ledger)
    Route::get('offenses', [OffenseController::class, 'index'])->name('offenses.index');
    Route::get('offenses/export', [OffenseController::class, 'export'])->name('offenses.export');
    Route::get('offenses/create', [OffenseController::class, 'create'])->name('offenses.create');
    Route::get('offenses/create/{student}', [OffenseController::class, 'create'])->name('offenses.create.for-student');
    Route::post('offenses', [OffenseController::class, 'store'])->name('offenses.store');
    Route::get('offenses/{offense}/edit', [OffenseController::class, 'edit'])->name('offenses.edit');
    Route::get('offenses/{offense}/report', [OffenseController::class, 'report'])->name('offenses.report');
    Route::put('offenses/{offense}', [OffenseController::class, 'update'])->name('offenses.update');
    Route::delete('offenses/{offense}', [OffenseController::class, 'destroy'])->name('offenses.destroy');

    // Staff accounts (administrators only — enforced in the controller)
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::get('users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('users', [UserController::class, 'store'])->name('users.store');
    Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::patch('users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');

    // JSON APIs
    Route::get('api/student/{rfid}', [StudentController::class, 'findByRfid']);
});
