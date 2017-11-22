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

namespace ApiPlatform\Core\Tests\Bridge\Doctrine\Orm\Metadata\Property;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Metadata\Property\DoctrineOrmPropertyMetadataFactory;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadataInfo;

/**
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class DoctrineOrmPropertyMetadataFactoryTest extends \PHPUnit_Framework_TestCase
{
    public function testCreateNoManager()
    {
        $propertyMetadata = new PropertyMetadata();

        $createDecorated = function() use ($propertyMetadata) {

            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };

        $createManagerRegistry = function() {
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->willReturn(null);

            return $managerRegistry->reveal();
        };

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createManagerRegistry(), $createDecorated());

        $this->assertEquals($propertyMetadata, $doctrineOrmPropertyMetadataFactory->create(/** $resourceClass */Dummy::class, /** $property */'id', /** $options */[]));
    }

    public function testCreateNoClassMetadata()
    {
        $propertyMetadata = new PropertyMetadata();

        $createDecorated = function() use ($propertyMetadata) {
            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };

        $createMangerRegistry = function() {
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn(null);

            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->shouldBeCalled()->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createMangerRegistry(), $createDecorated());

        $this->assertEquals($propertyMetadata, $doctrineOrmPropertyMetadataFactory->create(/** $resourceClass */Dummy::class, /** $property */'id', /** $options */[]));
    }

    public function testCreateIsIdentifier()
    {
        $createPropertyMetadata = function() {
            $propertyMetadata = new PropertyMetadata();
            $propertyMetadata = $propertyMetadata->withIdentifier(true);

            return $propertyMetadata;
        };

        $propertyMetadata = $createPropertyMetadata();

        $createDecorated = function() use ($propertyMetadata) {
            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };

        $createManageRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadataInfo::class);

            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata(Dummy::class)->shouldNotBeCalled()->willReturn($classMetadata->reveal());

            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->shouldNotBeCalled()->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createManageRegistry(), $createDecorated());

        $this->assertEquals($propertyMetadata, $doctrineOrmPropertyMetadataFactory->create(/** $resourceClass */Dummy::class, /** $property */'id', /** $options */[]));
    }

    public function testCreateIsWritable()
    {
        $createDecorated = function()  {
            $propertyMetadata = new PropertyMetadata();
            $propertyMetadata = $propertyMetadata->withWritable(false);

            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };

        $createMangerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadataInfo::class);
            $classMetadata->getIdentifier()->shouldBeCalled()->willReturn(['id']);

            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadata->reveal());

            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->shouldBeCalled()->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createMangerRegistry(), $createDecorated());

        $doctrinePropertyMetadata = $doctrineOrmPropertyMetadataFactory->create(Dummy::class, /** $property */'id', /** $options */[]);

        $this->assertEquals(true, $doctrinePropertyMetadata->isIdentifier());
        $this->assertEquals(false, $doctrinePropertyMetadata->isWritable());
    }

    public function testCreateClassMetadataInfo()
    {
        $createDecorated = function() {
            $propertyMetadata = new PropertyMetadata();

            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadataInfo::class);
            $classMetadata->getIdentifier()->shouldBeCalled()->willReturn(['id']);
            $classMetadata->isIdentifierNatural()->shouldBeCalled()->willReturn(true);

            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadata->reveal());

            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->shouldBeCalled()->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createManagerRegistry(), $createDecorated());

        $doctrinePropertyMetadata = $doctrineOrmPropertyMetadataFactory->create(Dummy::class, /** $property */'id', /** $options */[]);

        $this->assertEquals(true, $doctrinePropertyMetadata->isIdentifier());
        $this->assertEquals(true, $doctrinePropertyMetadata->isWritable());
    }

    public function testCreateClassMetadata()
    {
        $createPropertyMetadataFactory = function() {
            $propertyMetadata = new PropertyMetadata();

            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };

        $createMangerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadata::class);
            $classMetadata->getIdentifier()->shouldBeCalled()->willReturn(['id']);

            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadata->reveal());

            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->shouldBeCalled()->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createMangerRegistry(), $createPropertyMetadataFactory());

        $doctrinePropertyMetadata = $doctrineOrmPropertyMetadataFactory->create(Dummy::class, /** $property */'id', /** $options */[]);

        $this->assertEquals(true, $doctrinePropertyMetadata->isIdentifier());
        $this->assertEquals(false, $doctrinePropertyMetadata->isWritable());
    }
}
