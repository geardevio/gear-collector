<?php

namespace GearDev\Collector;

use GearDev\Collector\Collector\Collector;
use GearDev\Collector\Listeners\CacheClearListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class GearCollectorLaravelProvider extends ServiceProvider
{
    public function boot() {

    }

    public function register() {
        Collector::addPackageToCollector(__DIR__);
        Event::listen('cache:clearing', CacheClearListener::class);
    }

}