<?php

use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceScanController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ManagedUserController;
use App\Http\Controllers\PayslipController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome', [
    'canRegister' => Features::enabled(Features::registration()),
    'scanUrl' => '/scan',
])->name('home');

Route::get('/scan', [AttendanceScanController::class, 'create'])->name('scan.create');
Route::post('/scan', [AttendanceScanController::class, 'store'])->name('scan.store');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('users', [ManagedUserController::class, 'index'])->name('users.index');
        Route::post('users', [ManagedUserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}/status', [ManagedUserController::class, 'updateStatus'])->name('users.update-status');

        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::get('attendances/export', [AttendanceController::class, 'export'])->name('attendances.export');
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::get('backups/export', [BackupController::class, 'export'])->name('backups.export');

        Route::get('payslips', [PayslipController::class, 'index'])->name('payslips.index');
        Route::get('payslips/export', [PayslipController::class, 'exportExcel'])->name('payslips.export');
        Route::get('payslips/{user}/pdf', [PayslipController::class, 'exportPdf'])->name('payslips.export-pdf');
    });

    Route::middleware('role:super_admin')->group(function () {
        Route::post('attendances/manual-record', [AttendanceController::class, 'storeManualRecord'])->name('attendances.store-manual-record');
        Route::post('attendances/manual-time-out', [AttendanceController::class, 'storeManualTimeOut'])->name('attendances.store-manual-time-out');
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
        Route::patch('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');

        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    });
});

require __DIR__.'/settings.php';
