<?php

declare(strict_types=1);

/*
 * Laravel 12 provider registry.
 *
 * Replaces the legacy `config/app.php` providers array. Add new
 * application providers here.
 */

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\RepositoryServiceProvider::class,
];
