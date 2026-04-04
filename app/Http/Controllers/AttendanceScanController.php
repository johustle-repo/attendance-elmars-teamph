<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceScanController extends Controller
{
    public function create(): Response
    {
        $latestAttendances = Attendance::query()
            ->visibleInSystem()
            ->with('user')
            ->latest('recorded_at')
            ->take(8)
            ->get();

        return Inertia::render('attendance/scan', [
            'latestAttendances' => $latestAttendances->map(function (Attendance $attendance): array {
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
                ];
            }),
            'teamCount' => User::query()->visibleInSystem()->activeAgents()->count(),
            'officeHours' => Attendance::officeHoursLabel(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'qr_code' => ['required', 'string', 'max:255'],
            'entry_type' => ['required', Rule::in(['time_in', 'time_out'])],
        ]);

        $token = $this->extractToken($validated['qr_code']);
        $user = User::query()->visibleInSystem()->where('qr_token', $token)->first();

        if (! $user) {
            return back()->with('error', 'QR code not recognized. Please use a valid member QR code.');
        }

        if (! $user->isActive()) {
            return back()->with('error', $user->name.' is marked as inactive and cannot record attendance.');
        }

        $entryType = $validated['entry_type'];
        $recordedAt = Date::now(config('app.timezone'));
        $attendanceDate = $recordedAt->toDateString();
        $duplicateCutoff = $recordedAt->subMinutes(2);
        $latestAttendanceToday = Attendance::query()
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $attendanceDate)
            ->latest('recorded_at')
            ->first();

        $duplicateScan = Attendance::query()
            ->where('user_id', $user->id)
            ->where('entry_type', $entryType)
            ->where('recorded_at', '>=', $duplicateCutoff)
            ->exists();

        if ($duplicateScan) {
            return back()->with(
                'error',
                Str::headline(str_replace('_', ' ', $entryType)).' was already recorded recently for '.$user->name.'.',
            );
        }

        if ($entryType === 'time_in' && $latestAttendanceToday?->entry_type === 'time_in') {
            return back()->with('error', $user->name.' is already timed in. Please use Time Out next.');
        }

        // Prevent a new Time In once the user has a completed cycle (Time In + Time Out) for today.
        if ($entryType === 'time_in' && $latestAttendanceToday?->entry_type === 'time_out') {
            return back()->with('error', $user->name.' has already completed attendance for today.');
        }

        // Time Out requires an open Time In (latest today must be a time_in entry).
        if ($entryType === 'time_out' && ($latestAttendanceToday === null || $latestAttendanceToday->entry_type !== 'time_in')) {
            return back()->with('error', $user->name.' must record Time In before Time Out.');
        }

        $attendance = Attendance::query()->create([
            'user_id' => $user->id,
            'recorded_at' => $recordedAt,
            'entry_type' => $entryType,
            'scanned_code' => $validated['qr_code'],
            'source' => 'qr_scan',
        ]);

        try {
            AuditLog::record(
                request: $request,
                action: 'attendance.scan',
                resourceType: 'Attendance',
                resourceId: $attendance->id,
                newValues: [
                    'user_id' => $user->id,
                    'entry_type' => $entryType,
                    'recorded_at' => $recordedAt->toIso8601String(),
                    'source' => 'qr_scan',
                ],
            );
        } catch (\Throwable) {
            // Audit log must never block attendance recording
        }

        return redirect()
            ->route('scan.create')
            ->with('success', Str::headline(str_replace('_', ' ', $entryType)).' recorded for '.$user->name.'.');
    }

    private function extractToken(string $qrCode): string
    {
        $normalized = trim($qrCode);

        return Str::startsWith($normalized, 'attendance:')
            ? Str::after($normalized, 'attendance:')
            : $normalized;
    }
}
