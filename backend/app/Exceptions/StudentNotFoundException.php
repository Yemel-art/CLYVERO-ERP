<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class StudentNotFoundException extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            message: 'The requested student could not be found.',
            httpStatus: Response::HTTP_NOT_FOUND,
            errorCode: 'student_not_found',
        );
    }
}
