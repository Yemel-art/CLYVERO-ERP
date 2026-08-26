<?php

declare(strict_types=1);

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Base for every Service. Provides transactional execution helpers.
 *
 * Services contain ALL business logic. They orchestrate repositories,
 * raise events, and never return raw query builders.
 */
abstract class BaseService
{
    /**
     * Run a callback inside a database transaction.
     *
     * @template T
     * @param  Closure(): T  $callback
     * @return T
     */
    protected function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }
}
