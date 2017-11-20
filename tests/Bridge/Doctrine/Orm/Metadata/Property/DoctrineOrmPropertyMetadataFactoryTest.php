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

        $createPropertyMetadataFactory = function() use ($propertyMetadata) {

            $propertyMetadataFactory = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactory->create(Dummy::class, 'id', [])->shouldBeCalled()->willReturn($propertyMetadata);

            return $propertyMetadataFactory->reveal();
        };


        $createManagerRegistry = function() {
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass(Dummy::class)->willReturn(null);

            return $managerRegistry->reveal();
        };


        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createManagerRegistry(), $createPropertyMetadataFactory());

        $this->assertEquals($doctrineOrmPropertyMetadataFactory->create(Dummy::class, 'id', []), $propertyMetadata);
    }

    public function testCreateNoClassMetadata()
    {
        $propertyMetadata = new PropertyMetadata();

        $createPropertyMetadataFactory = function() use ($propertyMetadata) {
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

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createMangerRegistry(), $createPropertyMetadataFactory());

        $this->assertEquals($doctrineOrmPropertyMetadataFactory->create(Dummy::class, 'id', []), $propertyMetadata);
    }

    public function testCreateIsIdentifier()
    {
        $createPropertyMetadata = function() {
            $propertyMetadata = new PropertyMetadata();
            $propertyMetadata = $propertyMetadata->withIdentifier(true);

            return $propertyMetadata;
        };

        $propertyMetadata = $createPropertyMetadata();

        $createPropertyMetadataFactory = function() use ($propertyMetadata) {
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

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createManageRegistry(), $createPropertyMetadataFactory());

        $this->assertEquals($doctrineOrmPropertyMetadataFactory->create(Dummy::class, 'id', []), $propertyMetadata);
    }

    public function testCreateIsWritable()
    {


        $createPropertyMetadataFactory = function()  {

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

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createMangerRegistry(), $createPropertyMetadataFactory());

        $doctrinePropertyMetadata = $doctrineOrmPropertyMetadataFactory->create(Dummy::class, 'id', []);

        $this->assertEquals($doctrinePropertyMetadata->isIdentifier(), true);
        $this->assertEquals($doctrinePropertyMetadata->isWritable(), false);
    }

    public function testCreateClassMetadataInfo()
    {
        $createPropertyMetadataFactory = function() {
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

        $doctrineOrmPropertyMetadataFactory = new DoctrineOrmPropertyMetadataFactory($createManagerRegistry(), $createPropertyMetadataFactory());

        $doctrinePropertyMetadata = $doctrineOrmPropertyMetadataFactory->create(Dummy::class, 'id', []);

        $this->assertEquals($doctrinePropertyMetadata->isIdentifier(), true);
        $this->assertEquals($doctrinePropertyMetadata->isWritable(), true);
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

        $doctrinePropertyMetadata = $doctrineOrmPropertyMetadataFactory->create(Dummy::class, 'id', []);

        $this->assertEquals($doctrinePropertyMetadata->isIdentifier(), true);
        $this->assertEquals($doctrinePropertyMetadata->isWritable(), false);
    }
}
