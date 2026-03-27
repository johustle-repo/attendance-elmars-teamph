<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Services\FirebaseRealtimeDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, FirebaseRealtimeDatabase $firebase): Response
    {
        $user = $request->user();
        $today = Date::now(config('app.timezone'))->toDateString();
        $visibleUsers = User::query()->visibleInSystem();
        $visibleAttendances = Attendance::query()->visibleInSystem();
        $recentAttendances = Attendance::query()
            ->visibleInSystem()
            ->with('user')
            ->when(
                ! $user->canManageUsers(),
                fn ($query) => $query->where('user_id', $user->id),
            )
            ->latest('recorded_at')
            ->take(8)
            ->get();
        $latestMyAttendanceToday = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $today)
            ->latest('recorded_at')
            ->first();
        $canManageUsers = $user->canManageUsers();

        return Inertia::render('dashboard', [
            'canManageUsers' => $canManageUsers,
            'stats' => $canManageUsers ? [
                'totalUsers' => (clone $visibleUsers)->count(),
                'totalAdmins' => (clone $visibleUsers)->where('role', 'admin')->count(),
                'attendanceToday' => (clone $visibleAttendances)->whereDate('recorded_at', $today)->count(),
                'presentToday' => (clone $visibleAttendances)->whereDate('recorded_at', $today)->distinct('user_id')->count('user_id'),
                'firebaseConfigured' => $firebase->isConfigured(),
            ] : null,
            'memberSummary' => ! $canManageUsers ? [
                'totalAttendances' => Attendance::query()->where('user_id', $user->id)->count(),
                'todayAttendances' => Attendance::query()->where('user_id', $user->id)->whereDate('recorded_at', $today)->count(),
                'lastEntryType' => $latestMyAttendanceToday?->entry_type,
                'lastEntryTypeLabel' => $latestMyAttendanceToday?->entry_type
                    ? Str::headline(str_replace('_', ' ', $latestMyAttendanceToday->entry_type))
                    : 'No scan yet',
                'firebaseConfigured' => $firebase->isConfigured(),
            ] : null,
            'myQrValue' => $user->qr_value,
            'recentAttendances' => $recentAttendances->map(function (Attendance $attendance): array {
                $lateStatus = $attendance->entry_type === 'time_in'
                    ? Attendance::lateStatusFor($attendance->recorded_at)
                    : [
                        'attendance_status' => null,
                        'status_label' => null,
                        'status_hint' => null,
                        'late_minutes' => null,
                    ];

                return [
                    'id' => $attendance->id,
                    'user_name' => $attendance->user?->name,
                    'employee_code' => $attendance->user?->employee_code,
                    'entry_type' => $attendance->entry_type,
                    'entry_type_label' => Str::headline(str_replace('_', ' ', $attendance->entry_type)),
                    'attendance_status' => $lateStatus['attendance_status'],
                    'status_label' => $lateStatus['status_label'],
                    'status_hint' => $lateStatus['status_hint'],
                    'late_minutes' => $lateStatus['late_minutes'],
                    'recorded_at' => optional($attendance->recorded_at)->toIso8601String(),
                    'recorded_date' => optional($attendance->recorded_at)->format('M d, Y'),
                    'recorded_time' => optional($attendance->recorded_at)->format('h:i A'),
                    'source' => $attendance->source,
                ];
            }),
        ]);
    }
}
