<?php

use App\Providers\AppServiceProvider;
use App\Providers\BackblazeServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    BackblazeServiceProvider::class,
];
