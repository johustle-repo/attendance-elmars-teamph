<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $filters = $request->validate([
            'action'   => ['nullable', 'string', 'max:100'],
            'date'     => ['nullable', 'date'],
        ]);

        $logs = AuditLog::query()
            ->with('performer:id,name,employee_code')
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('performed_at', $date))
            ->orderByDesc('performed_at')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('audit-logs/index', [
            'filters' => [
                'action' => $filters['action'] ?? '',
                'date'   => $filters['date'] ?? '',
            ],
            'actions' => $this->availableActions(),
            'logs'    => $logs->through(fn (AuditLog $log): array => [
                'id'            => $log->id,
                'action'        => $log->action,
                'action_label'  => $this->actionLabel($log->action),
                'resource_type' => $log->resource_type,
                'resource_id'   => $log->resource_id,
                'old_values'    => $log->old_values,
                'new_values'    => $log->new_values,
                'ip_address'    => $log->ip_address,
                'performed_at'  => $log->performed_at?->toIso8601String(),
                'performed_at_display' => $log->performed_at?->setTimezone(config('app.timezone'))->format('M d, Y h:i A'),
                'performer_name' => $log->performer?->name ?? 'Unknown',
                'performer_code' => $log->performer?->employee_code,
            ]),
        ]);
    }

    /** @return array<int, array{value: string, label: string}> */
    private function availableActions(): array
    {
        return [
            ['value' => 'attendance.scan',            'label' => 'QR Scan'],
            ['value' => 'attendance.update',          'label' => 'Time Edit'],
            ['value' => 'attendance.delete',          'label' => 'Record Delete'],
            ['value' => 'attendance.manual_time_out', 'label' => 'Manual Time Out'],
            ['value' => 'backup.export',              'label' => 'Backup Export'],
        ];
    }

    private function actionLabel(string $action): string
    {
        return match ($action) {
            'attendance.scan'            => 'QR Scan',
            'attendance.update'          => 'Time Edit',
            'attendance.delete'          => 'Record Delete',
            'attendance.manual_time_out' => 'Manual Time Out',
            'backup.export'              => 'Backup Export',
            default                      => $action,
        };
    }
}
