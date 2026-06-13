<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\PodcastArchiveUploaded;
use App\Events\PodcastDeliveryVerified;
use App\Events\PodcastProcessed;
use App\Events\PodcastRadiobossUploaded;
use App\Events\PodcastUploadFailed;
use App\Listeners\DispatchPodcastDistributionJobs;
use App\Listeners\FinalizePodcastDelivery;
use App\Listeners\RecordPodcastUploadFailure;
use App\Listeners\SendPodcastArchiveNotification;
use App\Listeners\SendPodcastRadiobossNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        \Illuminate\Mail\Events\MessageSent::class => [
            \App\Listeners\LogSentEmail::class,
        ],
        PodcastProcessed::class => [
            DispatchPodcastDistributionJobs::class,
        ],
        PodcastRadiobossUploaded::class => [
            SendPodcastRadiobossNotification::class,
        ],
        PodcastArchiveUploaded::class => [
            SendPodcastArchiveNotification::class,
        ],
        PodcastDeliveryVerified::class => [
            FinalizePodcastDelivery::class,
        ],
        PodcastUploadFailed::class => [
            RecordPodcastUploadFailure::class,
        ],
    ];
}
