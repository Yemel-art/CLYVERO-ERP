<?php

declare(strict_types=1);

namespace App\Enums;

enum Gender: string
{
    case Male   = 'male';
    case Female = 'female';

    public function displayName(): string
    {
        return ucfirst($this->value);
    }
}
