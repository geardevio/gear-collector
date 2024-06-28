<?php

namespace GearDev\Collector\Collector;

interface AttributeInterface
{
    public function onClass(string $className, AttributeInterface $attribute): void;

    public function onMethod(string $className, string $methodName, AttributeInterface $attribute): void;

    public function onProperty(string $className, string $propertyName, AttributeInterface $attribute): void;
}