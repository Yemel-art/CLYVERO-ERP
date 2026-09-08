<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DTO\Auth\LoginDTO;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\VerifyLoginOtpRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Authentication endpoints — POST /login, /logout, /forgot-password,
 * /reset-password, /refresh-token; GET /me.
 *
 * Pure orchestration: receive request → build DTO → call AuthService →
 * shape response with API Resource → return.
 *
 * Source: API Blueprint §7 Authentication + Business Workflow §1.
 */
final class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $auth,
    ) {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = new LoginDTO(
            email:      $request->string('email')->toString(),
            password:   $request->string('password')->toString(),
            ipAddress:  $request->ip() ?? '0.0.0.0',
            schoolSlug: $request->filled('school_slug') ? $request->string('school_slug')->toString() : null,
            userAgent:  $request->userAgent(),
            rememberMe: $request->boolean('remember_me'),
        );

        $result = $this->auth->login($dto);

        if ($result['requires_otp']) {
            return $this->ok(
                data: $result,
                message: 'A verification code has been sent to the administrator email.',
            );
        }

        return $this->ok(
            data: [
                'requires_otp' => false,
                'user'       => new UserResource($result['user']),
                'token'      => $result['token'],
                'token_type' => $result['token_type'],
            ],
            message: 'Login successful.',
        );
    }

    public function verifyLoginOtp(VerifyLoginOtpRequest $request): JsonResponse
    {
        $result = $this->auth->verifyAdminLoginOtp(
            challengeId: $request->string('challenge_id')->toString(),
            code: $request->string('code')->toString(),
            ip: $request->ip() ?? '0.0.0.0',
            userAgent: $request->userAgent(),
        );

        return $this->ok(
            data: [
                'requires_otp' => false,
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
                'token_type' => $result['token_type'],
            ],
            message: 'Administrator login verified successfully.',
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auth->logout(
            user:      $request->user(),
            ip:        $request->ip() ?? '0.0.0.0',
            userAgent: $request->userAgent(),
        );

        return $this->ok(message: 'Logout successful.');
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['role.permissions', 'school']);

        return $this->ok(
            data: new UserResource($user),
            message: 'Current user.',
        );
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($request->has('email')) {
            $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        }

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:80'],
            'last_name'  => ['required', 'string', 'max:80'],
            'email'      => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->where('school_id', $user->school_id)->ignore($user->id),
            ],
            'phone'      => ['nullable', 'string', 'max:30'],
            'current_password' => ['nullable', 'string', 'max:255'],
        ]);

        $emailChanged = strtolower($user->email) !== $validated['email'];
        if ($emailChanged && (
            empty($validated['current_password'])
            || ! Hash::check($validated['current_password'], $user->password)
        )) {
            throw ValidationException::withMessages([
                'current_password' => ['Your current password is required to change the login email.'],
            ]);
        }

        unset($validated['current_password']);
        if ($emailChanged) {
            // The address becomes the login and OTP destination immediately.
            // Mark it unverified until a dedicated verification workflow is enabled.
            $validated['email_verified_at'] = null;
        }

        $user->forceFill($validated)->save();

        return $this->ok(
            data: new UserResource($user->fresh()->load(['role.permissions', 'school'])),
            message: 'Profile updated successfully.',
        );
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->auth->changePassword(
            user: $request->user(),
            currentPassword: $request->string('current_password')->toString(),
            newPassword: $request->string('password')->toString(),
            ip: $request->ip() ?? '0.0.0.0',
            userAgent: $request->userAgent(),
        );

        return $this->ok(
            message: 'Password changed successfully. Please sign in again on all devices.',
        );
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->sendPasswordResetLink(
            email:     $request->string('email')->toString(),
            schoolSlug: $request->string('school_slug')->toString() ?: null,
            accountScope: $request->string('account_scope')->toString() ?: 'school',
            ip:        $request->ip() ?? '0.0.0.0',
            userAgent: $request->userAgent(),
        );

        // Uniform response — never confirm whether the email exists.
        return $this->ok(
            message: 'If an account with that email exists, a password reset link has been sent.',
        );
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->auth->resetPassword(
            email:     $request->string('email')->toString(),
            schoolSlug: $request->string('school_slug')->toString() ?: null,
            accountScope: $request->string('account_scope')->toString() ?: 'school',
            password:  $request->string('password')->toString(),
            token:     $request->string('token')->toString(),
            ip:        $request->ip() ?? '0.0.0.0',
            userAgent: $request->userAgent(),
        );

        if ($status === Password::PASSWORD_RESET) {
            return $this->ok(message: 'Your password has been reset successfully. Please log in.');
        }

        return $this->error(
            message: $status === 'passwords.reused'
                ? 'The new password must be different from the current password.'
                : __($status),
            status: 422,
            errorCode: 'password_reset_failed',
        );
    }

    public function refreshToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $oldToken = $user->currentAccessToken();
        $tokenName = $oldToken?->name ?? 'auth-token';

        // Revoke the old, issue a new one with the same abilities.
        $oldToken?->delete();
        $newToken = $user->createToken($tokenName, ['*'], now()->addHours(12));

        return $this->ok(
            data: [
                'token'      => $newToken->plainTextToken,
                'token_type' => 'Bearer',
                'user'       => new UserResource($user->load('role.permissions')),
            ],
            message: 'Token refreshed.',
        );
    }
}
