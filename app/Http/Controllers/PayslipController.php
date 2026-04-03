<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayslipController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->canManageUsers(), 403);

        [$year, $month] = $this->validatedPeriod($request);
        $payslips = $this->buildPayslips($year, $month);

        return Inertia::render('payslips/index', [
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
            'availableYears' => $this->availableYears(),
            'availableMonths' => $this->availableMonths(),
            'periodLabel' => CarbonImmutable::create($year, $month, 1)->format('F Y'),
            'payslips' => $payslips,
            'summary' => [
                'totalEmployees' => count($payslips),
                'totalHours' => round(collect($payslips)->sum('total_hours'), 2),
                'totalPay' => round(collect($payslips)->sum('total_pay'), 2),
            ],
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);

        [$year, $month] = $this->validatedPeriod($request);
        $payslips = $this->buildPayslips($year, $month);
        $periodLabel = CarbonImmutable::create($year, $month, 1)->format('F Y');
        $generatedAt = Date::now(config('app.timezone'))->format('M d, Y h:i A');
        $xml = $this->buildExcelXml($payslips, $periodLabel, $generatedAt);
        $filename = sprintf('payslips-%d-%02d.xls', $year, $month);

        return response()->streamDownload(function () use ($xml): void {
            echo $xml;
        }, $filename, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request, User $user): StreamedResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        abort_unless($user->isVisibleInSystem(), 404);

        [$year, $month] = $this->validatedPeriod($request);
        $payslip = $this->buildUserPayslip($user, $year, $month);
        $periodLabel = CarbonImmutable::create($year, $month, 1)->format('F Y');
        $generatedAt = Date::now(config('app.timezone'))->format('M d, Y h:i A');
        $pdf = $this->buildPdfDocument($this->buildPayslipPdfStream($payslip, $periodLabel, $generatedAt));
        $filename = sprintf('payslip-%s-%d-%02d.pdf', str($user->employee_code ?? 'user-'.$user->id)->slug(), $year, $month);

        return response()->streamDownload(function () use ($pdf): void {
            echo $pdf;
        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    // -------------------------------------------------------------------------
    // Data building
    // -------------------------------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildPayslips(int $year, int $month): array
    {
        $startOfMonth = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
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
            ->sortBy('name')
            ->values();

        return $users->map(fn (User $user): array => $this->buildUserPayslip($user, $year, $month))->all();
    }

    // Day shift windows: 8:00 AM – 12:00 PM and 1:00 PM – 5:00 PM (12–1 PM lunch excluded)
    private const MORNING_START = 8;

    private const MORNING_END = 12;

    private const AFTERNOON_START = 13;

    private const AFTERNOON_END = 17;

    // Night shift window: 6:00 PM – 9:00 PM (Fiona and Maria only)
    private const NIGHT_SHIFT_START = 18;

    private const NIGHT_SHIFT_END = 21;

    /**
     * @return array<string, mixed>
     */
    private function buildUserPayslip(User $user, int $year, int $month): array
    {
        $startOfMonth = CarbonImmutable::create($year, $month, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
        $endOfMonth = $startOfMonth->endOfMonth();

        if (! $user->relationLoaded('attendances')) {
            $user->load([
                'attendances' => fn ($query) => $query
                    ->whereBetween('recorded_at', [$startOfMonth, $endOfMonth])
                    ->orderBy('recorded_at'),
            ]);
        }

        $nightShiftEligible = (bool) $user->night_shift_eligible;
        $attendanceDays = $this->buildAttendanceDays($user->attendances, $nightShiftEligible);
        $totalMinutes = (int) collect($attendanceDays)->sum(fn (array $day): int => (int) ($day['worked_minutes'] ?? 0));
        $totalHours = round($totalMinutes / 60, 2);
        $hourlyRate = (float) ($user->hourly_rate ?? 2.00);
        $totalPay = round($totalHours * $hourlyRate, 2);

        return [
            'user_id' => $user->id,
            'name' => $user->name,
            'sub_name' => $user->sub_name,
            'email' => $user->email,
            'employee_code' => $user->employee_code,
            'position' => $user->position,
            'hourly_rate' => $hourlyRate,
            'night_shift_eligible' => $nightShiftEligible,
            'days_worked' => count($attendanceDays),
            'total_minutes' => $totalMinutes,
            'total_hours' => $totalHours,
            'total_pay' => $totalPay,
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
            ->groupBy(fn (Attendance $a): string => (string) optional($a->recorded_at)->toDateString())
            ->map(function (Collection $entries) use ($nightShiftEligible): array {
                /** @var Collection<int, Attendance> $entries */
                $sorted = $entries->sortBy('recorded_at')->values();
                /** @var Attendance $anchor */
                $anchor = $sorted->first();
                $timeIn = $sorted->first(fn (Attendance $a): bool => $a->entry_type === 'time_in');
                $timeOut = $sorted->reverse()->first(fn (Attendance $a): bool => $a->entry_type === 'time_out');

                $workedMinutes = $this->shiftWindowMinutes(
                    $timeIn?->recorded_at,
                    $timeOut?->recorded_at,
                    $nightShiftEligible,
                );

                return [
                    'date' => optional($anchor->recorded_at)->toDateString(),
                    'display_date' => optional($anchor->recorded_at)->format('M d, Y'),
                    'time_in' => optional($timeIn?->recorded_at)->format('h:i A'),
                    'time_out' => optional($timeOut?->recorded_at)->format('h:i A'),
                    'worked_minutes' => $workedMinutes,
                    'worked_hours' => $workedMinutes !== null ? round($workedMinutes / 60, 2) : null,
                    'worked_hours_label' => $workedMinutes !== null ? $this->formatMinutes($workedMinutes) : 'Incomplete',
                ];
            })
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * Calculate billable minutes by clipping the attendance window to allowed shift windows.
     * Morning (all): 08:00–12:00. Afternoon (all): 13:00–17:00. Night (eligible only): 18:00–21:00.
     * 12:00–13:00 lunch break is excluded from all calculations.
     */
    private function shiftWindowMinutes(
        ?CarbonInterface $timeIn,
        ?CarbonInterface $timeOut,
        bool $nightShiftEligible = false,
    ): ?int {
        if (! $timeIn || ! $timeOut || $timeOut->lessThan($timeIn)) {
            return null;
        }

        $tz = config('app.timezone');
        $date = $timeIn->toDateString();

        $morningStart   = CarbonImmutable::parse($date.' 08:00:00', $tz);
        $morningEnd     = CarbonImmutable::parse($date.' 12:00:00', $tz);
        $afternoonStart = CarbonImmutable::parse($date.' 13:00:00', $tz);
        $afternoonEnd   = CarbonImmutable::parse($date.' 17:00:00', $tz);

        $dayMinutes = $this->overlapMinutes($timeIn, $timeOut, $morningStart, $morningEnd)
                    + $this->overlapMinutes($timeIn, $timeOut, $afternoonStart, $afternoonEnd);

        $nightMinutes = 0;
        if ($nightShiftEligible) {
            $nightStart = CarbonImmutable::parse($date.' 18:00:00', $tz);
            $nightEnd   = CarbonImmutable::parse($date.' 21:00:00', $tz);
            $nightMinutes = $this->overlapMinutes($timeIn, $timeOut, $nightStart, $nightEnd);
        }

        return $dayMinutes + $nightMinutes;
    }

    /**
     * Calculate the overlap (in minutes) between [timeIn, timeOut] and [windowStart, windowEnd].
     */
    private function overlapMinutes(
        CarbonInterface $timeIn,
        CarbonInterface $timeOut,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
    ): int {
        $effectiveStart = $timeIn->greaterThan($windowStart) ? $timeIn : $windowStart;
        $effectiveEnd = $timeOut->lessThan($windowEnd) ? $timeOut : $windowEnd;

        if ($effectiveEnd->lessThanOrEqualTo($effectiveStart)) {
            return 0;
        }

        return (int) $effectiveStart->diffInMinutes($effectiveEnd);
    }

    private function formatMinutes(int $minutes): string
    {
        return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
    }

    // -------------------------------------------------------------------------
    // Period helpers
    // -------------------------------------------------------------------------

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

    /**
     * @return array<int, int>
     */
    private function availableYears(): array
    {
        $currentYear = Date::now(config('app.timezone'))->year;
        $oldestUser = User::query()->visibleInSystem()->oldest('created_at')->value('created_at');
        $startYear = $oldestUser ? CarbonImmutable::parse($oldestUser)->year : $currentYear;

        return collect(range($startYear, $currentYear))->reverse()->values()->all();
    }

    /**
     * @return array<int, array{value:int,label:string}>
     */
    private function availableMonths(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $m): array => [
                'value' => $m,
                'label' => CarbonImmutable::create(2026, $m, 1)->format('F'),
            ])
            ->values()
            ->all();
    }

    // -------------------------------------------------------------------------
    // Excel (SpreadsheetML)
    // -------------------------------------------------------------------------

    /**
     * @param  array<int, array<string, mixed>>  $payslips
     */
    private function buildExcelXml(array $payslips, string $periodLabel, string $generatedAt): string
    {
        $summaryRows = collect([
            ['Payslips - '.$periodLabel],
            ['Generated at', $generatedAt],
            [],
            ['Employee Code', 'Name', 'Position', 'Hourly Rate ($)', 'Days Worked', 'Total Hours', 'Total Pay ($)'],
        ])
            ->map(fn (array $cells): string => $this->excelRow($cells))
            ->implode("\n");

        $dataRows = collect($payslips)
            ->map(fn (array $p): string => $this->excelRow([
                (string) ($p['employee_code'] ?? 'USER-'.$p['user_id']),
                (string) ($p['name'] ?? ''),
                (string) ($p['position'] ?? ''),
                number_format((float) ($p['hourly_rate'] ?? 0), 2),
                (string) ($p['days_worked'] ?? 0),
                number_format((float) ($p['total_hours'] ?? 0), 2),
                number_format((float) ($p['total_pay'] ?? 0), 2),
            ]))
            ->implode("\n");

        $totalPay = number_format(collect($payslips)->sum('total_pay'), 2);
        $totalHours = number_format(collect($payslips)->sum('total_hours'), 2);

        $footerRows = collect([
            [],
            ['', '', '', '', 'TOTAL', $totalHours, $totalPay],
            [],
            ['Approved and verified by:'],
            [],
            ['Elmar B. Noche'],
        ])
            ->map(fn (array $cells): string => $this->excelRow($cells))
            ->implode("\n");

        $detailWorksheets = collect($payslips)
            ->map(fn (array $p): string => $this->buildDetailWorksheet($p, $periodLabel))
            ->implode("\n");

        return <<<XML
<?xml version="1.0"?>
<?mso-application progid="Excel.Sheet"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
 xmlns:o="urn:schemas-microsoft-com:office:office"
 xmlns:x="urn:schemas-microsoft-com:office:excel"
 xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">
 <Worksheet ss:Name="Summary">
  <Table>
{$summaryRows}
{$dataRows}
{$footerRows}
  </Table>
 </Worksheet>
{$detailWorksheets}
</Workbook>
XML;
    }

    /**
     * @param  array<string, mixed>  $payslip
     */
    private function buildDetailWorksheet(array $payslip, string $periodLabel): string
    {
        $name = (string) ($payslip['name'] ?? 'User');
        $sheetName = $this->escapeXml(mb_substr($name, 0, 31));

        $headerRows = collect([
            [$name.' - '.$periodLabel],
            ['Employee Code', (string) ($payslip['employee_code'] ?? '')],
            ['Position', (string) ($payslip['position'] ?? '')],
            ['Hourly Rate', '$'.number_format((float) ($payslip['hourly_rate'] ?? 0), 2)],
            [],
            ['Date', 'Time In', 'Time Out', 'Hours Worked'],
        ])
            ->map(fn (array $cells): string => $this->excelRow($cells))
            ->implode("\n");

        $days = collect($payslip['attendance_days'] ?? []);
        $dayRows = $days->isEmpty()
            ? $this->excelRow(['-', 'No records', 'No records', '0h 00m'])
            : $days->map(fn (array $d): string => $this->excelRow([
                (string) ($d['display_date'] ?? ''),
                (string) ($d['time_in'] ?? 'Not recorded'),
                (string) ($d['time_out'] ?? 'Not recorded'),
                (string) ($d['worked_hours_label'] ?? 'Incomplete'),
            ]))->implode("\n");

        $footerRows = collect([
            [],
            ['', '', 'Total Hours', number_format((float) ($payslip['total_hours'] ?? 0), 2).'h'],
            ['', '', 'Total Pay', '$'.number_format((float) ($payslip['total_pay'] ?? 0), 2)],
        ])
            ->map(fn (array $cells): string => $this->excelRow($cells))
            ->implode("\n");

        return <<<XML
 <Worksheet ss:Name="{$sheetName}">
  <Table>
{$headerRows}
{$dayRows}
{$footerRows}
  </Table>
 </Worksheet>
XML;
    }

    /**
     * @param  array<int, string>  $cells
     */
    private function excelRow(array $cells): string
    {
        if ($cells === []) {
            return '   <Row></Row>';
        }

        $xml = collect($cells)
            ->map(fn (string $v): string => '<Cell><Data ss:Type="String">'.$this->escapeXml($v).'</Data></Cell>')
            ->implode('');

        return '   <Row>'.$xml.'</Row>';
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    // -------------------------------------------------------------------------
    // PDF (raw PDF binary, no external library)
    // -------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $payslip
     */
    private function buildPayslipPdfStream(array $payslip, string $periodLabel, string $generatedAt): string
    {
        $name         = (string) ($payslip['name'] ?? '');
        $subName      = (string) ($payslip['sub_name'] ?? '');
        $email        = (string) ($payslip['email'] ?? '');
        $employeeCode = (string) ($payslip['employee_code'] ?? 'N/A');
        $position     = (string) ($payslip['position'] ?? 'Team Member');
        $hourlyRate   = '$'.number_format((float) ($payslip['hourly_rate'] ?? 0), 2);
        $daysWorked   = (string) ($payslip['days_worked'] ?? 0).' days';
        $totalHours   = number_format((float) ($payslip['total_hours'] ?? 0), 2).' hrs';
        $totalPay     = '$'.number_format((float) ($payslip['total_pay'] ?? 0), 2);
        $days         = collect($payslip['attendance_days'] ?? []);
        $nightEligible = (bool) ($payslip['night_shift_eligible'] ?? false);
        $shiftLabel   = $nightEligible ? 'Day 8-12AM + 1-5PM + Night 6-9PM' : 'Day 8-12AM + 1-5PM';

        // ── colour palette ───────────────────────────────────────────────────
        $navy     = [15,  23,  42];
        $ink      = [17,  24,  39];
        $steel    = [30,  41,  59];
        $muted    = [71,  85, 105];
        $subtle   = [100, 116, 139];
        $border   = [203, 213, 225];
        $divider  = [226, 232, 240];
        $rowAlt   = [248, 250, 252];
        $white    = [255, 255, 255];
        $accent   = [37,  99, 235];   // blue-600
        $green    = [22, 101,  52];
        $teal     = [8,  145, 178];
        $ghost    = [148, 163, 184];

        $s = '';

        // ── page background ──────────────────────────────────────────────────
        $s .= $this->pdfRect(0, 0, 612, 792, fillColor: [250, 251, 252], lineWidth: 0);

        // ── top header band (full width) ─────────────────────────────────────
        $s .= $this->pdfRect(0, 752, 612, 40, fillColor: $navy, lineWidth: 0);
        // accent stripe on left edge
        $s .= $this->pdfRect(0, 752, 4, 40, fillColor: $accent, lineWidth: 0);
        $s .= $this->pdfText(18, 781, 'PAYSLIP', 16, 'F2', $white);
        $s .= $this->pdfText(18, 766, $periodLabel, 8.5, 'F1', $ghost);
        // company / system name right-aligned area
        $s .= $this->pdfText(390, 781, "Elmar's Team PH", 9, 'F2', $white);
        $s .= $this->pdfText(390, 768, 'Generated: '.$generatedAt, 7.5, 'F1', $ghost);

        // ── thin accent rule below header ────────────────────────────────────
        $s .= $this->pdfRect(0, 750, 612, 2, fillColor: $accent, lineWidth: 0);

        // ── SECTION: Employee ─────────────────────────────────────────────────
        $empCardTop = 740;
        $empCardH   = 118;
        $empCardBot = $empCardTop - $empCardH;

        // card shadow simulation (slightly offset dark rect)
        $s .= $this->pdfRect(32, $empCardBot - 2, 550, $empCardH, fillColor: [220, 225, 235], lineWidth: 0);
        // card
        $s .= $this->pdfRect(30, $empCardBot, 550, $empCardH, strokeColor: $border, fillColor: $white, lineWidth: 0.6);
        // left accent stripe
        $s .= $this->pdfRect(30, $empCardBot, 4, $empCardH, fillColor: $accent, lineWidth: 0);
        // section label banner
        $s .= $this->pdfRect(34, $empCardTop - 18, 546, 18, fillColor: [239, 246, 255], lineWidth: 0);
        $s .= $this->pdfText(42, $empCardTop - 13, 'EMPLOYEE', 7.5, 'F2', $accent);

        // name + email
        $nameY = $empCardTop - 32;
        $s .= $this->pdfText(42, $nameY, $this->fitText($name, 350, 14, 'F2'), 14, 'F2', $ink);
        if ($subName !== '') {
            $s .= $this->pdfText(42, $nameY - 14, $this->fitText($subName, 350, 8.5), 8.5, 'F1', $teal);
            $s .= $this->pdfText(42, $nameY - 26, $this->fitText($email, 350, 8.5), 8.5, 'F1', $muted);
        } else {
            $s .= $this->pdfText(42, $nameY - 14, $this->fitText($email, 350, 8.5), 8.5, 'F1', $muted);
        }

        // divider line between name block and detail row
        $detailY = $empCardBot + 42;
        $s .= $this->pdfLine(42, $detailY + 14, 572, $detailY + 14, $divider, 0.4);

        // detail fields: code | position | shift
        $s .= $this->pdfFieldPill(42,  $detailY, 130, 'Employee Code', $employeeCode, $ink);
        $s .= $this->pdfFieldPill(188, $detailY, 160, 'Position', $position, $ink);
        $s .= $this->pdfFieldPill(364, $detailY, 208, 'Shift Window', $shiftLabel, $teal);

        // ── SECTION: Pay Summary ─────────────────────────────────────────────
        $payCardTop = $empCardBot - 14;
        $payCardH   = 74;
        $payCardBot = $payCardTop - $payCardH;

        $s .= $this->pdfRect(32, $payCardBot - 2, 550, $payCardH, fillColor: [220, 225, 235], lineWidth: 0);
        $s .= $this->pdfRect(30, $payCardBot, 550, $payCardH, strokeColor: $border, fillColor: $white, lineWidth: 0.6);
        $s .= $this->pdfRect(30, $payCardBot, 4, $payCardH, fillColor: [22, 163, 74], lineWidth: 0); // green stripe
        $s .= $this->pdfRect(34, $payCardTop - 18, 546, 18, fillColor: [240, 253, 244], lineWidth: 0);
        $s .= $this->pdfText(42, $payCardTop - 13, 'PAY SUMMARY', 7.5, 'F2', [22, 163, 74]);

        // 4 metric blocks inside the pay card
        $colW = 137;
        $metrics = [
            ['Hourly Rate',  $hourlyRate,  $green,  [236, 253, 245]],
            ['Days Worked',  $daysWorked,  $accent, [239, 246, 255]],
            ['Total Hours',  $totalHours,  $steel,  [248, 250, 252]],
            ['Gross Pay',    $totalPay,    $green,  [240, 253, 244]],
        ];
        foreach ($metrics as $i => [$label, $val, $tc, $bg]) {
            $mx = 34 + $i * $colW;
            $mw = $colW - ($i < 3 ? 4 : 0);
            $s .= $this->pdfRect($mx, $payCardBot + 4, $mw, $payCardH - 22, fillColor: $bg, lineWidth: 0);
            // right divider except last
            if ($i < 3) {
                $s .= $this->pdfLine($mx + $mw, $payCardBot + 8, $mx + $mw, $payCardBot + $payCardH - 22, $divider, 0.5);
            }
            $s .= $this->pdfText($mx + 8, $payCardBot + 38, $label, 7, 'F1', $subtle);
            $s .= $this->pdfText($mx + 8, $payCardBot + 22, $this->fitText($val, $mw - 16, 13, 'F2'), 13, 'F2', $tc);
        }

        // ── SECTION: Attendance Log ──────────────────────────────────────────
        $tableTop = $payCardBot - 18;

        $s .= $this->pdfText(30, $tableTop, 'ATTENDANCE LOG', 8.5, 'F2', $steel);
        $s .= $this->pdfText(30, $tableTop - 12, 'Billable hours clipped to shift window. First time-in and last time-out per day.', 7.5, 'F1', $subtle);

        $tableRows = $days->isEmpty()
            ? [['Date' => '-', 'Time In' => 'No records', 'Time Out' => '-', 'Billable Hrs' => '-', 'Status' => 'No attendance this month']]
            : $days->map(fn (array $d): array => [
                'Date'         => (string) ($d['display_date'] ?? '-'),
                'Time In'      => (string) ($d['time_in'] ?? '-'),
                'Time Out'     => (string) ($d['time_out'] ?? '-'),
                'Billable Hrs' => (string) ($d['worked_hours_label'] ?? 'Incomplete'),
                'Status'       => (filled($d['time_in'] ?? null) && filled($d['time_out'] ?? null)) ? 'Complete' : 'Incomplete',
            ])->values()->all();

        // Dynamically position signatories just below the table so there's no large gap.
        // Table first-row Y = (tableTop-20) - headerH(18) - rowH(16) = tableTop - 54
        $firstRowY    = $tableTop - 54;
        $numRows      = count($tableRows);
        $lastRowBot   = $firstRowY - ($numRows - 1) * 16; // bottom edge of last row rect
        $sigY         = (int) max($lastRowBot - 44, 130);  // 44px breathing room; min 130
        $tableMinY    = $sigY - 30;                        // table stops before signatory space

        $s .= $this->buildPdfTable(30, $tableTop - 20, [118, 96, 96, 98, 144], ['Date', 'Time In', 'Time Out', 'Billable Hrs', 'Status'], $tableRows, (float) $tableMinY);

        // ── SECTION: Signatories ─────────────────────────────────────────────
        $sigBoxH = 56;
        $sigBoxW = 160;

        // prepared-by box
        $s .= $this->pdfRect(30, $sigY - 36, $sigBoxW, $sigBoxH, strokeColor: $border, fillColor: $white, lineWidth: 0.5);
        $s .= $this->pdfRect(30, $sigY + 16, $sigBoxW, 4, fillColor: $accent, lineWidth: 0);
        $s .= $this->pdfText(38, $sigY + 8, 'Prepared & Verified by', 7, 'F1', $subtle);
        $s .= $this->pdfText(38, $sigY - 4, 'Elmar B. Noche', 9.5, 'F2', $ink);
        $s .= $this->pdfText(38, $sigY - 17, 'Team Administrator', 7.5, 'F1', $muted);

        // employee-signature box
        $s .= $this->pdfRect(422, $sigY - 36, $sigBoxW, $sigBoxH, strokeColor: $border, fillColor: $white, lineWidth: 0.5);
        $s .= $this->pdfRect(422, $sigY + 16, $sigBoxW, 4, fillColor: [226, 232, 240], lineWidth: 0);
        $s .= $this->pdfText(430, $sigY + 8, 'Employee Signature', 7, 'F1', $subtle);
        $s .= $this->pdfText(430, $sigY - 4, $this->fitText($name, 144, 9.5, 'F2'), 9.5, 'F2', $ink);
        $s .= $this->pdfText(430, $sigY - 17, 'Signature above', 7.5, 'F1', $muted);

        // ── footer rule + text ───────────────────────────────────────────────
        $s .= $this->pdfRect(0, 46, 612, 1, fillColor: $divider, lineWidth: 0);
        $s .= $this->pdfRect(0, 46, 4, 24, fillColor: $accent, lineWidth: 0);
        $s .= $this->pdfText(12, 56, "Elmar's Team PH  |  System-generated payslip  |  Period: {$periodLabel}", 7, 'F1', $ghost);
        $s .= $this->pdfText(12, 46, $employeeCode.' - '.$name, 6.5, 'F1', $ghost);
        $s .= $this->pdfText(530, 56, 'Page 1 / 1', 7, 'F1', $ghost);

        return $s;
    }

    /**
     * Renders a labelled field pill: small label on top, bold value below.
     *
     * @param  array{0:int,1:int,2:int}  $valueColor
     */
    private function pdfFieldPill(float $x, float $y, float $maxWidth, string $label, string $value, array $valueColor): string
    {
        $s = $this->pdfText($x, $y + 11, $label, 6.5, 'F1', [148, 163, 184]);
        $s .= $this->pdfText($x, $y, $this->fitText($value, $maxWidth, 8.5, 'F2'), 8.5, 'F2', $valueColor);

        return $s;
    }

    // -------------------------------------------------------------------------
    // PDF primitives (same approach as BackupController)
    // -------------------------------------------------------------------------

    private function buildPdfDocument(string $contentStream): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
        $objects[5] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
            .'/Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents 6 0 R >>';
        $objects[6] = "<< /Length ".strlen($contentStream)." >>\nstream\n"
            .$contentStream."\nendstream";
        $objects[2] = '<< /Type /Pages /Count 1 /Kids [5 0 R ] >>';
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
     * @param  array{0:int,1:int,2:int}  $color
     */
    private function pdfText(float $x, float $y, string $text, float $size, string $font, array $color): string
    {
        [$r, $g, $b] = $color;
        $rf = round($r / 255, 4);
        $gf = round($g / 255, 4);
        $bf = round($b / 255, 4);
        $safe = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);

        return "BT /{$font} {$size} Tf {$rf} {$gf} {$bf} rg {$x} {$y} Td ({$safe}) Tj ET\n";
    }

    /**
     * @param  array{0:int,1:int,2:int}|null  $strokeColor
     * @param  array{0:int,1:int,2:int}|null  $fillColor
     */
    private function pdfRect(
        float $x,
        float $y,
        float $w,
        float $h,
        ?array $strokeColor = null,
        ?array $fillColor = null,
        float $lineWidth = 1.0,
    ): string {
        $s = "{$lineWidth} w\n";

        if ($strokeColor) {
            [$r, $g, $b] = $strokeColor;
            $s .= round($r / 255, 4).' '.round($g / 255, 4).' '.round($b / 255, 4)." RG\n";
        }

        if ($fillColor) {
            [$r, $g, $b] = $fillColor;
            $s .= round($r / 255, 4).' '.round($g / 255, 4).' '.round($b / 255, 4)." rg\n";
        }

        $op = ($strokeColor && $fillColor) ? 'B' : ($fillColor ? 'f' : 'S');
        $s .= "{$x} {$y} {$w} {$h} re {$op}\n";

        return $s;
    }

    /**
     * @param  array{0:int,1:int,2:int}  $color
     */
    private function pdfLine(float $x1, float $y1, float $x2, float $y2, array $color, float $lineWidth = 0.5): string
    {
        [$r, $g, $b] = $color;

        return round($r / 255, 4).' '.round($g / 255, 4).' '.round($b / 255, 4)." RG\n"
            ."{$lineWidth} w\n"
            ."{$x1} {$y1} m {$x2} {$y2} l S\n";
    }

    private function pdfTextPair(float $x, float $y, float $maxWidth, string $label, string $value): string
    {
        $s = $this->pdfText($x, $y, $label, 7.5, 'F1', [100, 116, 139]);
        $s .= $this->pdfText($x, $y - 11, $this->fitText($value, $maxWidth, 9), 9, 'F2', [17, 24, 39]);

        return $s;
    }

    /**
     * @param  array{0:int,1:int,2:int}  $fillColor
     * @param  array{0:int,1:int,2:int}  $textColor
     */
    private function pdfMetricCard(
        float $x,
        float $y,
        float $w,
        float $h,
        string $label,
        string $value,
        array $fillColor,
        array $textColor,
    ): string {
        $s = $this->pdfRect($x, $y, $w, $h, strokeColor: [203, 213, 225], fillColor: $fillColor, lineWidth: 0.6);
        $s .= $this->pdfText($x + 6, $y + $h - 13, $label, 7, 'F1', [100, 116, 139]);
        $s .= $this->pdfText($x + 6, $y + 7, $this->fitText($value, $w - 12, 11, 'F2'), 11, 'F2', $textColor);

        return $s;
    }

    /**
     * @param  array<int, int>  $colWidths
     * @param  array<int, string>  $headers
     * @param  array<int, array<string, string>>  $rows
     */
    private function buildPdfTable(
        float $x,
        float $startY,
        array $colWidths,
        array $headers,
        array $rows,
        float $minY = 110,
    ): string {
        $totalWidth = array_sum($colWidths);
        $rowHeight = 16;
        $headerHeight = 18;
        $s = '';
        $y = $startY - $headerHeight;

        // Header row
        $s .= $this->pdfRect($x, $y, $totalWidth, $headerHeight, fillColor: [15, 23, 42], lineWidth: 0);
        $cx = $x + 5;
        foreach ($headers as $i => $header) {
            $s .= $this->pdfText($cx, $y + 6, $header, 8, 'F2', [255, 255, 255]);
            $cx += $colWidths[$i];
        }
        $y -= $rowHeight;

        // Data rows
        foreach ($rows as $ri => $row) {
            $fillColor = $ri % 2 === 0 ? [255, 255, 255] : [248, 250, 252];
            $s .= $this->pdfRect($x, $y, $totalWidth, $rowHeight, strokeColor: [226, 232, 240], fillColor: $fillColor, lineWidth: 0.3);
            $cx = $x + 5;
            $values = array_values($row);
            foreach ($values as $ci => $cell) {
                $maxW = ($colWidths[$ci] ?? 80) - 10;
                $s .= $this->pdfText($cx, $y + 5, $this->fitText($cell, $maxW, 8), 8, 'F1', [30, 41, 59]);
                $cx += $colWidths[$ci] ?? 80;
            }
            $y -= $rowHeight;

            if ($y < $minY) {
                break;
            }
        }

        return $s;
    }

    private function fitText(string $text, float $maxWidth, float $fontSize, string $font = 'F1'): string
    {
        $charWidth = $fontSize * ($font === 'F2' ? 0.62 : 0.55);
        $maxChars = (int) ($maxWidth / $charWidth);

        if (mb_strlen($text) <= $maxChars) {
            return $text;
        }

        return mb_substr($text, 0, max(0, $maxChars - 3)).'...';
    }
}
