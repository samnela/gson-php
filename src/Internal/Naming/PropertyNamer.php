<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Gson\Internal\Naming;

use ReflectionProperty;
use Tebru\Collection\SetInterface;
use Tebru\Gson\Annotation\SerializedName;
use Tebru\Gson\PropertyNamingStrategy;

/**
 * Class PropertyNamer
 *
 * Gets the property name from annotation or naming strategy
 *
 * @author Nate Brunette <n@tebru.net>
 */
final class PropertyNamer
{
    /**
     * @var PropertyNamingStrategy
     */
    private $propertyNamingStrategy;

    /**
     * Constructor
     *
     * @param PropertyNamingStrategy $propertyNamingStrategy
     */
    public function __construct(PropertyNamingStrategy $propertyNamingStrategy)
    {
        $this->propertyNamingStrategy = $propertyNamingStrategy;
    }

    /**
     * Get the serialized version of the property name
     *
     * @param ReflectionProperty $reflectionProperty
     * @param SetInterface $annotations
     * @return string
     */
    public function serializedName(ReflectionProperty $reflectionProperty, SetInterface $annotations): string
    {
        $serializedName = $annotations->find(function ($element) { return $element instanceof SerializedName; });
        if (null !== $serializedName) {
            return $serializedName->getName();
        }

        return $this->propertyNamingStrategy->translateName($reflectionProperty->getName());
    }
}