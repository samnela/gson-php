<?php
/*
 * Copyright (c) Nate Brunette.
 * Distributed under the MIT License (http://opensource.org/licenses/MIT)
 */

namespace Tebru\Gson\Test\Unit\Internal;

use Doctrine\Common\Annotations\AnnotationReader;
use PHPUnit_Framework_TestCase;
use ReflectionProperty;
use Tebru\Gson\Annotation\Exclude;
use Tebru\Gson\Annotation\Expose;
use Tebru\Gson\Annotation\Since;
use Tebru\Gson\Annotation\Until;
use Tebru\Gson\Internal\AccessorStrategy\GetByMethod;
use Tebru\Gson\Internal\AccessorStrategy\GetByPublicProperty;
use Tebru\Gson\Internal\AccessorStrategy\SetByMethod;
use Tebru\Gson\Internal\AccessorStrategy\SetByPublicProperty;
use Tebru\Gson\Internal\Data\AnnotationCollectionFactory;
use Tebru\Gson\Internal\Data\AnnotationSet;
use Tebru\Gson\Internal\Data\Property;
use Tebru\Gson\Internal\Excluder;
use Tebru\Gson\Internal\PhpType;
use Tebru\Gson\Test\Mock\ExcluderVersionMock;
use Tebru\Gson\Test\Mock\ExclusionStrategies\BarPropertyExclusionStrategy;
use Tebru\Gson\Test\Mock\ExclusionStrategies\ExcludeClassMockExclusionStrategy;
use Tebru\Gson\Test\Mock\ExcluderExcludeMock;
use Tebru\Gson\Test\Mock\ExcluderExposeMock;
use Tebru\Gson\Test\Mock\ExclusionStrategies\FooExclusionStrategy;
use Tebru\Gson\Test\Mock\ExclusionStrategies\FooPropertyExclusionStrategy;
use Tebru\Gson\Test\Mock\Foo;

/**
 * Class ExcluderTest
 *
 * @author Nate Brunette <n@tebru.net>
 * @covers \Tebru\Gson\Internal\Excluder
 */
class ExcluderTest extends PHPUnit_Framework_TestCase
{
    public function testExcludeClassWithoutVersion()
    {
        $excluder = $this->excluder();

        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithVersionLow()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('0.1.0');

        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithVersionEqualSince()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('1');

        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithVersionBetween()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('1.5');

        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithVersionEqualUntil()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('2');

        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithVersionHigh()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('3');

        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithExcludeAnnotation()
    {
        $excluder = $this->excluder();

        self::assertTrue($excluder->excludeClass(ExcluderExcludeMock::class, true));
        self::assertFalse($excluder->excludeClass(ExcluderExcludeMock::class, false));
    }

    public function testExcludeClassWithExposeAnnotation()
    {
        $excluder = $this->excluder();
        $excluder->setRequireExpose(true);

        self::assertFalse($excluder->excludeClass(ExcluderExposeMock::class, true));
        self::assertTrue($excluder->excludeClass(ExcluderExposeMock::class, false));
    }

    public function testExcludeClassWithStrategySerialization()
    {
        $excluder = $this->excluder();
        $excluder->addExclusionStrategy(new FooExclusionStrategy(), true, false);
        $excluder->addExclusionStrategy(new ExcludeClassMockExclusionStrategy(), true, false);

        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludeClassWithStrategyDeserialization()
    {
        $excluder = $this->excluder();
        $excluder->addExclusionStrategy(new FooExclusionStrategy(), false, true);
        $excluder->addExclusionStrategy(new ExcludeClassMockExclusionStrategy(), false, true);

        self::assertFalse($excluder->excludeClass(ExcluderVersionMock::class, true));
        self::assertTrue($excluder->excludeClass(ExcluderVersionMock::class, false));
    }

    public function testExcludePropertyDefaultModifiers()
    {
        $excluder = $this->excluder();

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByPublicProperty('foo'),
            new SetByPublicProperty('foo'),
            new AnnotationSet(),
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testExcludePrivateProperties()
    {
        $excluder = $this->excluder();
        $excluder->setExcludedModifiers(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByPublicProperty('foo'),
            new SetByPublicProperty('foo'),
            new AnnotationSet(),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testExcludeProtectedProperties()
    {
        $excluder = $this->excluder();
        $excluder->setExcludedModifiers(ReflectionProperty::IS_STATIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByPublicProperty('foo'),
            new SetByPublicProperty('foo'),
            new AnnotationSet(),
            ReflectionProperty::IS_PROTECTED
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeWithoutSinceUntilAnnotations()
    {
        $excluder = $this->excluder();
        $excluder->setVersion(1);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet(),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeWithoutVersion()
    {
        $excluder = $this->excluder();

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Until(['value' => '2'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testExcludeBeforeSince()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('1.0.0');

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Since(['value' => '1.0.1'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeEqualToSince()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('1.0.1');

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Since(['value' => '1.0.1'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeAfterSince()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('1.0.2');

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Since(['value' => '1.0.1'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeBeforeUntil()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('1');

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Until(['value' => '2.0.0'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testExcludeEqualToUntil()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('2.0.0');

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Until(['value' => '2.0.0'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testExcludeGreaterThanUntil()
    {
        $excluder = $this->excluder();
        $excluder->setVersion('2.0.1');

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Until(['value' => '2.0.0'])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testExcludeWithExcludeAnnotation()
    {
        $excluder = $this->excluder();

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Exclude([])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testExcludeWithExcludeAnnotationOnlySerialize()
    {
        $excluder = $this->excluder();

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Exclude(['deserialize' => false])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testExcludeWithExcludeAnnotationOnlyDeserialize()
    {
        $excluder = $this->excluder();

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Exclude(['serialize' => false])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeWithExposeAnnotation()
    {
        $excluder = $this->excluder();
        $excluder->setRequireExpose(true);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Expose([])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeWithExposeAnnotationOnlySerialize()
    {
        $excluder = $this->excluder();
        $excluder->setRequireExpose(true);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Expose(['deserialize' => false])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeWithExposeAnnotationOnlyDeserialize()
    {
        $excluder = $this->excluder();
        $excluder->setRequireExpose(true);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Expose(['serialize' => false])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testDoNotExcludeWithExposeAnnotationWithoutRequireExpose()
    {
        $excluder = $this->excluder();

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet([new Expose(['serialize' => false])]),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertFalse($excluder->excludeProperty($property, true));
        self::assertFalse($excluder->excludeProperty($property, false));
    }

    public function testExcludeWithoutExposeAnnotationWithRequireExpose()
    {
        $excluder = $this->excluder();
        $excluder->setRequireExpose(true);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet(),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    public function testExcludeFromStrategy()
    {
        $excluder = $this->excluder();
        $excluder->addExclusionStrategy(new BarPropertyExclusionStrategy(), true, true);
        $excluder->addExclusionStrategy(new FooPropertyExclusionStrategy(), true, true);

        $property = new Property(
            Foo::class,
            'foo',
            'foo',
            new PhpType('string'),
            new GetByMethod('getFoo'),
            new SetByMethod('setFoo'),
            new AnnotationSet(),
            ReflectionProperty::IS_PRIVATE
        );

        self::assertTrue($excluder->excludeProperty($property, true));
        self::assertTrue($excluder->excludeProperty($property, false));
    }

    private function excluder(): Excluder
    {
        return new Excluder(new AnnotationCollectionFactory(new AnnotationReader()));
    }
}
