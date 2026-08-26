<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Student;
use App\Models\Teacher;
use App\Models\ParentGuardian;
use App\Models\AcademicYear;
use App\Models\Term;
use App\Models\Subject;
use App\Models\SchoolClass;
use App\Models\User;

use App\Observers\UserObserver;

use App\Policies\StudentPolicy;
use App\Policies\TeacherPolicy;
use App\Policies\ParentPolicy;
use App\Policies\AcademicYearPolicy;
use App\Policies\TermPolicy;
use App\Policies\SubjectPolicy;
use App\Policies\SchoolClassPolicy;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use App\Services\TenantContext;


/**
 * Application bootstrap: observers, rate limiters, and policies.
 */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }


    public function boot(): void
    {
        ResetPassword::createUrlUsing(function (User $user, string $token): string {
            $user->loadMissing('school');

            return rtrim((string) config('app.frontend_url'), '/') . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $user->email,
                'school' => $user->school?->slug,
            ]);
        });
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $user->loadMissing('school');
            $url = rtrim((string) config('app.frontend_url'), '/') . '/reset-password?' . http_build_query([
                'token' => $token,
                'email' => $user->email,
                'school' => $user->school?->slug,
            ]);
            $minutes = (int) config('auth.passwords.users.expire', 30);

            $requestedLocale = request()?->header('X-Locale');
            $locale = in_array($requestedLocale, ['fr', 'en'], true)
                ? $requestedLocale
                : ($user->school?->default_locale ?? config('app.locale'));

            if ($locale === 'fr') {
                return (new MailMessage())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->greeting('Bonjour ' . $user->first_name . ',')
                    ->line('Vous recevez cet e-mail parce qu’une demande de réinitialisation du mot de passe a été effectuée pour votre compte.')
                    ->action('Réinitialiser le mot de passe', $url)
                    ->line("Ce lien de réinitialisation expire dans {$minutes} minutes.")
                    ->line('Si vous n’avez pas demandé cette réinitialisation, aucune action n’est nécessaire.');
            }

            return (new MailMessage())
                ->subject('Reset your password')
                ->greeting('Hello ' . $user->first_name . ',')
                ->line('You are receiving this email because a password reset was requested for your account.')
                ->action('Reset password', $url)
                ->line("This password reset link expires in {$minutes} minutes.")
                ->line('If you did not request a password reset, no further action is required.');
        });
        $this->registerObservers();
        $this->registerRateLimiters();
        $this->registerPolicies();
    }


    private function registerObservers(): void
    {
        User::observe(UserObserver::class);
    }


    private function registerPolicies(): void
    {
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(Teacher::class, TeacherPolicy::class);
        Gate::policy(ParentGuardian::class, ParentPolicy::class);
        Gate::policy(AcademicYear::class, AcademicYearPolicy::class);
        Gate::policy(Term::class, TermPolicy::class);
        Gate::policy(Subject::class, SubjectPolicy::class);
        Gate::policy(SchoolClass::class, SchoolClassPolicy::class);
    }


    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $perMinute = (int) env('RATE_LIMIT_LOGIN_PER_MINUTE', 5);

            $key = $request->ip() . '|' . strtolower((string) $request->input('email', ''));

            return [
                Limit::perMinute($perMinute)->by($key)
            ];
        });

        RateLimiter::for('otp-verify', function (Request $request) {
            $perMinute = (int) env('RATE_LIMIT_OTP_VERIFY_PER_MINUTE', 6);
            $challenge = strtolower((string) $request->input('challenge_id', 'missing'));
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perMinute($perMinute)->by("otp|{$challenge}|{$ip}"),
                Limit::perMinute(max(12, $perMinute * 2))->by("otp-ip|{$ip}"),
            ];
        });


        RateLimiter::for('password-reset', function (Request $request) {
            $perHour = (int) env('RATE_LIMIT_PASSWORD_RESET_PER_HOUR', 3);

            $identity = strtolower(trim((string) $request->input('email', '')));
            $school = strtolower(trim((string) $request->input('school_slug', '')));
            $ip = $request->ip() ?? 'unknown';

            return [
                Limit::perHour($perHour)->by("password-reset|{$school}|{$identity}"),
                Limit::perHour(max(10, $perHour * 3))->by("password-reset-ip|{$ip}"),
            ];
        });

        RateLimiter::for('password-change', function (Request $request) {
            $perMinute = (int) env('RATE_LIMIT_PASSWORD_CHANGE_PER_MINUTE', 3);

            return [
                Limit::perMinute($perMinute)->by('password-change|' . ($request->user()?->id ?? $request->ip())),
            ];
        });


        RateLimiter::for('api', function (Request $request) {
            $perMinute = (int) env('RATE_LIMIT_API_PER_MINUTE', 100);

            $key = $request->user()?->id ?? $request->ip();

            return [
                Limit::perMinute($perMinute)->by($key)
            ];
        });
    }
}
