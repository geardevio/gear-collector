<?php

namespace GearDev\Collector\Listeners;

class CacheClearListener {

    public function handle($event) {
        @unlink(base_path().'/storage/collector/annotationsHash');
        @unlink(base_path().'/storage/collector/attributeBasedThree');
        @unlink(base_path().'/storage/collector/classBasedThree');
    }

}