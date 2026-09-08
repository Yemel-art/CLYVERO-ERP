<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class AccountInactiveException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'Your account has been deactivated. Please contact the administrator.',
            httpStatus: Response::HTTP_FORBIDDEN,
            errorCode: 'account_inactive',
        );
    }
}
