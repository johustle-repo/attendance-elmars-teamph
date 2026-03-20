<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
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
            ->with([
                'attendances' => fn ($query) => $query
                    ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
                    ->orderBy('recorded_at'),
            ])
            ->orderByRaw("case when role = 'super_admin' then 0 when role = 'admin' then 1 else 2 end")
            ->orderBy('name')
            ->get();

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
        $attendanceDays = $this->buildAttendanceDays($user->attendances);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'role_label' => $user->role?->label(),
            'employee_code' => $user->employee_code,
            'position' => $user->position,
            'qr_value' => $user->qr_value,
            'created_at' => optional($user->created_at)->toIso8601String(),
            'attendance_day_count' => count($attendanceDays),
            'attendance_log_count' => $user->attendances->count(),
            'attendance_days' => $attendanceDays,
        ];
    }

    /**
     * @param  Collection<int, Attendance>  $attendances
     * @return array<int, array<string, mixed>>
     */
    private function buildAttendanceDays(Collection $attendances): array
    {
        return $attendances
            ->groupBy(fn (Attendance $attendance): string => (string) optional($attendance->recorded_at)->toDateString())
            ->map(function (Collection $entries): array {
                /** @var Collection<int, Attendance> $entries */
                $sortedEntries = $entries->sortBy('recorded_at')->values();
                /** @var Attendance $anchor */
                $anchor = $sortedEntries->first();
                $timeIn = $sortedEntries
                    ->first(fn (Attendance $attendance): bool => $attendance->entry_type === 'time_in');
                $timeOut = $sortedEntries
                    ->reverse()
                    ->first(fn (Attendance $attendance): bool => $attendance->entry_type === 'time_out');

                return [
                    'date' => optional($anchor->recorded_at)->toDateString(),
                    'display_date' => optional($anchor->recorded_at)->format('M d, Y'),
                    'time_in' => optional($timeIn?->recorded_at)->format('h:i A'),
                    'time_out' => optional($timeOut?->recorded_at)->format('h:i A'),
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
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $currentYear = Date::now(config('app.timezone'))->year;
        $oldestAttendance = Attendance::query()->oldest('recorded_at')->value('recorded_at');
        $oldestUser = User::query()->oldest('created_at')->value('created_at');

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
        $rows = collect($dataset['users'])
            ->flatMap(function (array $user): Collection {
                if (empty($user['attendance_days'])) {
                    return collect([[
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role_label'] ?? $user['role'],
                        'employee_code' => $user['employee_code'] ?? '',
                        'position' => $user['position'] ?? '',
                        'date' => '',
                        'time_in' => '',
                        'time_out' => '',
                        'logs' => '',
                    ]]);
                }

                return collect($user['attendance_days'])->map(
                    fn (array $day): array => [
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'role' => $user['role_label'] ?? $user['role'],
                        'employee_code' => $user['employee_code'] ?? '',
                        'position' => $user['position'] ?? '',
                        'date' => $day['display_date'] ?? '',
                        'time_in' => $day['time_in'] ?? '',
                        'time_out' => $day['time_out'] ?? '',
                        'logs' => collect($day['logs'] ?? [])
                            ->map(
                                fn (array $log): string => ($log['entry_type_label'] ?? 'Attendance')
                                    .' - '
                                    .($log['recorded_time'] ?? '')
                            )
                            ->implode(', '),
                    ],
                );
            })
            ->map(function (array $row): string {
                $cells = [
                    $row['name'],
                    $row['email'],
                    $row['role'],
                    $row['employee_code'],
                    $row['position'],
                    $row['date'],
                    $row['time_in'],
                    $row['time_out'],
                    $row['logs'],
                ];

                $cellXml = collect($cells)
                    ->map(
                        fn (string $value): string => '<Cell><Data ss:Type="String">'
                            .$this->escapeXml($value)
                            .'</Data></Cell>',
                    )
                    ->implode('');

                return '<Row>'.$cellXml.'</Row>';
            })
            ->implode('');

        $periodLabel = $this->escapeXml((string) ($dataset['period']['label'] ?? ''));
        $generatedLabel = $this->escapeXml(CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A'));

        return <<<XML
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="Backup">
  <Table>
   <Row>
    <Cell><Data ss:Type="String">Elmar's Team PH Backup</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">Period: {$periodLabel}</Data></Cell>
   </Row>
   <Row>
    <Cell><Data ss:Type="String">Generated at: {$generatedLabel}</Data></Cell>
   </Row>
   <Row></Row>
   <Row>
    <Cell><Data ss:Type="String">Name</Data></Cell>
    <Cell><Data ss:Type="String">Email</Data></Cell>
    <Cell><Data ss:Type="String">Role</Data></Cell>
    <Cell><Data ss:Type="String">Employee Code</Data></Cell>
    <Cell><Data ss:Type="String">Position</Data></Cell>
    <Cell><Data ss:Type="String">Date</Data></Cell>
    <Cell><Data ss:Type="String">Time In</Data></Cell>
    <Cell><Data ss:Type="String">Time Out</Data></Cell>
    <Cell><Data ss:Type="String">Logs</Data></Cell>
   </Row>
   {$rows}
   <Row></Row>
   <Row>
    <Cell><Data ss:Type="String">Approved and verified by:</Data></Cell>
   </Row>
   <Row></Row>
   <Row>
    <Cell><Data ss:Type="String">Elmar B. Noche</Data></Cell>
   </Row>
  </Table>
 </Worksheet>
</Workbook>
XML;
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

        $streams = $members
            ->map(
                fn (array $user): string => $this->buildUserPdfPageStream(
                    $generatedAt,
                    $dataset['period'],
                    $user,
                ),
            )
            ->all();

        if (empty($streams)) {
            $streams[] = $this->buildEmptyPdfPageStream($generatedAt, $dataset['period']);
        }

        $streams[] = $this->buildSignatoryPdfPageStream($generatedAt, $dataset['period']);

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

        $pageReferences = [];
        $objectNumber = 4;

        foreach ($pageStreams as $contentStream) {
            $pageObjectNumber = $objectNumber++;
            $contentObjectNumber = $objectNumber++;
            $pageReferences[] = $pageObjectNumber.' 0 R';

            $objects[$pageObjectNumber] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
                .'/Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObjectNumber.' 0 R >>';
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
    private function buildUserPdfPageStream(string $generatedAt, array $period, array $user): string
    {
        $attendanceDays = collect($user['attendance_days'] ?? []);
        $rows = $attendanceDays
            ->map(fn (array $day): array => [
                'date' => $day['display_date'] ?? '-',
                'time_in' => $day['time_in'] ?? 'Not recorded',
                'time_out' => $day['time_out'] ?? 'Not recorded',
                'status' => $this->attendanceStatusLabel($day),
            ])
            ->values()
            ->all();

        if (empty($rows)) {
            $rows[] = [
                'date' => '-',
                'time_in' => 'Not recorded',
                'time_out' => 'Not recorded',
                'status' => 'No attendance records for this month',
            ];
        }

        $stream = '';
        $stream .= $this->pdfText(40, 748, "Elmar's Team PH Attendance Backup", 16);
        $stream .= $this->pdfText(40, 728, 'Monthly member attendance summary', 10);
        $stream .= $this->pdfLine(40, 720, 572, 720);
        $stream .= $this->pdfText(40, 700, 'Period: '.($period['label'] ?? ''), 10);
        $stream .= $this->pdfText(
            40,
            684,
            'Generated at: '.CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A'),
            10,
        );

        $stream .= $this->pdfText(40, 654, 'Member details', 11);
        $stream .= $this->pdfText(40, 632, 'Name: '.($user['name'] ?? ''), 12);
        $stream .= $this->pdfText(
            40,
            616,
            'Employee Code: '.($user['employee_code'] ?? 'Not set'),
            10,
        );
        $stream .= $this->pdfText(
            280,
            616,
            'Position: '.($user['position'] ?? 'Not set'),
            10,
        );
        $stream .= $this->pdfText(40, 600, 'Email: '.($user['email'] ?? ''), 10);
        $stream .= $this->pdfText(
            40,
            580,
            'Attendance days this month: '.$attendanceDays->count(),
            10,
        );

        return $stream.$this->buildUserPdfTable(
            40,
            548,
            [120, 86, 86, 240],
            ['Date', 'Time In', 'Time Out', 'Status'],
            $rows,
        );
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildEmptyPdfPageStream(string $generatedAt, array $period): string
    {
        $stream = '';
        $stream .= $this->pdfText(40, 748, "Elmar's Team PH Attendance Backup", 16);
        $stream .= $this->pdfText(40, 728, 'Monthly member attendance summary', 10);
        $stream .= $this->pdfLine(40, 720, 572, 720);
        $stream .= $this->pdfText(40, 700, 'Period: '.($period['label'] ?? ''), 10);
        $stream .= $this->pdfText(
            40,
            684,
            'Generated at: '.CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A'),
            10,
        );
        $stream .= $this->pdfText(40, 634, 'No member attendance records were found for this month.', 12);

        return $stream;
    }

    /**
     * @param  array<string, mixed>  $period
     */
    private function buildSignatoryPdfPageStream(string $generatedAt, array $period): string
    {
        $stream = '';
        $stream .= $this->pdfText(40, 748, "Elmar's Team PH Attendance Backup", 16);
        $stream .= $this->pdfText(40, 728, 'Approval page', 10);
        $stream .= $this->pdfLine(40, 720, 572, 720);
        $stream .= $this->pdfText(40, 700, 'Period: '.($period['label'] ?? ''), 10);
        $stream .= $this->pdfText(
            40,
            684,
            'Generated at: '.CarbonImmutable::parse($generatedAt)->format('M d, Y h:i A'),
            10,
        );
        $stream .= $this->pdfText(40, 560, 'Approved and verified by:', 14);
        $stream .= $this->pdfLine(40, 470, 240, 470);
        $stream .= $this->pdfText(40, 446, 'Elmar B. Noche', 16);

        return $stream;
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
        $rowHeight = 18.0;
        $headerHeight = 20.0;
        $tableWidth = array_sum($columnWidths);
        $tableHeight = $headerHeight + (count($rows) * $rowHeight);
        $bottomY = $topY - $tableHeight;
        $stream = '';

        $stream .= $this->pdfRect($x, $bottomY, $tableWidth, $tableHeight);

        $currentX = $x;
        foreach ($columnWidths as $width) {
            $currentX += $width;
            if ($currentX < ($x + $tableWidth)) {
                $stream .= $this->pdfLine($currentX, $bottomY, $currentX, $topY);
            }
        }

        $stream .= $this->pdfLine($x, $topY - $headerHeight, $x + $tableWidth, $topY - $headerHeight);

        for ($index = 1; $index < count($rows); $index++) {
            $lineY = $topY - $headerHeight - ($index * $rowHeight);
            $stream .= $this->pdfLine($x, $lineY, $x + $tableWidth, $lineY);
        }

        $headerX = $x;
        foreach ($headers as $index => $header) {
            $stream .= $this->pdfText($headerX + 5, $topY - 13, $header, 9);
            $headerX += $columnWidths[$index];
        }

        foreach ($rows as $rowIndex => $row) {
            $textY = $topY - $headerHeight - ($rowIndex * $rowHeight) - 12;
            $cellX = $x;
            $values = [
                $row['date'] ?? '',
                $row['time_in'] ?? '',
                $row['time_out'] ?? '',
                $row['status'] ?? '',
            ];

            foreach ($values as $index => $value) {
                $stream .= $this->pdfText(
                    $cellX + 5,
                    $textY,
                    $this->fitPdfText($value, $columnWidths[$index] - 8),
                    8,
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

    private function fitPdfText(string $value, float $maxWidth): string
    {
        $maxCharacters = max(1, (int) floor($maxWidth / 4.2));

        return strlen($value) <= $maxCharacters
            ? $value
            : substr($value, 0, max(0, $maxCharacters - 3)).'...';
    }

    private function pdfText(float $x, float $y, string $text, float $size = 10): string
    {
        return "BT\n/F1 {$size} Tf\n1 0 0 1 "
            .$this->pdfNumber($x)
            .' '
            .$this->pdfNumber($y)
            ." Tm\n("
            .$this->escapePdfString($text)
            .") Tj\nET\n";
    }

    private function pdfRect(float $x, float $y, float $width, float $height): string
    {
        return $this->pdfNumber($x)
            .' '
            .$this->pdfNumber($y)
            .' '
            .$this->pdfNumber($width)
            .' '
            .$this->pdfNumber($height)
            ." re S\n";
    }

    private function pdfLine(float $x1, float $y1, float $x2, float $y2): string
    {
        return $this->pdfNumber($x1)
            .' '
            .$this->pdfNumber($y1)
            .' m '
            .$this->pdfNumber($x2)
            .' '
            .$this->pdfNumber($y2)
            ." l S\n";
    }

    private function pdfNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
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
