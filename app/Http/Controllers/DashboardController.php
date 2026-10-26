<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\StudentOffense;
use App\Models\StudentRfid;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function showDashboard(Request $request): View
    {
        $recentOffenses = StudentOffense::query()
            ->with(['student', 'offenseType'])
            ->orderByDesc('incident_at')
            ->limit(5)
            ->get();

        $pending = StudentOffense::query()
            ->with(['student', 'offenseType'])
            ->open()
            ->orderByDesc('incident_at')
            ->limit(5)
            ->get();

        return view('dashboard', [
            'user' => $request->user(),
            'collegeCount' => Student::where('education_level', 'College')->where('enrollment_status', 'enrolled')->count(),
            'monthCases' => StudentOffense::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'prevMonthCases' => StudentOffense::whereMonth('created_at', now()->subMonth()->month)->whereYear('created_at', now()->subMonth()->year)->count(),
            'pendingCount' => StudentOffense::open()->count(),
            'cardCount' => StudentRfid::where('is_active', true)->count(),
            'recentOffenses' => $recentOffenses,
            'pending' => $pending,
        ]);
    }
}
