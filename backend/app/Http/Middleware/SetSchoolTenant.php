<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

final class SetSchoolTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_if($user === null, 403, 'Authentication is required.');
        abort_if(
            $user->isSuperAdministrator(),
            403,
            'The platform owner manages school identity and access only. Sign in with the school administrator account for academic operations.',
        );

        $school = $user->school;
        abort_if($user->school_id === null || $school === null || ! $school->is_active, 403, 'No active school is assigned to this account.');
        $requestedCode = strtoupper(trim((string) $request->header('X-School-Code')));
        abort_if($requestedCode !== '' && $requestedCode !== strtoupper($school->school_code), 403, 'You are not authorized for the requested school.');

        $this->tenant->setSchoolId($school->id);
        $request->attributes->set('tenant_school', $school);
        $locale = strtolower((string) $request->header('X-Locale', $school->default_locale ?? 'fr'));
        App::setLocale(in_array($locale, ['en', 'fr'], true) ? $locale : 'fr');

        try {
            return $next($request);
        } finally {
            $this->tenant->clear();
        }
    }
}
