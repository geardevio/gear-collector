<?php

namespace GearDev\Collector\Collector;

class Attribute
{
    private string $className;

    private array $arguments = [];

    public function __construct(
        string $className,
        array $arguments
    ) {
        $this->className = $className;
        foreach ($arguments as $name=>$value) {
            $this->arguments[] = new AttributeArgument($name, $value);
        }
        $this->arguments = $arguments;
    }

    /**
     * @return array|AttributeArgument[]
     */
    public function getArguments(): array {
        return $this->arguments;
    }

    public function getAttributeClassName(): string
    {
        return $this->className;
    }
    static function __set_state(array $array) {
        return new self(...$array);
    }

}
