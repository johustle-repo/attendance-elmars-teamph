<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

        $selectedDate = $filters['date'] ?? today()->toDateString();
        $attendanceEntries = $this->attendanceQuery($filters['search'] ?? null, $selectedDate)->get();
        $attendances = $this->buildAttendanceSummaries($attendanceEntries);

        return Inertia::render('attendances/index', [
            'filters' => [
                'search' => $filters['search'] ?? '',
                'date' => $selectedDate,
            ],
            'summary' => [
                'recordCount' => $attendances->count(),
                'uniqueUsers' => $attendances->pluck('user_id')->unique()->count(),
                'teamSize' => User::query()->count(),
            ],
            'canEditAttendanceTime' => $user->canEditAttendanceTime(),
            'attendances' => $attendances->values(),
        ]);
    }

    public function update(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->canEditAttendanceTime(), 403);

        $validated = $request->validate([
            'recorded_date' => ['required', 'date'],
            'recorded_time' => ['required', 'date_format:H:i'],
        ]);

        $attendance->update([
            'recorded_at' => Carbon::parse($validated['recorded_date'].' '.$validated['recorded_time']),
        ]);

        return redirect()
            ->route('attendances.index', $request->only('search', 'date'))
            ->with('success', 'Attendance time updated successfully.');
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ]);

        $selectedDate = $filters['date'] ?? today()->toDateString();
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
                    'time_out_attendance_id' => $timeOut?->id,
                    'time_out_date' => optional($timeOut?->recorded_at)->toDateString(),
                    'time_out_time' => optional($timeOut?->recorded_at)->format('H:i'),
                    'time_out_display' => optional($timeOut?->recorded_at)->format('h:i A'),
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
                $attendance['time_out_display'] ?? '',
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
    <Cell><Data ss:Type="String">Time Out</Data></Cell>
   </Row>
   {$rows}
  </Table>
 </Worksheet>
</Workbook>
XML;
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
