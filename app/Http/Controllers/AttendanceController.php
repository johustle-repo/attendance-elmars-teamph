<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->canManageUsers(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ]);

        $selectedDate = $filters['date'] ?? Date::now(config('app.timezone'))->toDateString();
        $attendanceEntries = $this->attendanceQuery($filters['search'] ?? null, $selectedDate)->get();
        $attendances = $this->buildAttendanceSummaries($attendanceEntries);

        return Inertia::render('attendances/index', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'date' => $selectedDate,
            ],
            'officeHours' => Attendance::officeHoursLabel(),
            'summary' => [
                'recordCount' => $attendances->count(),
                'uniqueUsers' => $attendances->pluck('user_id')->unique()->count(),
                'teamSize' => User::query()->visibleInSystem()->activeAgents()->count(),
            ],
            'canEditAttendanceTime' => $user->canEditAttendanceTime(),
            'attendances' => $attendances->values(),
        ]);
    }

    public function update(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->canEditAttendanceTime(), 403);
        abort_unless($attendance->user?->isVisibleInSystem(), 404);

        $validated = $request->validate([
            'recorded_date' => ['required', 'date'],
            'recorded_time' => ['required', 'date_format:H:i'],
        ]);

        $oldRecordedAt = $attendance->recorded_at?->toIso8601String();

        $attendance->update([
            'recorded_at' => Carbon::parse(
                $validated['recorded_date'].' '.$validated['recorded_time'],
                config('app.timezone'),
            ),
        ]);

        AuditLog::record(
            request: $request,
            action: 'attendance.update',
            resourceType: 'Attendance',
            resourceId: $attendance->id,
            oldValues: ['recorded_at' => $oldRecordedAt],
            newValues: ['recorded_at' => $attendance->fresh()?->recorded_at?->toIso8601String()],
        );

        return redirect()
            ->route('attendances.index', $request->only('search', 'date'))
            ->with('success', 'Attendance time updated successfully.');
    }

    public function destroy(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->canEditAttendanceTime(), 403);
        abort_unless($attendance->user?->isVisibleInSystem(), 404);

        $entryTypeLabel = str($attendance->entry_type)->replace('_', ' ')->headline()->value();
        $userName = $attendance->user?->name ?? 'this user';

        AuditLog::record(
            request: $request,
            action: 'attendance.delete',
            resourceType: 'Attendance',
            resourceId: $attendance->id,
            oldValues: [
                'user_id' => $attendance->user_id,
                'entry_type' => $attendance->entry_type,
                'recorded_at' => $attendance->recorded_at?->toIso8601String(),
                'source' => $attendance->source,
            ],
        );

        $attendance->delete();

        return redirect()
            ->route('attendances.index', $request->only('search', 'date'))
            ->with('success', $entryTypeLabel.' deleted for '.$userName.'.');
    }

    public function storeManualTimeOut(Request $request)
    {
        abort_unless($request->user()->canEditAttendanceTime(), 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'recorded_date' => ['required', 'date'],
            'recorded_time' => ['required', 'date_format:H:i'],
        ]);

        $user = User::query()
            ->visibleInSystem()
            ->findOrFail($validated['user_id']);

        $recordedAt = Carbon::parse(
            $validated['recorded_date'].' '.$validated['recorded_time'],
            config('app.timezone'),
        );

        $timeIn = Attendance::query()
            ->visibleInSystem()
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $validated['recorded_date'])
            ->where('entry_type', 'time_in')
            ->orderBy('recorded_at')
            ->first();

        if (! $timeIn) {
            return redirect()
                ->route('attendances.index', $request->only('search', 'date'))
                ->with('error', 'A Time In record is required before adding a Time Out.');
        }

        $existingTimeOut = Attendance::query()
            ->visibleInSystem()
            ->where('user_id', $user->id)
            ->whereDate('recorded_at', $validated['recorded_date'])
            ->where('entry_type', 'time_out')
            ->exists();

        if ($existingTimeOut) {
            return redirect()
                ->route('attendances.index', $request->only('search', 'date'))
                ->with('error', 'A Time Out record already exists for this user on the selected date.');
        }

        if ($recordedAt->lessThanOrEqualTo($timeIn->recorded_at)) {
            return redirect()
                ->route('attendances.index', $request->only('search', 'date'))
                ->with('error', 'Time Out must be later than the recorded Time In.');
        }

        $newAttendance = Attendance::query()->create([
            'user_id' => $user->id,
            'recorded_at' => $recordedAt,
            'entry_type' => 'time_out',
            'scanned_code' => $user->qr_value ?? 'manual-time-out-'.$user->id,
            'source' => 'manual_adjustment',
        ]);

        AuditLog::record(
            request: $request,
            action: 'attendance.manual_time_out',
            resourceType: 'Attendance',
            resourceId: $newAttendance->id,
            newValues: [
                'user_id' => $user->id,
                'entry_type' => 'time_out',
                'recorded_at' => $recordedAt->toIso8601String(),
                'source' => 'manual_adjustment',
            ],
        );

        return redirect()
            ->route('attendances.index', $request->only('search', 'date'))
            ->with('success', 'Time Out added successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ]);

        $selectedDate = $filters['date'] ?? Date::now(config('app.timezone'))->toDateString();
        $attendanceEntries = $this->attendanceQuery($filters['search'] ?? null, $selectedDate)->get();
        $attendances = $this->buildAttendanceSummaries($attendanceEntries);
        $xml = $this->buildExcelXml($attendances);

        return response()->streamDownload(function () use ($xml): void {
            echo $xml;
        }, 'attendance-'.$selectedDate.'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    private function attendanceQuery(?string $search, ?string $date): Builder
    {
        return Attendance::query()
            ->visibleInSystem()
            ->with('user')
            ->when($date, fn (Builder $query) => $query->whereDate('recorded_at', $date))
            ->when($search, function (Builder $query, string $search): void {
                $query->whereHas('user', function (Builder $userQuery) use ($search): void {
                    $userQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')
                        ->orWhere('employee_code', 'like', '%'.$search.'%');
                });
            })
            ->latest('recorded_at');
    }

    private function buildAttendanceSummaries(Collection $attendances): Collection
    {
        return $attendances
            ->groupBy(function (Attendance $attendance): string {
                return optional($attendance->recorded_at)->toDateString().'|'.$attendance->user_id;
            })
            ->map(function (Collection $entries): array {
                /** @var Collection<int, Attendance> $entries */
                $sortedEntries = $entries->sortBy('recorded_at')->values();
                /** @var Attendance $anchor */
                $anchor = $sortedEntries->first();
                $timeIn = $sortedEntries
                    ->filter(fn (Attendance $attendance): bool => $attendance->entry_type === 'time_in')
                    ->sortBy('recorded_at')
                    ->first();
                $timeOut = $sortedEntries
                    ->filter(fn (Attendance $attendance): bool => $attendance->entry_type === 'time_out')
                    ->sortByDesc('recorded_at')
                    ->first();
                $attendanceStatus = Attendance::lateStatusFor($timeIn?->recorded_at);

                return [
                    'key' => optional($anchor->recorded_at)->toDateString().'-'.$anchor->user_id,
                    'user_id' => $anchor->user_id,
                    'date' => optional($anchor->recorded_at)->toDateString(),
                    'display_date' => optional($anchor->recorded_at)->format('M d, Y'),
                    'employee_code' => $anchor->user?->employee_code,
                    'user_name' => $anchor->user?->name,
                    'user_email' => $anchor->user?->email,
                    'time_in_attendance_id' => $timeIn?->id,
                    'time_in_date' => optional($timeIn?->recorded_at)->toDateString(),
                    'time_in_time' => optional($timeIn?->recorded_at)->format('H:i'),
                    'time_in_display' => optional($timeIn?->recorded_at)->format('h:i A'),
                    'attendance_status' => $attendanceStatus['attendance_status'],
                    'status_label' => $attendanceStatus['status_label'],
                    'status_hint' => $attendanceStatus['status_hint'],
                    'late_minutes' => $attendanceStatus['late_minutes'],
                    'time_out_attendance_id' => $timeOut?->id,
                    'time_out_date' => optional($timeOut?->recorded_at)->toDateString(),
                    'time_out_time' => optional($timeOut?->recorded_at)->format('H:i'),
                    'time_out_display' => optional($timeOut?->recorded_at)->format('h:i A'),
                    'total_hours_label' => $this->shiftHoursLabel(
                        $timeIn?->recorded_at,
                        $timeOut?->recorded_at,
                        (bool) ($anchor->user?->night_shift_eligible ?? false),
                    ),
                ];
            })
            ->sortBy([
                ['date', 'desc'],
                ['user_name', 'asc'],
            ])
            ->values();
    }

    private function buildExcelXml(Collection $attendances): string
    {
        $rows = $attendances->map(function (array $attendance): string {
            $cells = [
                $attendance['display_date'] ?? '',
                $attendance['employee_code'] ?: 'USER-'.$attendance['user_id'],
                $attendance['user_name'] ?? '',
                $attendance['user_email'] ?? '',
                $attendance['time_in_display'] ?? '',
                $attendance['status_label'] ?? '',
                $attendance['time_out_display'] ?? '',
                $attendance['total_hours_label'] ?? '',
            ];

            $cellXml = collect($cells)
                ->map(fn (string $value) => '<Cell><Data ss:Type="String">'.$this->escapeXml($value).'</Data></Cell>')
                ->implode('');

            return '<Row>'.$cellXml.'</Row>';
        })->implode('');

        return <<<XML
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="Attendance">
  <Table>
   <Row>
    <Cell><Data ss:Type="String">Date</Data></Cell>
    <Cell><Data ss:Type="String">ID</Data></Cell>
    <Cell><Data ss:Type="String">Name</Data></Cell>
    <Cell><Data ss:Type="String">Email</Data></Cell>
    <Cell><Data ss:Type="String">Time In</Data></Cell>
    <Cell><Data ss:Type="String">Status</Data></Cell>
    <Cell><Data ss:Type="String">Time Out</Data></Cell>
    <Cell><Data ss:Type="String">Total Hours</Data></Cell>
   </Row>
   {$rows}
  </Table>
 </Worksheet>
</Workbook>
XML;
    }

    private function shiftHoursLabel(
        ?CarbonInterface $timeIn,
        ?CarbonInterface $timeOut,
        bool $nightShiftEligible = false,
    ): ?string {
        if (! $timeIn || ! $timeOut || $timeOut->lessThan($timeIn)) {
            return null;
        }

        $tz = config('app.timezone');
        $date = $timeIn->toDateString();

        $minutes = $this->overlapMinutes($timeIn, $timeOut, CarbonImmutable::parse($date.' 08:00:00', $tz), CarbonImmutable::parse($date.' 12:00:00', $tz))
                 + $this->overlapMinutes($timeIn, $timeOut, CarbonImmutable::parse($date.' 13:00:00', $tz), CarbonImmutable::parse($date.' 17:00:00', $tz));

        if ($nightShiftEligible) {
            $minutes += $this->overlapMinutes($timeIn, $timeOut, CarbonImmutable::parse($date.' 18:00:00', $tz), CarbonImmutable::parse($date.' 21:00:00', $tz));
        }

        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    private function overlapMinutes(
        CarbonInterface $timeIn,
        CarbonInterface $timeOut,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): int {
        $effectiveStart = $timeIn->greaterThan($windowStart) ? $timeIn : $windowStart;
        $effectiveEnd   = $timeOut->lessThan($windowEnd) ? $timeOut : $windowEnd;

        if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
            return 0;
        }

        return (int) $effectiveStart->diffInMinutes($effectiveEnd);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
