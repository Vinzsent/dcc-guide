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
     * Show the admin dashboard — all figures cover College students only.
     */
    public function showDashboard(Request $request): View
    {
        $recentOffenses = StudentOffense::query()
            ->forCollegeStudents()
            ->with(['student', 'offenseType'])
            ->orderByDesc('incident_at')
            ->limit(5)
            ->get();

        $pending = StudentOffense::query()
            ->forCollegeStudents()
            ->with(['student', 'offenseType'])
            ->open()
            ->orderByDesc('incident_at')
            ->limit(5)
            ->get();

        $schoolYear = StudentOffense::currentSchoolYear();

        return view('dashboard', [
            'user' => $request->user(),
            'collegeCount' => Student::where('education_level', 'College')->where('enrollment_status', 'enrolled')->count(),
            'monthCases' => StudentOffense::forCollegeStudents()
                ->whereMonth('incident_at', now()->month)
                ->whereYear('incident_at', now()->year)
                ->count(),
            'prevMonthCases' => StudentOffense::forCollegeStudents()
                ->whereMonth('incident_at', now()->subMonth()->month)
                ->whereYear('incident_at', now()->subMonth()->year)
                ->count(),
            'syCases' => StudentOffense::forCollegeStudents()->forSchoolYear($schoolYear['start_year'])->count(),
            'schoolYear' => $schoolYear,
            'pendingCount' => StudentOffense::forCollegeStudents()->open()->count(),
            'cardCount' => StudentRfid::activeForCollege()->count(),
            'recentOffenses' => $recentOffenses,
            'pending' => $pending,
        ]);
    }
}
