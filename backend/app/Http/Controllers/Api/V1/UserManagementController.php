<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Api\ApiController;
use App\Models\Role;
use App\Models\User;
use App\Rules\StrongPassword;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

final class UserManagementController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('user.view'), 403);

        $q = User::query()->with('role');
        if ($request->filled('q')) {
            $like = '%' . $request->input('q') . '%';
            $q->where(fn ($q) => $q
                ->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like));
        }
        if ($request->filled('role')) {
            $q->whereHas('role', fn ($r) => $r->where('name', $request->input('role')));
        }
        if ($request->filled('is_active')) {
            $q->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $page = $q->orderBy('first_name')->paginate((int) $request->integer('per_page', 25));

        $data = collect($page->items())->map(fn (User $u) => [
            'id'         => $u->id,
            'first_name' => $u->first_name,
            'last_name'  => $u->last_name,
            'full_name'  => $u->full_name,
            'email'      => $u->email,
            'phone'      => $u->phone,
            'is_active'  => $u->is_active,
            'last_login_at' => $u->last_login_at?->toIso8601String(),
            // Keep the array shape expected by the frontend while using the
            // application's actual one-role-per-user relationship.
            'roles'      => $u->role ? [[
                'id' => $u->role->id,
                'name' => $u->role->name,
                'display_name' => $u->role->display_name,
            ]] : [],
        ])->all();

        return $this->ok(data: $data, message: 'Users retrieved.', meta: [
            'page' => $page->currentPage(), 'per_page' => $page->perPage(),
            'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('user.create'), 403);

        $schoolId = (string) $request->user()->school_id;
        $request->merge([
            'first_name' => trim((string) $request->input('first_name')),
            'last_name' => trim((string) $request->input('last_name')),
            'email' => strtolower(trim((string) $request->input('email'))),
            'phone' => $request->filled('phone') ? trim((string) $request->input('phone')) : null,
        ]);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name'  => ['required', 'string', 'max:60'],
            'email'      => ['required', 'email', 'max:120', Rule::unique('users', 'email')->where('school_id', $schoolId)],
            'phone'      => ['nullable', 'string', 'max:30'],
            'password'   => ['required', 'string', new StrongPassword()],
            'role'       => ['required', 'string', Rule::exists('roles', 'name'), Rule::notIn([UserRole::SuperAdministrator->value])],
            'is_active'  => ['sometimes', 'boolean'],
        ]);

        $role = Role::where('name', $data['role'])->firstOrFail();
        $user = User::create([
            'school_id' => $schoolId,
            'role_id'   => $role->id,
            'first_name' => $data['first_name'],
            'last_name'  => $data['last_name'],
            'email'      => $data['email'],
            'phone'      => $data['phone'] ?? null,
            'password'   => Hash::make($data['password']),
            'is_active'  => $data['is_active'] ?? true,
        ]);

        return $this->created([
            'id'        => $user->id,
            'full_name' => $user->full_name,
            'email'     => $user->email,
            'roles'     => [$role->name],
        ], 'User created.');
    }

    public function update(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('user.edit'), 403);

        $schoolId = (string) $request->user()->school_id;
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }

        $data = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:60'],
            'last_name'  => ['sometimes', 'string', 'max:60'],
            'email'      => ['sometimes', 'email', 'max:120', Rule::unique('users', 'email')->where('school_id', $schoolId)->ignore($user->id)],
            'phone'      => ['sometimes', 'nullable', 'string', 'max:30'],
            'is_active'  => ['sometimes', 'boolean'],
            'role'       => ['sometimes', 'string', Rule::exists('roles', 'name'), Rule::notIn([UserRole::SuperAdministrator->value])],
        ]);

        if (isset($data['role'])) {
            $role = Role::where('name', $data['role'])->firstOrFail();
            $data['role_id'] = $role->id;
        }
        unset($data['role']);
        $user->fill($data)->save();

        return $this->ok([
            'id'        => $user->id,
            'full_name' => $user->full_name,
            'email'     => $user->email,
            'is_active' => $user->is_active,
        ], 'User updated.');
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        abort_unless($request->user()?->hasPermission('user.reset_password'), 403);

        $data = $request->validate([
            'password' => ['required', 'string', new StrongPassword()],
        ]);

        if (Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The new password must be different from the current password.'],
            ]);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ])->save();
        $user->tokens()->delete();
        Password::broker()->deleteToken($user);
        return $this->ok(null, 'Password reset.');
    }
}
