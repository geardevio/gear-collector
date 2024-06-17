<?php

namespace GearDev\Collector\Collector;

use Illuminate\Foundation\Application;

interface AttributeInterface
{
    public function onClass(Application $app, string $className, AttributeInterface $attribute): void;

    public function onMethod(Application $app, string $className, string $methodName, AttributeInterface $attribute): void;

    public function onProperty(Application $app, string $className, string $propertyName, AttributeInterface $attribute): void;
}