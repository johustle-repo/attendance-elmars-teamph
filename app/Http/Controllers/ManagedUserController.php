<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ManagedUserController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user->canManageUsers(), 403);

        $managedUsers = User::query()
            ->visibleInSystem()
            ->withCount('attendances')
            ->orderByRaw("case when role = 'admin' then 0 else 1 end")
            ->orderBy('name')
            ->get();

        return Inertia::render('users/index', [
            'users' => $managedUsers->map(fn (User $managedUser) => [
                'id' => $managedUser->id,
                'name' => $managedUser->name,
                'sub_name' => $managedUser->sub_name,
                'email' => $managedUser->email,
                'role' => $managedUser->role?->value,
                'role_label' => $managedUser->role?->label(),
                'employee_code' => $managedUser->employee_code,
                'position' => $managedUser->position,
                'status' => $managedUser->status,
                'status_label' => $managedUser->statusLabel(),
                'qr_value' => $managedUser->qr_value,
                'attendance_count' => $managedUser->attendances_count,
                'created_at' => optional($managedUser->created_at)->format('M d, Y'),
            ]),
            'allowedRoles' => collect($this->allowedRolesFor($user))
                ->map(fn (UserRole $role) => [
                    'value' => $role->value,
                    'label' => $role->label(),
                ])
                ->values(),
            'statusOptions' => collect(User::availableStatuses())
                ->map(fn (string $status) => [
                    'value' => $status,
                    'label' => str($status)->headline()->value(),
                ])
                ->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->canManageUsers(), 403);

        $allowedRoles = array_map(
            fn (UserRole $role): string => $role->value,
            $this->allowedRolesFor($user),
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sub_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in($allowedRoles)],
            'position' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(User::availableStatuses())],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::query()->create([
            'name' => trim($validated['name']),
            'sub_name' => filled($validated['sub_name'] ?? null)
                ? trim((string) $validated['sub_name'])
                : null,
            'email' => Str::lower(trim($validated['email'])),
            'role' => $validated['role'],
            'position' => filled($validated['position'] ?? null)
                ? trim((string) $validated['position'])
                : 'Team Member',
            'status' => $validated['status'],
            'qr_token' => (string) Str::uuid(),
            'email_verified_at' => now(),
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User added successfully.');
    }

    public function updateStatus(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->canManageUsers(), 403);
        abort_unless($user->isVisibleInSystem(), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in(User::availableStatuses())],
        ]);

        if ($request->user()->is($user) && $validated['status'] === User::STATUS_INACTIVE) {
            return redirect()
                ->route('users.index')
                ->with('error', 'You cannot mark your own account as inactive.');
        }

        $user->update([
            'status' => $validated['status'],
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', $user->name.' is now marked as '.$user->statusLabel().'.');
    }

    /**
     * @return array<int, UserRole>
     */
    private function allowedRolesFor(User $user): array
    {
        return $user->isSuperAdmin()
            ? [UserRole::Admin, UserRole::Member]
            : [UserRole::Member];
    }
}
