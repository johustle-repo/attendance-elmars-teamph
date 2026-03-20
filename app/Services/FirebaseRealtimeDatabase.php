<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FirebaseRealtimeDatabase
{
    public function isConfigured(): bool
    {
        return filled(config('services.firebase.database_url'));
    }

    public function syncUser(User $user): void
    {
        $this->put('users/'.$user->id, [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role?->value,
            'employee_code' => $user->employee_code,
            'position' => $user->position,
            'qr_value' => $user->qr_value,
            'created_at' => optional($user->created_at)->toIso8601String(),
            'updated_at' => optional($user->updated_at)->toIso8601String(),
        ]);
    }

    public function deleteUser(User $user): void
    {
        $this->delete('users/'.$user->id);
    }

    public function syncAttendance(Attendance $attendance): void
    {
        $attendance->loadMissing('user');

        $this->put('attendances/'.$attendance->id, [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'user_name' => $attendance->user?->name,
            'user_email' => $attendance->user?->email,
            'employee_code' => $attendance->user?->employee_code,
            'recorded_at' => optional($attendance->recorded_at)->toIso8601String(),
            'recorded_date' => optional($attendance->recorded_at)->toDateString(),
            'recorded_time' => optional($attendance->recorded_at)->format('H:i:s'),
            'entry_type' => $attendance->entry_type,
            'scanned_code' => $attendance->scanned_code,
            'source' => $attendance->source,
            'created_at' => optional($attendance->created_at)->toIso8601String(),
            'updated_at' => optional($attendance->updated_at)->toIso8601String(),
        ]);
    }

    public function deleteAttendance(Attendance $attendance): void
    {
        $this->delete('attendances/'.$attendance->id);
    }

    private function put(string $path, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        try {
            $response = Http::asJson()
                ->timeout(10)
                ->withQueryParameters($this->authQuery())
                ->put($this->urlFor($path), $payload);

            if ($response->failed()) {
                Log::warning('Firebase sync failed.', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Firebase sync request failed.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function delete(string $path): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        try {
            $response = Http::timeout(10)
                ->withQueryParameters($this->authQuery())
                ->delete($this->urlFor($path));

            if ($response->failed()) {
                Log::warning('Firebase delete failed.', [
                    'path' => $path,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (Throwable $exception) {
            Log::warning('Firebase delete request failed.', [
                'path' => $path,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function authQuery(): array
    {
        $secret = config('services.firebase.database_secret');

        return filled($secret) ? ['auth' => $secret] : [];
    }

    private function urlFor(string $path): string
    {
        return rtrim((string) config('services.firebase.database_url'), '/').'/'.trim($path, '/').'.json';
    }
}
