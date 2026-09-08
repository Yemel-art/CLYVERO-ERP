<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Term;
use App\Repositories\Contracts\TermRepositoryInterface;

class EloquentTermRepository extends BaseRepository implements TermRepositoryInterface
{
    protected function model(): string { return Term::class; }
}
