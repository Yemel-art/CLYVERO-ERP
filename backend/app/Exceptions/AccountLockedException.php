<?php

declare(strict_types=1);

namespace App\Exceptions;

use DateTimeInterface;
use Symfony\Component\HttpFoundation\Response;

class AccountLockedException extends DomainException
{
    public function __construct(?DateTimeInterface $lockedUntil = null)
    {
        $context = $lockedUntil
            ? ['locked_until' => $lockedUntil->format(DateTimeInterface::ATOM)]
            : [];

        parent::__construct(
            message: 'Too many failed login attempts. Your account is temporarily locked.',
            httpStatus: Response::HTTP_TOO_MANY_REQUESTS,
            errorCode: 'account_locked',
            context: $context,
        );
    }
}
