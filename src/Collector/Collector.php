<?php

namespace GearDev\Collector\Collector;

use Nette\PhpGenerator\ClassType;

class Collector
{
    private static mixed $registeredPackagePaths = [
        __DIR__.'/../../../', //all packages from geardevio
    ];

    private static array $pathToSkip = [];

    private static array $attributeBasedThree;
    private static array $classBasedThree;

    private function __construct()
    {
        self::$pathToSkip = [
            '/var/www/packages/frock',
            '/var/www/packages/gear-autowire',
            '/var/www/packages/gear-masko',
            '/var/www/packages/gear-logger',
        ];
    }

    private static ?self $instance = null;

    public static function getInstance(): self {
        if (self::$instance===null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function addPackageToCollector(string $packagePath): void
    {
        self::$registeredPackagePaths[] = $packagePath;
    }

    public function collect(string $path): void
    {
        $storagePath = $path.'/../storage/collector';
        if (!file_exists($storagePath)) {
            mkdir($storagePath, 0777, true);
        }
        if (!file_exists($storagePath.'/.gitignore')) {
            file_put_contents($storagePath.'/.gitignore', "*\n!.gitignore\n");
        }

        $currentHash = $this->getHashOfAllMimeTypes($path);


        if (!file_exists($storagePath.'/annotationsHash')) {
            file_put_contents($storagePath.'/annotationsHash', '');
            $cachedHash = null;
        } else {
            $cachedHash = file_get_contents($storagePath.'/annotationsHash');
        }

        if ($cachedHash!=$currentHash) {
            $classes = $this->getFilesWithClassesRecursively($path);
            $classes = $this->addClassesFromRegisteredPackages($classes);
            $classBasedThree = $this->createClassBasedThree($classes);
            $attributeBasedThree = $this->createAttributeBasedThree($classBasedThree);
            file_put_contents(
                $storagePath.'/attributeBasedThree',
                "<?php\n\nreturn ".var_export($attributeBasedThree, true)."\n;"
            );
            file_put_contents(
                $storagePath.'/classBasedThree',
                "<?php\n\nreturn ".var_export($classBasedThree, true)."\n;"
            );
            file_put_contents($storagePath.'/annotationsHash', $currentHash);


            self::$attributeBasedThree = $attributeBasedThree;
            self::$classBasedThree = $classBasedThree;

        } else {
            self::$attributeBasedThree = include $storagePath.'/attributeBasedThree';
            self::$classBasedThree = include $storagePath.'/classBasedThree';
        }
    }

    public function getClassesByAnnotation(string $attribute) {
        try {
            return self::$attributeBasedThree[$attribute] ?? [];
        } catch (\Throwable $e) {
            throw new \Exception('There is a problem with collector. Try remove files in storage/collector');
        }
    }

    public function getAnnotatedClassesCount(string $attribute): int
    {
        return count($this->getClassesByAnnotation($attribute));
    }

    public function runAttributeInstructions(string $attributeClassName) {
        $classes = $this->getClassesByAnnotation($attributeClassName);

        foreach ($classes as $className=>$classAnnotationsInfo) {
            $info = $this->getAnnotationsByClassName(ltrim($className, '\\'));

            if (isset($info['classAnnotations'])) {
                /** @var Attribute $attribute */
                foreach ($info['classAnnotations'] as $attribute) {
                    if (ltrim($attribute->getAttributeClassName(), '\\') !== ltrim($attributeClassName, '\\')) {
                        continue;
                    }
                    /** @var AttributeInterface $attributeExemplar */
                    $attributeExemplar = new ($attribute->getAttributeClassName())(...$attribute->getArguments());
                    $attributeExemplar->onClass($className, $attributeExemplar);
                }
            }

            if (isset($info['methodAnnotations'])) {
                /** @var Attribute $attribute */
                foreach ($info['methodAnnotations'] as $methodName => $attributesList) {
                    foreach ($attributesList as $attribute) {
                        if (ltrim($attribute->getAttributeClassName(), '\\') !== ltrim($attributeClassName, '\\')) {
                            continue;
                        }
                        /** @var AttributeInterface $attributeExemplar */
                        $attributeExemplar = new ($attribute->getAttributeClassName())(...$attribute->getArguments());
                        $attributeExemplar->onMethod($className, $methodName, $attributeExemplar);
                    }
                }
            }

            if (isset($info['propertyAnnotations'])) {
                /** @var Attribute $attribute */
                foreach ($info['propertyAnnotations'] as $propertyName => $attributesList) {
                    foreach ($attributesList as $attribute) {
                        if (ltrim($attribute->getAttributeClassName(), '\\') !== ltrim($attributeClassName, '\\')) {
                            continue;
                        }
                        /** @var AttributeInterface $attributeExemplar */
                        $attributeExemplar = new ($attribute->getAttributeClassName())(...$attribute->getArguments());
                        $attributeExemplar->onProperty($className, $propertyName, $attributeExemplar);
                    }
                }
            }
        }
    }

    public function getAnnotationsByClassName(string $className) {
        if (!str_starts_with('\\', $className)) {
            $className = '\\'.$className;
        }
        return self::$classBasedThree[$className] ?? [];
    }

    private function getFilesWithClassesRecursively(string $path, &$classes = [])
    {
        $files = glob($path . "/*");
        foreach ($files as $file) {
            if (is_dir($file)) {
                if (in_array(realpath($file), self::$pathToSkip)) continue;
                $this->getFilesWithClassesRecursively($file, $classes);
            } else {
                if (str_ends_with($file, '.php')) {
                    if ($this->doesFileContainClass($file)) {
                        $classes[] = $file;
                    }
                }
            }
        }
        return $classes;
    }

    private function doesFileContainClass(string $file): bool
    {
        // lets check if there is string class MySpecialClass(or another class) in file
        // after that lets use classType

        $regex = '/^class\s+(\w*)\s*/m';
        $content = file_get_contents($file);
        preg_match($regex, $content, $matches);
        if (!isset($matches[1])) {
            return false;
        }

        //now lets get namespace
        $regex = '/^namespace\s+(.*);/m';
        preg_match($regex, $content, $matches);
        if (!isset($matches[1])) {
            return false;
        }
        return true;

    }

    private function createAttributeBasedThree(array $classBasedThree) {
        $three = [];
        foreach ($classBasedThree as $className=>$classInfo) {

            if (array_key_exists('methodAnnotations', $classInfo)) {
                foreach ($classInfo['methodAnnotations'] as $annotations) {
                    foreach ($annotations as $attributeClassName=>$annotationArguments) {
                        if ($attributeClassName==\Attribute::class) continue;
                        if (!array_key_exists($attributeClassName, $three)) $three[$attributeClassName] = [];
                        $three[$attributeClassName][$className] = $classInfo;
                    }
                }
            }
            if (array_key_exists('classAnnotations', $classInfo)) {
                foreach ($classInfo['classAnnotations'] as $attributeClassName=>$annotationArguments) {
                    if ($attributeClassName==\Attribute::class) continue;
                    if (!array_key_exists($attributeClassName, $three)) $three[$attributeClassName] = [];
                    $three[$attributeClassName][$className] = $classInfo;
                }
            }
            if (array_key_exists('propertyAnnotations', $classInfo)) {
                foreach ($classInfo['propertyAnnotations'] as $annotations) {
                    foreach ($annotations as $attributeClassName=>$annotationArguments) {
                        if ($attributeClassName==\Attribute::class) continue;
                        if (!array_key_exists($attributeClassName, $three)) $three[$attributeClassName] = [];
                        $three[$attributeClassName][$className] = $classInfo;
                    }
                }
            }
        }
        return $three;
    }

    private function createClassBasedThree(mixed $classes)
    {
        $three = [];
        foreach ($classes as $filePath) {
            $content = file_get_contents($filePath);
            //lets get namespace of class with regex
            $regex = '/^namespace\s+(.*);/m';
            preg_match($regex, $content, $matches);
            if (!isset($matches[1])) {
                continue;
            }
            $namespace = $matches[1];
            try {
                /** @var ClassType $classType */
                $classType = ClassType::fromCode($content);
            } catch (\Throwable $e) {
                continue;
            }

            $fullClassName = '\\' . $namespace . '\\' . $classType->getName();
            $three[$fullClassName] = [];
            $three[$fullClassName]['classAnnotations'] = [];
            $three[$fullClassName]['methodAnnotations'] = [];
            $three[$fullClassName]['propertyAnnotations'] = [];
            $classAttributes = $classType->getAttributes();
            if (!array_key_exists('classAnnotations', $three[$fullClassName])) $three[$fullClassName]['classAnnotations'] = [];
            if (count($classAttributes) > 0) {
                foreach ($classAttributes as $attribute) {
                    if ($attribute->getName()==\Attribute::class) continue;
                    if (!array_key_exists($attribute->getName(), $three[$fullClassName]['classAnnotations'])) $three[$fullClassName]['classAnnotations'][$attribute->getName()] = [];
                    foreach ($attribute->getArguments() as $key => $argument) {
                        $three[$fullClassName]['classAnnotations'][$attribute->getName()][$key] = $argument;
                    }
                }
            } else {
                unset($three[$fullClassName]['classAnnotations']);
            }

            foreach ($classType->getMethods() as $method) {
                $methodAttributes = $method->getAttributes();
                $methodName = $method->getName();
                $three[$fullClassName]['methodAnnotations'][$methodName] = [];
                if (count($methodAttributes) > 0) {
                    foreach ($methodAttributes as $attribute) {
                        if (!array_key_exists($attribute->getName(), $three[$fullClassName]['methodAnnotations'][$methodName])) $three[$fullClassName]['methodAnnotations'][$methodName][$attribute->getName()] = [];
                        foreach ($attribute->getArguments() as $key => $argument) {
                            $three[$fullClassName]['methodAnnotations'][$methodName][$attribute->getName()][$key] = $argument;
                        }
                    }
                } else {
                    unset($three[$fullClassName]['methodAnnotations'][$methodName]);
                }
            }
            if (count($three[$fullClassName]['methodAnnotations'])===0) unset($three[$fullClassName]['methodAnnotations']);

            foreach ($classType->getProperties() as $property) {
                $propertyAttributes = $property->getAttributes();
                $propertyName = $property->getName();
                $three[$fullClassName]['propertyAnnotations'][$propertyName] = [];
                if (count($propertyAttributes) > 0) {
                    foreach ($propertyAttributes as $attribute) {
                        if (!array_key_exists($attribute->getName(), $three[$fullClassName]['propertyAnnotations'][$propertyName])) $three[$fullClassName]['propertyAnnotations'][$propertyName][$attribute->getName()] = [];
                        foreach ($attribute->getArguments() as $key => $argument) {
                            $three[$fullClassName]['propertyAnnotations'][$propertyName][$attribute->getName()][$key] = $argument;
                        }
                    }
                } else {
                    unset($three[$fullClassName]['propertyAnnotations'][$propertyName]);
                }
            }
            if (count($three[$fullClassName]['propertyAnnotations'])===0) unset($three[$fullClassName]['propertyAnnotations']);
        }

        //lets clean up empty arrays on lowest levels
        foreach ($three as $className=>$arrayInfo) {
            if (count($arrayInfo)===0) {
                unset($three[$className]);
            }
        }

        foreach ($three as $className=>$arrayInfo) {
            if (array_key_exists('methodAnnotations', $arrayInfo)) {
                foreach ($arrayInfo['methodAnnotations'] as $methodName=>$annotations) {
                    foreach ($annotations as $annotationClassName=>$annotationArguments) {
                        $three[$className]['methodAnnotations'][$methodName][$annotationClassName] = new Attribute($annotationClassName, $annotationArguments);
                    }
                }
            }

            if (array_key_exists('classAnnotations', $arrayInfo)) {
                foreach ($arrayInfo['classAnnotations'] as $annotationClassName=>$annotationArguments) {
                    $three[$className]['classAnnotations'][$annotationClassName] = new Attribute($annotationClassName, $annotationArguments);
                }
            }

            if (array_key_exists('propertyAnnotations', $arrayInfo)) {
                foreach ($arrayInfo['propertyAnnotations'] as $propertyName=>$annotations) {
                    foreach ($annotations as $annotationClassName=>$annotationArguments) {
                        $three[$className]['propertyAnnotations'][$propertyName][$annotationClassName] = new Attribute($annotationClassName, $annotationArguments);
                    }
                }
            }
        }

        return $three;

    }

    private function getHashOfAllMimeTypes(string $path, $hash = ''): string
    {
        $files = glob($path . "/*");
        foreach ($files as $file) {
            if (is_dir($file)) {
                $hash = hash('xxh3',   $this->getHashOfAllMimeTypes($file, $hash));
            } else {
                $hash = hash('xxh3',   $hash.filemtime($file));
            }
        }
        return $hash;
    }

    private function addClassesFromRegisteredPackages(array $classes): array
    {
        foreach (self::$registeredPackagePaths as $path) {
            if (!file_exists($path)) continue;
            $classesGot = $this->getFilesWithClassesRecursively($path);
            foreach ($classesGot as $class) {
                $classes[] = $class;
            }
        }
        return $classes;
    }

}
