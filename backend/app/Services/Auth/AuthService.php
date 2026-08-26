<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\DTO\Auth\LoginDTO;
use App\Enums\AuditAction;
use App\Exceptions\AccountInactiveException;
use App\Exceptions\AccountLockedException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authentication business logic.
 *
 * Responsibilities (per Workflow 1, SRS Auth Module, Security Blueprint §4-8):
 *   - Validate credentials.
 *   - Enforce is_active gate.
 *   - Track failed attempts and lock accounts.
 *   - Issue Sanctum Bearer tokens.
 *   - Log activity (login/logout) and audit (created/updated where applicable).
 *   - Drive password reset email + token verification.
 */
class AuthService extends BaseService
{
    /** Maximum failed attempts before temporary lock. */
    private const MAX_FAILED_ATTEMPTS = 5;

    /** Lock duration after MAX_FAILED_ATTEMPTS. */
    private const LOCK_DURATION_MINUTES = 15;

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly AdminLoginOtpService $adminOtp,
        private readonly TenantPasswordResetService $passwordResets,
    ) {
    }

    /**
     * Validate credentials. Administrators receive an email OTP challenge;
     * other roles receive their Sanctum token immediately.
     *
     * @return array{requires_otp:true,challenge_id:string,masked_email:string,expires_in:int}|array{requires_otp:false,user:User,token:string,token_type:'Bearer'}
     *
     * @throws InvalidCredentialsException
     * @throws AccountInactiveException
     * @throws AccountLockedException
     */
    public function login(LoginDTO $dto): array
    {
        $user = $this->users->findByEmail($dto->email, $dto->schoolSlug);

        if ($user === null) {
            $this->logFailedLogin($dto, reason: 'user_not_found');
            throw new InvalidCredentialsException();
        }

        if ($user->isLocked()) {
            throw new AccountLockedException($user->locked_until);
        }

        // A disabled tenant must be rejected consistently. Previously the
        // repository hid disabled schools only when a school code was sent,
        // which produced a misleading "wrong password" response and allowed
        // an unambiguous email to bypass the tenant status check.
        if (! $user->is_active || ($user->school !== null && ! $user->school->is_active)) {
            $this->logFailedLogin($dto, reason: 'inactive', userId: $user->id);
            throw new AccountInactiveException();
        }

        if (! Hash::check($dto->password, $user->password)) {
            $user = $this->users->incrementFailedAttempts($user);
            $this->logFailedLogin($dto, reason: 'wrong_password', userId: $user->id);

            if ($user->failed_login_attempts >= self::MAX_FAILED_ATTEMPTS) {
                $until = Carbon::now()->addMinutes(self::LOCK_DURATION_MINUTES);
                $this->users->lockUntil($user, $until);
                throw new AccountLockedException($until);
            }

            throw new InvalidCredentialsException();
        }

        if (($user->isAdministrator() || $user->isSuperAdministrator()) && config('login_security.admin_email_otp_enabled', false)) {
            $user = $this->users->resetFailedAttempts($user);
            $challenge = $this->adminOtp->createChallenge($user, $dto->rememberMe, $dto->ipAddress);

            return [
                'requires_otp' => true,
                'challenge_id' => $challenge->id,
                'masked_email' => $this->adminOtp->maskEmail($user->email),
                'expires_in' => AdminLoginOtpService::EXPIRES_IN_MINUTES * 60,
            ];
        }

        return $this->completeLogin($user, $dto->rememberMe, $dto->ipAddress, $dto->userAgent);
    }

    /** @return array{requires_otp:false,user:User,token:string,token_type:'Bearer'} */
    public function verifyAdminLoginOtp(
        string $challengeId,
        string $code,
        string $ip,
        ?string $userAgent,
    ): array {
        $challenge = $this->adminOtp->verify($challengeId, $code);

        return $this->completeLogin(
            $challenge->user,
            $challenge->remember_me,
            $ip,
            $userAgent,
        );
    }

    public function logout(User $user, string $ip, ?string $userAgent): void
    {
        $this->transaction(function () use ($user, $ip, $userAgent): void {
            // Revoke just the current token (not all tokens).
            $user->currentAccessToken()?->delete();

            $this->logActivity($user->id, 'logout', $ip, $userAgent);
            $this->writeAudit($user->id, 'auth', AuditAction::LoggedOut, $ip, $userAgent);
        });
    }

    /**
     * Change an authenticated user's password and invalidate every session.
     *
     * Requiring the existing password protects an unattended authenticated
     * browser, while revoking every token contains stolen-session risk.
     */
    public function changePassword(
        User $user,
        string $currentPassword,
        string $newPassword,
        string $ip,
        ?string $userAgent,
    ): void {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        if (Hash::check($newPassword, $user->password)) {
            throw ValidationException::withMessages([
                'password' => ['The new password must be different from the current password.'],
            ]);
        }

        $this->transaction(function () use ($user, $newPassword, $ip, $userAgent): void {
            $user->forceFill([
                'password' => Hash::make($newPassword),
                'remember_token' => Str::random(60),
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            // A password change is a security boundary: every browser and API
            // token, including the current one, must authenticate again.
            $user->tokens()->delete();
            $this->passwordResets->deleteFor($user);

            $this->logActivity($user->id, 'password_changed', $ip, $userAgent);
            $this->writeAudit($user->id, 'auth', AuditAction::PasswordChanged, $ip, $userAgent);
        });
    }

    /**
     * Initiate the password-reset flow: generate token + send email.
     *
     * Always returns a uniform status string to prevent user enumeration.
     */
    public function sendPasswordResetLink(string $email, ?string $schoolSlug, string $ip, ?string $userAgent): string
    {
        $user = $this->users->findByEmail($email, $schoolSlug);
        if ($user === null) {
            return Password::RESET_LINK_SENT;
        }
        $this->passwordResets->send($user, $ip);
        $this->logActivity($user->id, 'password_reset_requested', $ip, $userAgent);
        $this->writeAudit($user->id, 'auth', AuditAction::PasswordResetRequested, $ip, $userAgent);

        return Password::RESET_LINK_SENT;
    }

    /**
     * Apply a new password using a valid reset token.
     */
    public function resetPassword(
        string $email,
        ?string $schoolSlug,
        string $password,
        string $token,
        string $ip,
        ?string $userAgent,
    ): string {
        $user = $this->users->findByEmail($email, $schoolSlug);
        if ($user === null) {
            return Password::INVALID_USER;
        }

        if (Hash::check($password, $user->password)) {
            return 'passwords.reused';
        }

        $consumed = $this->passwordResets->consume(
            $user,
            $token,
            function () use ($user, $password, $ip, $userAgent): void {
                $user->password = Hash::make($password);
                $user->setRememberToken(Str::random(60));
                $user->failed_login_attempts = 0;
                $user->locked_until = null;
                $user->save();

                // Revoke all existing tokens — force fresh logins everywhere.
                $user->tokens()->delete();

                event(new PasswordReset($user));

                $this->logActivity($user->id, 'password_reset', $ip, $userAgent);
                $this->writeAudit($user->id, 'auth', AuditAction::PasswordReset, $ip, $userAgent);
            },
        );

        return $consumed ? Password::PASSWORD_RESET : Password::INVALID_TOKEN;
    }

    // ─── Private helpers ────────────────────────────────────────────

    /** @return array{requires_otp:false,user:User,token:string,token_type:'Bearer'} */
    private function completeLogin(User $user, bool $rememberMe, string $ip, ?string $userAgent): array
    {
        return $this->transaction(function () use ($user, $rememberMe, $ip, $userAgent): array {
            $user = $this->users->markLoggedIn($user, $ip);

            $tokenName = 'auth-token-' . substr(Str::uuid()->toString(), 0, 8);
            $expiresAt = $rememberMe
                ? Carbon::now()->addDays(30)
                : Carbon::now()->addHours(12);

            $token = $user->createToken($tokenName, ['*'], $expiresAt)->plainTextToken;

            $this->logActivity($user->id, 'login', $ip, $userAgent);
            $this->writeAudit($user->id, 'auth', AuditAction::LoggedIn, $ip, $userAgent);

            return [
                'requires_otp' => false,
                'user' => $user->load(['role.permissions', 'school']),
                'token' => $token,
                'token_type' => 'Bearer',
            ];
        });
    }

    private function logFailedLogin(LoginDTO $dto, string $reason, ?string $userId = null): void
    {
        ActivityLog::create([
            'user_id'    => $userId,
            'activity'   => 'login_failed',
            'metadata'   => ['email' => $dto->email, 'reason' => $reason],
            'ip_address' => $dto->ipAddress,
            'user_agent' => $dto->userAgent,
            'created_at' => now(),
        ]);
    }

    private function logActivity(
        string $userId,
        string $activity,
        string $ip,
        ?string $userAgent,
    ): void {
        ActivityLog::create([
            'user_id'    => $userId,
            'activity'   => $activity,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }

    private function writeAudit(
        string $userId,
        string $module,
        AuditAction $action,
        string $ip,
        ?string $userAgent,
    ): void {
        AuditLog::create([
            'user_id'    => $userId,
            'module'     => $module,
            'action'     => $action->value,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'created_at' => now(),
        ]);
    }
}
