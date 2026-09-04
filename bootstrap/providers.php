<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\TenancyServiceProvider;
use Ronda\Identity\Presentation\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    TenancyServiceProvider::class,
    FortifyServiceProvider::class,
];
