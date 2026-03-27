<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use HasFactory;

    public const OFFICE_START_HOUR = 8;

    public const OFFICE_END_HOUR = 17;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'recorded_at',
        'entry_type',
        'scanned_code',
        'source',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeVisibleInSystem(Builder $query): void
    {
        $query->whereHas('user', fn (Builder $userQuery) => $userQuery->visibleInSystem());
    }

    /**
     * @return array{attendance_status:string,status_label:string,status_hint:string,late_minutes:int|null}
     */
    public static function lateStatusFor(?CarbonInterface $timeIn): array
    {
        if (! $timeIn) {
            return [
                'attendance_status' => 'no_time_in',
                'status_label' => 'No Time In',
                'status_hint' => 'No time in has been recorded yet.',
                'late_minutes' => null,
            ];
        }

        $officeStart = CarbonImmutable::parse(
            $timeIn->toDateString(),
            $timeIn->getTimezone(),
        )->setTime(self::OFFICE_START_HOUR, 0);

        if ($timeIn->lessThanOrEqualTo($officeStart)) {
            return [
                'attendance_status' => 'on_time',
                'status_label' => 'On Time',
                'status_hint' => 'Recorded on or before 8:00 AM.',
                'late_minutes' => 0,
            ];
        }

        $lateMinutes = $officeStart->diffInMinutes($timeIn);

        return [
            'attendance_status' => 'late',
            'status_label' => 'Late',
            'status_hint' => $lateMinutes.' minute'.($lateMinutes === 1 ? '' : 's').' after 8:00 AM.',
            'late_minutes' => $lateMinutes,
        ];
    }

    public static function officeHoursLabel(): string
    {
        return '8:00 AM - 5:00 PM';
    }
}
