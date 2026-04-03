<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    private const PRIORITY_BACKUP_USER_NAME = 'Elmar B. Noche';

    private const EXCEL_WORKSHEET_NAME_LIMIT = 31;

    public function index(Request $request): Response
    {
        abort_unless($request->user()->canManageUsers(), 403);

        [$year, $month] = $this->validatedPeriod($request);
        $dataset = $this->buildDataset($year, $month);

        return Inertia::render('backups/index', [
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'availableYears' => $this->availableYears(),
            'availableMonths' => $this->availableMonths(),
            'summary' => [
                'periodLabel' => $dataset['period']['label'],
                'totalUsers' => count($dataset['users']),
                'usersWithAttendance' => collect($dataset['users'])
                    ->filter(fn (array $user): bool => $user['attendance_day_count'] > 0)
                    ->count(),
                'attendanceDayCount' => collect($dataset['users'])->sum('attendance_day_count'),
                'attendanceLogCount' => collect($dataset['users'])->sum('attendance_log_count'),
                'totalWorkHours' => $this->formatWorkedMinutes(
                    collect($dataset['users'])->sum('total_work_minutes'),
                ),
            ],
            'backupUsers' => $dataset['users'],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        [$year, $month] = $this->validatedPeriod($request);
        $type = $this->validatedExportType($request);
        $dataset = $this->buildDataset($year, $month);
        $generatedAt = Date::now(config('app.timezone'))->toIso8601String();

        AuditLog::record(
            request: $request,
            action: 'backup.export',
            resourceType: 'Backup',
            newValues: [
                'year' => $year,
                'month' => $month,
                'format' => $type,
                'generated_at' => $generatedAt,
            ],
        );

        return match ($type) {
            'excel' => $this->downloadExcelBackup($year, $month, $generatedAt, $dataset),
            'pdf' => $this->downloadPdfBackup($year, $month, $generatedAt, $dataset),
            default => $this->downloadJsonBackup($year, $month, $generatedAt, $dataset),
        };
    }

    /**
     * @param  array{period: array<string, mixed>, users: array<int, array<string, mixed>>}  $dataset
     */
    private function downloadJsonBackup(int $year, int $month, string $generatedAt, array $dataset): StreamedResponse
    {
        $json = json_encode([
            'generated_at' => $generatedAt,
            'period' => $dataset['period'],
            'users' => $dataset['users'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        $filename = sprintf('attendance-backup-%d-%02d.json', $year, $month);

        return response()->streamDownload(function () use ($json): void {
            echo $json ?: '{}';
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: array<string, mixed>, users: array<int, array<string, mixed>>}  $dataset
     */
    private function downloadExcelBackup(int $year, int $month, string $generatedAt, array $dataset): StreamedResponse
    {
        $xml = $this->buildBackupExcelXml($generatedAt, $dataset);
        $filename = sprintf('attendance-backup-%d-%02d.xls', $year, $month);

        return response()->streamDownload(function () use ($xml): void {
            echo $xml;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{period: array<string, mixed>, users: array<int, array<string, mixed>>}  $dataset
     */
    private function downloadPdfBackup(int $year, int $month, string $generatedAt, array $dataset): StreamedResponse
    {
        $pdf = $this->buildPdfDocument($this->buildPdfPageStreams($generatedAt, $dataset));
        $filename = sprintf('attendance-backup-%d-%02d.pdf', $year, $month);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array{0:int,1:int}
     */
    private function validatedPeriod(Request $request): array
    {
        $now = Date::now(config('app.timezone'));
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        return [
            (int) ($filters['year'] ?? $now->year),
            (int) ($filters['month'] ?? $now->month),
        ];
    }

    private function validatedExportType(Request $request): string
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['json', 'excel', 'pdf'])],
        ]);

        return $validated['type'] ?? 'json';
    }

    /**
     * @return array{period: array<string, mixed>, users: array<int, array<string, mixed>>}
     */
    private function buildDataset(int $year, int $month): array
    {
        $startOfMonth = CarbonImmutable::create(
            $year,
            $month,
            1,
            0,
            0,
            0,
            config('app.timezone'),
        )->startOfMonth();
        $endOfMonth = $startOfMonth->endOfMonth();

        $users = User::query()
            ->visibleInSystem()
            ->with([
                'attendances' => fn ($query) => $query
                    ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
                    ->orderBy('recorded_at'),
            ])
            ->get()
            ->filter(fn (User $user): bool => $user->isActive() || $user->attendances->isNotEmpty())
            ->sort(fn (User $left, User $right): int => $this->compareBackupUsers($left, $right))
            ->values();

        return [
            'period' => [
                'year' => $year,
                'month' => $month,
                'start' => $startOfMonth->toDateString(),
                'end' => $endOfMonth->toDateString(),
                'label' => $startOfMonth->format('F Y'),
            ],
            'users' => $users
                ->map(fn (User $user): array => $this->mapBackupUser($user))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapBackupUser(User $user): array
    {
        $attendanceDays = $this->buildAttendanceDays($user->attendances, (bool) $user->night_shift_eligible);
        $totalWorkMinutes = (int) collect($attendanceDays)->sum(
            fn (array $day): int => (int) ($day['total_work_minutes'] ?? 0),
        );

        return [
            'id' => $user->id,
            'name' => $user->name,
            'sub_name' => $user->sub_name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'role_label' => $user->role?->label(),
            'employee_code' => $user->employee_code,
            'position' => $user->position,
            'status' => $user->status,
            'status_label' => $user->statusLabel(),
            'qr_value' => $user->qr_value,
            'created_at' => optional($user->created_at)->toIso8601String(),
            'attendance_day_count' => count($attendanceDays),
            'attendance_log_count' => $user->attendances->count(),
            'total_work_minutes' => $totalWorkMinutes,
            'total_work_hours' => $this->formatWorkedMinutes($totalWorkMinutes),
            'attendance_days' => $attendanceDays,
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $attendances
     * @return array<int, array<string, mixed>>
     */
    private function buildAttendanceDays(Collection $attendances, bool $nightShiftEligible = false): array
    {
        return $attendances
            ->groupBy(fn (Attendance $attendance): string => (string) optional($attendance->recorded_at)->toDateString())
            ->map(function (Collection $entries) use ($nightShiftEligible): array {
                /** @var Collection<int, Attendance> $entries */
                $sortedEntries = $entries->sortBy('recorded_at')->values();
                /** @var Attendance $anchor */
                $anchor = $sortedEntries->first();
                $timeIn = $sortedEntries
                    ->first(fn (Attendance $attendance): bool => $attendance->entry_type === 'time_in');
                $timeOut = $sortedEntries
                    ->reverse()
                    ->first(fn (Attendance $attendance): bool => $attendance->entry_type === 'time_out');
                $totalWorkMinutes = $this->shiftWindowMinutes(
                    $timeIn?->recorded_at,
                    $timeOut?->recorded_at,
                    $nightShiftEligible,
                );

                return [
                    'date' => optional($anchor->recorded_at)->toDateString(),
                    'display_date' => optional($anchor->recorded_at)->format('M d, Y'),
                    'time_in' => optional($timeIn?->recorded_at)->format('h:i A'),
                    'time_out' => optional($timeOut?->recorded_at)->format('h:i A'),
                    'total_work_minutes' => $totalWorkMinutes,
                    'total_work_hours' => $totalWorkMinutes === null
                        ? null
                        : $this->formatWorkedMinutes($totalWorkMinutes),
                    'logs' => $sortedEntries->map(fn (Attendance $attendance): array => [
                        'id' => $attendance->id,
                        'entry_type' => $attendance->entry_type,
                        'entry_type_label' => str($attendance->entry_type)
                            ->replace('_', ' ')
                            ->headline()
                            ->value(),
                        'recorded_at' => optional($attendance->recorded_at)->toIso8601String(),
                        'recorded_time' => optional($attendance->recorded_at)->format('h:i A'),
                    ])->values()->all(),
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * Billable minutes clipped to shift windows (lunch 12–1 PM excluded).
     * Morning: 08:00–12:00. Afternoon: 13:00–17:00. Night (eligible only): 18:00–21:00.
     */
    private function shiftWindowMinutes(
        ?CarbonInterface $timeIn,
        ?CarbonInterface $timeOut,
        bool $nightShiftEligible = false,
    ): ?int {
        if (! $timeIn || ! $timeOut || $timeOut->lessThan($timeIn)) {
            return null;
        }

        $tz   = config('app.timezone');
        $date = $timeIn->toDateString();

        $minutes = $this->overlapMinutes($timeIn, $timeOut, CarbonImmutable::parse($date.' 08:00:00', $tz), CarbonImmutable::parse($date.' 12:00:00', $tz))
                 + $this->overlapMinutes($timeIn, $timeOut, CarbonImmutable::parse($date.' 13:00:00', $tz), CarbonImmutable::parse($date.' 17:00:00', $tz));

        if ($nightShiftEligible) {
            $minutes += $this->overlapMinutes($timeIn, $timeOut, CarbonImmutable::parse($date.' 18:00:00', $tz), CarbonImmutable::parse($date.' 21:00:00', $tz));
        }

        return $minutes;
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

    private function formatWorkedMinutes(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %02dm', $hours, $remainingMinutes);
    }

    private function compareBackupUsers(User $left, User $right): int
    {
        $priorityComparison = $this->backupPriorityRank($left) <=> $this->backupPriorityRank($right);

        if ($priorityComparison !== 0) {
            return $priorityComparison;
        }

        $roleComparison = $this->backupRoleRank($left) <=> $this->backupRoleRank($right);

        if ($roleComparison !== 0) {
            return $roleComparison;
        }

        $nameComparison = strcasecmp((string) $left->name, (string) $right->name);

        if ($nameComparison !== 0) {
            return $nameComparison;
        }

        return $left->id <=> $right->id;
    }

    private function backupPriorityRank(User $user): int
    {
        return $this->isPriorityBackupUser($user->name) ? 0 : 1;
    }

    private function backupRoleRank(User $user): int
    {
        return ($user->role?->value ?? null) === 'admin' ? 0 : 1;
    }

    private function isPriorityBackupUser(?string $name): bool
    {
        return strcasecmp(trim((string) $name), self::PRIORITY_BACKUP_USER_NAME) === 0;
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $currentYear = Date::now(config('app.timezone'))->year;
        $oldestAttendance = Attendance::query()->visibleInSystem()->oldest('recorded_at')->value('recorded_at');
        $oldestUser = User::query()->visibleInSystem()->oldest('created_at')->value('created_at');

        $startYear = collect([$oldestAttendance, $oldestUser])
            ->filter()
            ->map(fn ($value): int => CarbonImmutable::parse($value)->year)
            ->min() ?? $currentYear;

        return collect(range($startYear, $currentYear))
            ->reverse()
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    private function availableMonths(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month): array => [
                'value' => $month,
                'label' => CarbonImmutable::create(2026, $month, 1)->format('F'),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{period: array<string, mixed>, users: array<int, array<string, mixed>>}  $dataset
     */
    private function buildBackupExcelXml(string $generatedAt, array $dataset): string
    {
        $periodLabel = (string) ($dataset['period']['label'] ?? '');
        $generatedLabel = CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A');
        $users = collect($dataset['users'])
            ->filter(fn (array $user): bool => ($user['role'] ?? null) === 'member')
            ->values();

        if ($users->isEmpty()) {
            $worksheets = $this->buildEmptyBackupExcelWorksheet($periodLabel, $generatedLabel);
        } else {
            $worksheetNames = $this->buildUniqueExcelWorksheetNames(
                $users
                    ->map(fn (array $user, int $index): string => (string) ($user['name'] ?? 'User '.($index + 1)))
                    ->all(),
            );

            $worksheets = $users
                ->map(
                    fn (array $user, int $index): string => $this->buildBackupExcelWorksheet(
                        $worksheetNames[$index],
                        $periodLabel,
                        $generatedLabel,
                        $user,
                    ),
                )
                ->implode("\n");
        }

        return <<<XML
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 {$worksheets}
</Workbook>
XML;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    private function buildBackupExcelWorksheet(
        string $sheetName,
        string $periodLabel,
        string $generatedLabel,
        array $user,
    ): string {
        $attendanceRows = collect($user['attendance_days'] ?? [])
            ->map(
                fn (array $day): array => [
                    $day['display_date'] ?? '',
                    $day['time_in'] ?? 'Not recorded',
                    $day['time_out'] ?? 'Not recorded',
                    $day['total_work_hours'] ?? 'Not recorded',
                    collect($day['logs'] ?? [])
                        ->map(
                            fn (array $log): string => ($log['entry_type_label'] ?? 'Attendance')
                                .' - '
                                .($log['recorded_time'] ?? ''),
                        )
                        ->implode(', '),
                ],
            )
            ->values();

        if ($attendanceRows->isEmpty()) {
            $attendanceRows = collect([[
                '-',
                'Not recorded',
                'Not recorded',
                'Not recorded',
                'No attendance records for this member in the selected period.',
            ]]);
        }

        $rows = collect([
            ['Elmar\'s Team PH Backup'],
            ['Period', $periodLabel],
            ['Generated at', $generatedLabel],
            [],
            ['Name', (string) ($user['name'] ?? '')],
            ['Sub Name', (string) ($user['sub_name'] ?? 'Not set')],
            ['Email', (string) ($user['email'] ?? '')],
            ['Role', (string) ($user['role_label'] ?? $user['role'] ?? '')],
            ['Employee Code', (string) ($user['employee_code'] ?? 'Not set')],
            ['Position', (string) ($user['position'] ?? 'Not set')],
            ['Status', (string) ($user['status_label'] ?? $user['status'] ?? 'Active')],
            ['Attendance Days', (string) ($user['attendance_day_count'] ?? 0)],
            ['Attendance Logs', (string) ($user['attendance_log_count'] ?? 0)],
            ['Member Total Hours', (string) ($user['total_work_hours'] ?? $this->formatWorkedMinutes(0))],
            [],
            ['Date', 'Time In', 'Time Out', 'Daily Total Hours', 'Logs'],
        ])
            ->concat($attendanceRows)
            ->concat([
                [],
                ['Approved and verified by:'],
                [],
                [self::PRIORITY_BACKUP_USER_NAME],
            ])
            ->map(fn (array $cells): string => $this->buildBackupExcelRow($cells))
            ->implode("\n");

        $escapedSheetName = $this->escapeXml($sheetName);

        return <<<XML
 <Worksheet ss:Name="{$escapedSheetName}">
  <Table>
{$rows}
  </Table>
 </Worksheet>
XML;
    }

    private function buildEmptyBackupExcelWorksheet(string $periodLabel, string $generatedLabel): string
    {
        $rows = collect([
            ['Elmar\'s Team PH Backup'],
            ['Period', $periodLabel],
            ['Generated at', $generatedLabel],
            [],
            ['No visible users were found for this backup export.'],
            [],
            ['Approved and verified by:'],
            [],
            [self::PRIORITY_BACKUP_USER_NAME],
        ])
            ->map(fn (array $cells): string => $this->buildBackupExcelRow($cells))
            ->implode("\n");

        return <<<XML
 <Worksheet ss:Name="Backup Summary">
  <Table>
{$rows}
  </Table>
 </Worksheet>
XML;
    }

    /**
     * @param  array<int, string>  $worksheetNames
     * @return array<int, string>
     */
    private function buildUniqueExcelWorksheetNames(array $worksheetNames): array
    {
        $usedNames = [];

        return collect($worksheetNames)
            ->values()
            ->map(function (string $worksheetName, int $index) use (&$usedNames): string {
                $baseName = $this->sanitizeExcelWorksheetName(
                    $worksheetName !== '' ? $worksheetName : 'User '.($index + 1),
                );
                $candidate = $baseName;
                $suffix = 2;

                while (in_array($candidate, $usedNames, true)) {
                    $suffixLabel = ' ('.$suffix.')';
                    $candidate = mb_substr(
                        $baseName,
                        0,
                        self::EXCEL_WORKSHEET_NAME_LIMIT - strlen($suffixLabel),
                    ).$suffixLabel;
                    $suffix++;
                }

                $usedNames[] = $candidate;

                return $candidate;
            })
            ->all();
    }

    private function sanitizeExcelWorksheetName(string $worksheetName): string
    {
        $sanitized = preg_replace('/[:\\\\\\/\\?\\*\\[\\]]/', ' ', $worksheetName) ?? $worksheetName;
        $sanitized = preg_replace('/\s+/', ' ', trim($sanitized)) ?? trim($sanitized);

        if ($sanitized === '') {
            return 'User';
        }

        return mb_substr($sanitized, 0, self::EXCEL_WORKSHEET_NAME_LIMIT);
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function buildBackupExcelRow(array $cells): string
    {
        if ($cells === []) {
            return '   <Row></Row>';
        }

        $cellXml = collect($cells)
            ->map(fn (string $value): string => $this->buildBackupExcelCell($value))
            ->implode('');

        return '   <Row>'.$cellXml.'</Row>';
    }

    private function buildBackupExcelCell(string $value): string
    {
        return '<Cell><Data ss:Type="String">'.$this->escapeXml($value).'</Data></Cell>';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    /**
     * @param  array{period: array<string, mixed>, users: array<int, array<string, mixed>>}  $dataset
     * @return array<int, string>
     */
    private function buildPdfPageStreams(string $generatedAt, array $dataset): array
    {
        $members = collect($dataset['users'])
            ->filter(fn (array $user): bool => ($user['role'] ?? null) === 'member')
            ->values();

        if ($members->isEmpty()) {
            return [
                $this->buildEmptyPdfPageStream($generatedAt, $dataset['period'], 1, 2),
                $this->buildSignatoryPdfPageStream($generatedAt, $dataset['period'], 2, 2),
            ];
        }

        $totalPages = $members->count() + 1;
        $streams = $members
            ->map(
                fn (array $user, int $index): string => $this->buildUserPdfPageStream(
                    $generatedAt,
                    $dataset['period'],
                    $user,
                    $index + 1,
                    $totalPages,
                ),
            )
            ->all();

        $streams[] = $this->buildSignatoryPdfPageStream($generatedAt, $dataset['period'], $totalPages, $totalPages);

        return $streams;
    }

    /**
     * @param  array<int, string>  $pageStreams
     */
    private function buildPdfDocument(array $pageStreams): string
    {
        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';

        $pageReferences = [];
        $objectNumber = 5;

        foreach ($pageStreams as $contentStream) {
            $pageObjectNumber = $objectNumber++;
            $contentObjectNumber = $objectNumber++;
            $pageReferences[] = $pageObjectNumber.' 0 R';

            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
                .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents '.$contentObjectNumber.' 0 R >>';
            $objects[$contentObjectNumber] = "<< /Length ".strlen($contentStream)." >>\nstream\n"
                .$contentStream
                ."\nendstream";
        }

        $objects[2] = '<< /Type /Pages /Count '.count($pageStreams).' /Kids ['.implode(' ', $pageReferences).' ] >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$object."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf('%010d 00000 n ', $offsets[$i])."\n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF";

        return $pdf;
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildUserPdfPageStream(
        string $generatedAt,
        array $period,
        array $user,
        int $pageNumber,
        int $totalPages,
    ): string
    {
        $attendanceDays = collect($user['attendance_days'] ?? []);
        $rows = $attendanceDays
            ->map(fn (array $day): array => [
                'date' => $day['display_date'] ?? '-',
                'time_in' => $day['time_in'] ?? 'Not recorded',
                'time_out' => $day['time_out'] ?? 'Not recorded',
                'hours' => $day['total_work_hours'] ?? 'Not recorded',
                'status' => $this->attendanceStatusLabel($day),
            ])
            ->values()
            ->all();

        if (empty($rows)) {
            $rows[] = [
                'date' => '-',
                'time_in' => 'Not recorded',
                'time_out' => 'Not recorded',
                'hours' => 'Not recorded',
                'status' => 'No attendance records for this month',
            ];
        }

        $accountStatus = (string) ($user['status_label'] ?? $user['status'] ?? 'Active');
        $latestAttendanceDate = (string) ($attendanceDays->first()['display_date'] ?? 'No records');
        [$accountStatusFill, $accountStatusAccent] = $this->accountStatusPalette(
            (string) ($user['status'] ?? $accountStatus),
        );

        $stream = $this->buildPdfHeader(
            $generatedAt,
            $period,
            'Member Report',
            'Monthly attendance backup',
            $pageNumber,
            $totalPages,
        );
        $stream .= $this->pdfText(30, 638, 'Member Details', 11.5, 'F2', [30, 41, 59]);
        $stream .= $this->pdfRect(
            30,
            500,
            552,
            126,
            strokeColor: [203, 213, 225],
            fillColor: [255, 255, 255],
            lineWidth: 0.8,
        );
        $stream .= $this->pdfRect(30, 610, 552, 8, fillColor: [239, 246, 255], lineWidth: 0);
        $stream .= $this->pdfRect(30, 500, 6, 126, fillColor: [63, 120, 190], lineWidth: 0);
        $stream .= $this->pdfLine(374, 514, 374, 594, [226, 232, 240], 0.8);
        $stream .= $this->pdfText(
            46,
            600,
            $this->fitPdfText((string) ($user['name'] ?? ''), 308, 16, 'F2'),
            16,
            'F2',
            [17, 24, 39],
        );
        $stream .= $this->pdfText(
            46,
            580,
            $this->fitPdfText((string) ($user['email'] ?? ''), 308, 10),
            10,
            'F1',
            [71, 85, 105],
        );
        if (filled($user['sub_name'] ?? null)) {
            $stream .= $this->pdfText(
                46,
                566,
                $this->fitPdfText('Sub Name: '.(string) $user['sub_name'], 308, 8.5, 'F1'),
                8.5,
                'F1',
                [8, 145, 178],
            );
        }
        $stream .= $this->buildPdfTextPair(
            46,
            556,
            132,
            'Employee Code',
            (string) ($user['employee_code'] ?? 'Not set'),
        );
        $stream .= $this->buildPdfTextPair(
            188,
            556,
            164,
            'Position',
            (string) ($user['position'] ?? 'Not set'),
        );
        $stream .= $this->buildPdfMetricCard(
            392,
            556,
            82,
            34,
            'Status',
            $accountStatus,
            $accountStatusFill,
            $accountStatusAccent,
        );
        $stream .= $this->buildPdfMetricCard(
            482,
            556,
            82,
            34,
            'Role',
            (string) ($user['role_label'] ?? $user['role'] ?? 'Member'),
            [248, 250, 252],
            [71, 85, 105],
        );
        $stream .= $this->buildPdfMetricCard(
            392,
            512,
            82,
            34,
            'Days',
            (string) $attendanceDays->count(),
            [239, 246, 255],
            [29, 78, 216],
        );
        $stream .= $this->buildPdfMetricCard(
            482,
            512,
            82,
            34,
            'Logs',
            (string) ($user['attendance_log_count'] ?? 0),
            [236, 253, 245],
            [22, 101, 52],
        );
        $stream .= $this->buildPdfTextPair(46, 526, 152, 'Latest Attendance', $latestAttendanceDate);
        $stream .= $this->buildPdfTextPair(
            214,
            526,
            138,
            'Total Hours',
            (string) ($user['total_work_hours'] ?? $this->formatWorkedMinutes(0)),
        );
        $stream .= $this->pdfText(30, 486, 'Attendance Log Summary', 11.5, 'F2', [30, 41, 59]);
        $stream .= $this->pdfText(
            30,
            470,
            'Daily first time-in, last time-out, and completion status for the selected backup period.',
            8.5,
            'F1',
            [100, 116, 139],
        );

        $stream .= $this->buildUserPdfTable(
            30,
            454,
            [108, 78, 78, 82, 206],
            ['Date', 'Time In', 'Time Out', 'Total Hours', 'Status'],
            $rows,
        );

        return $stream.$this->buildPdfFooter($generatedAt, $pageNumber, $totalPages);
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildEmptyPdfPageStream(
        string $generatedAt,
        array $period,
        int $pageNumber,
        int $totalPages,
    ): string
    {
        $stream = $this->buildPdfHeader(
            $generatedAt,
            $period,
            'Archive Summary',
            'Monthly attendance backup',
            $pageNumber,
            $totalPages,
        );
        $stream .= $this->pdfText(30, 638, 'Attendance Overview', 11.5, 'F2', [30, 41, 59]);
        $stream .= $this->pdfRect(
            30,
            364,
            552,
            244,
            strokeColor: [203, 213, 225],
            fillColor: [255, 255, 255],
            lineWidth: 0.8,
        );
        $stream .= $this->pdfRect(30, 594, 552, 10, fillColor: [239, 246, 255], lineWidth: 0);
        $stream .= $this->pdfText(46, 564, 'No member attendance records found', 18, 'F2', [17, 24, 39]);
        $stream .= $this->pdfText(
            46,
            542,
            'The selected backup period does not contain any member time-in or time-out entries.',
            10,
            'F1',
            [71, 85, 105],
        );
        $stream .= $this->pdfText(
            46,
            525,
            'The approval page is still included so this PDF can be stored as an archive record.',
            10,
            'F1',
            [71, 85, 105],
        );
        $stream .= $this->buildPdfMetricCard(
            46,
            432,
            148,
            48,
            'Members Included',
            '0',
            [239, 246, 255],
            [29, 78, 216],
        );
        $stream .= $this->buildPdfMetricCard(
            206,
            432,
            166,
            48,
            'Report Status',
            'No data available',
            [255, 247, 237],
            [180, 83, 9],
        );
        $stream .= $this->buildPdfMetricCard(
            384,
            432,
            152,
            48,
            'Next Section',
            'Approval page',
            [236, 253, 245],
            [22, 101, 52],
        );
        $stream .= $this->pdfText(
            46,
            400,
            'Export another month from the backup center once attendance data becomes available.',
            9,
            'F1',
            [100, 116, 139],
        );

        return $stream.$this->buildPdfFooter($generatedAt, $pageNumber, $totalPages);
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildSignatoryPdfPageStream(
        string $generatedAt,
        array $period,
        int $pageNumber,
        int $totalPages,
    ): string
    {
        $periodLabel = (string) ($period['label'] ?? '');
        $generatedLabel = CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A');

        $stream = $this->buildPdfHeader(
            $generatedAt,
            $period,
            'Approval Page',
            'Approval and archive verification',
            $pageNumber,
            $totalPages,
        );
        $stream .= $this->pdfText(30, 638, 'Approval Summary', 11.5, 'F2', [30, 41, 59]);
        $stream .= $this->pdfRect(
            30,
            292,
            552,
            316,
            strokeColor: [203, 213, 225],
            fillColor: [255, 255, 255],
            lineWidth: 0.8,
        );
        $stream .= $this->pdfRect(30, 576, 552, 10, fillColor: [224, 231, 255], lineWidth: 0);
        $stream .= $this->pdfText(46, 548, 'Attendance Backup Approval', 18, 'F2', [17, 24, 39]);
        $stream .= $this->pdfText(
            46,
            528,
            'This attendance backup reflects the archived member attendance records for the selected month.',
            10,
            'F1',
            [71, 85, 105],
        );
        $stream .= $this->pdfText(
            46,
            512,
            'It is prepared for archive storage and internal verification.',
            10,
            'F1',
            [71, 85, 105],
        );
        $stream .= $this->buildPdfMetadataCard(46, 444, 220, 40, 'Backup Period', $periodLabel);
        $stream .= $this->buildPdfMetadataCard(278, 444, 220, 40, 'Generated At', $generatedLabel);
        $stream .= $this->pdfText(46, 394, 'Approved and verified by:', 10, 'F2', [47, 72, 88]);
        $stream .= $this->pdfLine(46, 342, 286, 342, [148, 163, 184], 1.0);
        $stream .= $this->pdfText(46, 320, self::PRIORITY_BACKUP_USER_NAME, 16, 'F2', [17, 24, 39]);
        $stream .= $this->pdfText(46, 302, 'Attendance Administrator', 10, 'F1', [100, 116, 139]);
        $stream .= $this->pdfText(46, 284, 'Signature', 8, 'F1', [100, 116, 139]);
        $stream .= $this->pdfText(
            334,
            394,
            'Archive note',
            10,
            'F2',
            [47, 72, 88],
        );
        $stream .= $this->pdfText(
            334,
            372,
            'Keep this page attached to the monthly attendance backup',
            9,
            'F1',
            [71, 85, 105],
        );
        $stream .= $this->pdfText(
            334,
            356,
            'when filing printed or digital archive copies.',
            9,
            'F1',
            [71, 85, 105],
        );

        return $stream.$this->buildPdfFooter($generatedAt, $pageNumber, $totalPages);
    }

    /**
     * @param  array<int, float>  $columnWidths
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, string>>  $rows
     */
    private function buildUserPdfTable(
        float $x,
        float $topY,
        array $columnWidths,
        array $headers,
        array $rows,
    ): string {
        $headerHeight = 24.0;
        $maxTableHeight = max(180.0, $topY - 72.0);
        $rowCount = max(1, count($rows));
        $rowHeight = max(
            11.5,
            min(
                20.0,
                ($maxTableHeight - $headerHeight) / $rowCount,
            ),
        );
        $bodyFontSize = max(6.8, min(8.5, $rowHeight - 4.0));
        $tableWidth = array_sum($columnWidths);
        $tableHeight = $headerHeight + (count($rows) * $rowHeight);
        $bottomY = $topY - $tableHeight;
        $stream = '';
        $borderColor = [203, 213, 225];
        $gridColor = [226, 232, 240];
        $headerFill = [30, 41, 59];
        $rowFill = [248, 250, 252];
        $textColor = [17, 24, 39];

        $stream .= $this->pdfRect(
            $x,
            $topY - $headerHeight,
            $tableWidth,
            $headerHeight,
            fillColor: $headerFill,
            lineWidth: 0,
        );

        foreach ($rows as $rowIndex => $row) {
            $rowTop = $topY - $headerHeight - ($rowIndex * $rowHeight);
            $rowBottom = $rowTop - $rowHeight;

            if ($rowIndex % 2 === 0) {
                $stream .= $this->pdfRect($x, $rowBottom, $tableWidth, $rowHeight, fillColor: $rowFill, lineWidth: 0);
            }
        }

        $stream .= $this->pdfRect(
            $x,
            $bottomY,
            $tableWidth,
            $tableHeight,
            strokeColor: $borderColor,
            lineWidth: 0.8,
        );

        $currentX = $x;
        foreach ($columnWidths as $width) {
            $currentX += $width;
            if ($currentX < ($x + $tableWidth)) {
                $stream .= $this->pdfLine($currentX, $bottomY, $currentX, $topY, $gridColor, 0.6);
            }
        }

        $stream .= $this->pdfLine($x, $topY - $headerHeight, $x + $tableWidth, $topY - $headerHeight, $gridColor, 0.6);

        for ($index = 1; $index < count($rows); $index++) {
            $lineY = $topY - $headerHeight - ($index * $rowHeight);
            $stream .= $this->pdfLine($x, $lineY, $x + $tableWidth, $lineY, $gridColor, 0.6);
        }

        $headerX = $x;
        foreach ($headers as $index => $header) {
            $stream .= $this->pdfText(
                $headerX + 6,
                $topY - (($headerHeight - 10) / 2) - 1.5,
                $header,
                9,
                'F2',
                [255, 255, 255],
            );
            $headerX += $columnWidths[$index];
        }

        foreach ($rows as $rowIndex => $row) {
            $rowTop = $topY - $headerHeight - ($rowIndex * $rowHeight);
            $textY = $rowTop - (($rowHeight - $bodyFontSize) / 2) - 1.5;
            $cellX = $x;
            $values = [
                $row['date'] ?? '',
                $row['time_in'] ?? '',
                $row['time_out'] ?? '',
                $row['hours'] ?? '',
                $row['status'] ?? '',
            ];

            foreach ($values as $index => $value) {
                $isStatusColumn = $index === 4;
                $font = $isStatusColumn ? 'F2' : 'F1';
                $color = $isStatusColumn ? $this->attendanceStatusColor($value) : $textColor;
                $stream .= $this->pdfText(
                    $cellX + 6,
                    $textY,
                    $this->fitPdfText($value, $columnWidths[$index] - 12, $bodyFontSize, $font),
                    $bodyFontSize,
                    $font,
                    $color,
                );
                $cellX += $columnWidths[$index];
            }
        }

        return $stream;
    }

    /**
     * @param  array<string, mixed>  $day
     */
    private function attendanceStatusLabel(array $day): string
    {
        $hasTimeIn = filled($day['time_in'] ?? null);
        $hasTimeOut = filled($day['time_out'] ?? null);

        return match (true) {
            $hasTimeIn && $hasTimeOut => 'Complete',
            $hasTimeIn => 'Time In only',
            $hasTimeOut => 'Time Out only',
            default => 'No attendance recorded',
        };
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function accountStatusPalette(string $status): array
    {
        return match (strtolower(trim($status))) {
            'active' => [[236, 253, 245], [22, 101, 52]],
            'inactive' => [[254, 242, 242], [153, 27, 27]],
            default => [[255, 247, 237], [180, 83, 9]],
        };
    }

    /**
     * @return array<int, int>
     */
    private function attendanceStatusColor(string $status): array
    {
        return match ($status) {
            'Complete' => [22, 101, 52],
            'Time In only', 'Time Out only' => [180, 83, 9],
            default => [153, 27, 27],
        };
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildPdfHeader(
        string $generatedAt,
        array $period,
        string $section,
        string $subtitle,
        int $pageNumber,
        int $totalPages,
    ): string {
        $periodLabel = (string) ($period['label'] ?? '');
        $generatedLabel = CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A');
        $stream = '';

        $stream .= $this->pdfRect(30, 722, 552, 42, fillColor: [19, 41, 75], lineWidth: 0);
        $stream .= $this->pdfRect(30, 716, 552, 6, fillColor: [63, 120, 190], lineWidth: 0);
        $stream .= $this->pdfText(46, 747, "Elmar's Team PH Attendance Backup", 16, 'F2', [255, 255, 255]);
        $stream .= $this->pdfText(46, 732, $subtitle, 9, 'F1', [226, 232, 240]);
        $stream .= $this->pdfRect(462, 734, 102, 18, fillColor: [239, 246, 255], lineWidth: 0);
        $stream .= $this->pdfText(474, 741, 'Page '.$pageNumber.' / '.$totalPages, 9, 'F2', [19, 41, 75]);
        $stream .= $this->pdfText(474, 727, $this->fitPdfText($section, 78, 8.5, 'F1'), 8.5, 'F1', [191, 219, 254]);
        $stream .= $this->buildPdfMetadataCard(30, 674, 176, 34, 'Period', $periodLabel);
        $stream .= $this->buildPdfMetadataCard(218, 674, 176, 34, 'Generated At', $generatedLabel);
        $stream .= $this->buildPdfMetadataCard(406, 674, 176, 34, 'Section', $section);

        return $stream;
    }

    private function buildPdfFooter(string $generatedAt, int $pageNumber, int $totalPages): string
    {
        $generatedLabel = CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A');
        $stream = '';

        $stream .= $this->pdfLine(30, 46, 582, 46, [203, 213, 225], 0.8);
        $stream .= $this->pdfText(30, 28, 'Prepared by the attendance backup system', 8, 'F1', [100, 116, 139]);
        $stream .= $this->pdfText(250, 28, 'Generated '.$generatedLabel, 8, 'F1', [100, 116, 139]);
        $stream .= $this->pdfText(510, 28, $pageNumber.'/'.$totalPages, 8, 'F2', [71, 85, 105]);

        return $stream;
    }

    private function buildPdfMetadataCard(
        float $x,
        float $y,
        float $width,
        float $height,
        string $label,
        string $value,
    ): string {
        $stream = '';

        $stream .= $this->pdfRect(
            $x,
            $y,
            $width,
            $height,
            strokeColor: [203, 213, 225],
            fillColor: [247, 250, 252],
            lineWidth: 0.8,
        );
        $stream .= $this->buildPdfCenteredTextStack(
            $x,
            $y,
            $width,
            $height,
            $label,
            $value,
            7.25,
            9.5,
            'F2',
            'F1',
            [71, 85, 105],
            [17, 24, 39],
            3.0,
            12.0,
        );

        return $stream;
    }

    private function buildPdfTextPair(
        float $x,
        float $labelY,
        float $maxWidth,
        string $label,
        string $value,
    ): string {
        $stream = '';

        $stream .= $this->pdfText($x, $labelY, $label, 8, 'F1', [100, 116, 139]);
        $stream .= $this->pdfText(
            $x,
            $labelY - 13,
            $this->fitPdfText($value, $maxWidth, 10, 'F2'),
            10,
            'F2',
            [17, 24, 39],
        );

        return $stream;
    }

    /**
     * @param  array<int, int>  $fillColor
     * @param  array<int, int>  $accentColor
     */
    private function buildPdfMetricCard(
        float $x,
        float $y,
        float $width,
        float $height,
        string $label,
        string $value,
        array $fillColor,
        array $accentColor,
    ): string {
        $isNumericValue = is_numeric($value);
        $compactCard = $height <= 38.0;
        $labelSize = $compactCard ? 6.75 : 7.25;
        $valueSize = match (true) {
            $compactCard && $isNumericValue => 12.5,
            $compactCard => 8.75,
            $isNumericValue => 16.0,
            default => 9.5,
        };
        $stream = '';

        $stream .= $this->pdfRect(
            $x,
            $y,
            $width,
            $height,
            strokeColor: $accentColor,
            fillColor: $fillColor,
            lineWidth: 0.8,
        );
        $stream .= $this->buildPdfCenteredTextStack(
            $x,
            $y,
            $width,
            $height,
            $label,
            $value,
            $labelSize,
            $valueSize,
            'F2',
            'F2',
            $accentColor,
            [17, 24, 39],
            $compactCard ? 3.0 : 4.0,
            10.0,
        );

        return $stream;
    }

    /**
     * @param  array<int, int>  $labelColor
     * @param  array<int, int>  $valueColor
     */
    private function buildPdfCenteredTextStack(
        float $x,
        float $y,
        float $width,
        float $height,
        string $label,
        string $value,
        float $labelSize,
        float $valueSize,
        string $labelFont,
        string $valueFont,
        array $labelColor,
        array $valueColor,
        float $lineGap,
        float $horizontalPadding,
    ): string {
        $labelText = $this->fitPdfText($label, $width - ($horizontalPadding * 2), $labelSize, $labelFont);
        $valueText = $this->fitPdfText($value, $width - ($horizontalPadding * 2), $valueSize, $valueFont);
        $descentFactor = 0.22;
        $groupHeight = $labelSize + $valueSize + $lineGap;
        $groupBottom = $y + (($height - $groupHeight) / 2);
        $valueBaseline = $groupBottom + ($valueSize * $descentFactor);
        $labelBaseline = $groupBottom + $valueSize + $lineGap + ($labelSize * $descentFactor);
        $centerX = $x + ($width / 2);

        return $this->pdfCenteredText($centerX, $labelBaseline, $labelText, $labelSize, $labelFont, $labelColor)
            .$this->pdfCenteredText($centerX, $valueBaseline, $valueText, $valueSize, $valueFont, $valueColor);
    }

    private function fitPdfText(
        string $value,
        float $maxWidth,
        float $size = 10,
        string $font = 'F1',
    ): string
    {
        if ($this->estimatePdfTextWidth($value, $size, $font) <= $maxWidth) {
            return $value;
        }

        $truncated = $value;

        while ($truncated !== '' && $this->estimatePdfTextWidth($truncated.'...', $size, $font) > $maxWidth) {
            $truncated = substr($truncated, 0, -1);
        }

        return $truncated === '' ? '' : rtrim($truncated).'...';
    }

    private function estimatePdfTextWidth(string $text, float $size, string $font = 'F1'): float
    {
        $width = 0.0;

        foreach (str_split($text) as $character) {
            $factor = match (true) {
                $character === ' ' => 0.28,
                str_contains('.,:;|!ijl\'`', $character) => 0.24,
                str_contains('frt()[]{}', $character) => 0.34,
                str_contains('mwWM@#%&QG', $character) => 0.78,
                ctype_upper($character) => 0.62,
                ctype_digit($character) => 0.56,
                default => 0.5,
            };

            $width += $factor;
        }

        if ($font === 'F2') {
            $width *= 1.05;
        }

        return $width * $size;
    }

    private function pdfText(
        float $x,
        float $y,
        string $text,
        float $size = 10,
        string $font = 'F1',
        ?array $color = null,
    ): string {
        $stream = "q\n";

        if ($color !== null) {
            $stream .= $this->pdfRgb($color, true);
        }

        $stream .= "BT\n/{$font} {$size} Tf\n1 0 0 1 "
            .$this->pdfNumber($x)
            .' '
            .$this->pdfNumber($y)
            ." Tm\n("
            .$this->escapePdfString($text)
            .") Tj\nET\nQ\n";

        return $stream;
    }

    private function pdfCenteredText(
        float $centerX,
        float $y,
        string $text,
        float $size = 10,
        string $font = 'F1',
        ?array $color = null,
    ): string {
        $x = max(0.0, $centerX - ($this->estimatePdfTextWidth($text, $size, $font) / 2));

        return $this->pdfText($x, $y, $text, $size, $font, $color);
    }

    private function pdfRightAlignedText(
        float $rightX,
        float $y,
        string $text,
        float $size = 10,
        string $font = 'F1',
        ?array $color = null,
    ): string {
        $x = max(0.0, $rightX - $this->estimatePdfTextWidth($text, $size, $font));

        return $this->pdfText($x, $y, $text, $size, $font, $color);
    }

    private function pdfRect(
        float $x,
        float $y,
        float $width,
        float $height,
        ?array $strokeColor = null,
        ?array $fillColor = null,
        float $lineWidth = 1.0,
    ): string
    {
        $operator = match (true) {
            $strokeColor !== null && $fillColor !== null => 'B',
            $fillColor !== null => 'f',
            default => 'S',
        };

        $stream = "q\n";

        if ($strokeColor !== null) {
            $stream .= $this->pdfRgb($strokeColor, false);
        }

        if ($fillColor !== null) {
            $stream .= $this->pdfRgb($fillColor, true);
        }

        if ($strokeColor !== null && $lineWidth > 0) {
            $stream .= $this->pdfNumber($lineWidth)." w\n";
        }

        $stream .= $this->pdfNumber($x)
            .' '
            .$this->pdfNumber($y)
            .' '
            .$this->pdfNumber($width)
            .' '
            .$this->pdfNumber($height)
            ." re {$operator}\nQ\n";

        return $stream;
    }

    private function pdfLine(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        ?array $strokeColor = null,
        float $lineWidth = 1.0,
    ): string
    {
        $stream = "q\n";

        if ($strokeColor !== null) {
            $stream .= $this->pdfRgb($strokeColor, false);
        }

        $stream .= $this->pdfNumber($lineWidth)." w\n";
        $stream .= $this->pdfNumber($x1)
            .' '
            .$this->pdfNumber($y1)
            .' m '
            .$this->pdfNumber($x2)
            .' '
            .$this->pdfNumber($y2)
            ." l S\nQ\n";

        return $stream;
    }

    private function pdfNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array<int, int>  $color
     */
    private function pdfRgb(array $color, bool $fill): string
    {
        $operator = $fill ? 'rg' : 'RG';
        [$red, $green, $blue] = $color;

        return $this->pdfNumber($red / 255)
            .' '
            .$this->pdfNumber($green / 255)
            .' '
            .$this->pdfNumber($blue / 255)
            ." {$operator}\n";
    }

    private function escapePdfString(string $value): string
    {
        $sanitized = preg_replace('/[^\x20-\x7E]/', '?', $value) ?? $value;

        return str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $sanitized,
        );
    }
}
