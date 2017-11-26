<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace ApiPlatform\Core\Tests\Metadata\Property\Factory;

use ApiPlatform\Core\Metadata\Property\Factory\InheritedPropertyMetadataFactory;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\DummyTableInheritance;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\DummyTableInheritanceChild;
use Symfony\Component\PropertyInfo\Type;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class InheritedPropertyMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $createResourceNameCollectionFactory = function() {
            $resourceNameCollectionFactory = $this->prophesize(ResourceNameCollectionFactoryInterface::class);
            $resourceNameCollectionFactory->create()->willReturn(new ResourceNameCollection([DummyTableInheritance::class, DummyTableInheritanceChild::class]))->shouldBeCalled();
            return $resourceNameCollectionFactory->reveal();
        };

        $type = new Type(Type::BUILTIN_TYPE_STRING);

        $createPropertyMetadataFactory = function() use ($type) {
            $nicknameMetadata = new PropertyMetadata($type, 'nickname', true, true, false, false, true, false, 'http://example.com/foo', null, ['foo' => 'bar']);
            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(DummyTableInheritance::class, 'nickname', /** $option */[])->willReturn($nicknameMetadata)->shouldBeCalled();
            $propertyMetadataFactory->create(DummyTableInheritanceChild::class, 'nickname', /** $option */[])->willReturn($nicknameMetadata)->shouldBeCalled();

            return $propertyMetadataFactory->reveal();
        };

        $factory = new InheritedPropertyMetadataFactory($createResourceNameCollectionFactory(), $createPropertyMetadataFactory());
        $metadata = $factory->create(DummyTableInheritance::class, 'nickname');

        $shouldBe = new PropertyMetadata($type, 'nickname', /** $readable */true, /** $writable */true, /** $readableLink */false, /** $writableLink */false, /** $required */true, /** $identifier */false, 'http://example.com/foo', DummyTableInheritanceChild::class, ['foo' => 'bar']);
        $this->assertEquals($shouldBe, $metadata);
    }
}
