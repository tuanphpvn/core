<?php

/*
 * This file is part of the API Platform project.
 *
 * (c) Kévin Dunglas <dunglas@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);

namespace ApiPlatform\Core\Tests\Bridge\Doctrine\Orm\Extension;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\OrderExtension;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

/**
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
class OrderExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testApplyToCollectionWithValidOrder()
    {
        $createQueryBuilder = function () {
            $qbProphecy = $this->prophesize(QueryBuilder::class);

            $qbProphecy->addOrderBy('o.name', 'asc')->shouldBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $qbProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $qbProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension(/** $order */
            'asc');
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), /** $resourceClass */
            Dummy::class);
    }

    public function testApplyToCollectionWithWrongOrder()
    {
        $createQueryBuilder = function () {
            $qbProphecy = $this->prophesize(QueryBuilder::class);

            $qbProphecy->addOrderBy('o.name', 'asc')->shouldNotBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $qbProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $qbProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension();

        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionWithOrderOverridden()
    {
        $createResourceMetadataFactory = function () {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn(new ResourceMetadata(
                    /** $shortName */ null,
                    /** $description */ null,
                    /** $iri */ null,
                    /** $itemOperations */ null,
                    /** $collectionOperations */ null,
                    /** $attributes */ ['order' => ['foo' => 'DESC']])
            );

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function () {

            $qbProphecy = $this->prophesize(QueryBuilder::class);

            $qbProphecy->addOrderBy('o.foo', 'DESC')->shouldBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $qbProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $qbProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension('asc', $createResourceMetadataFactory());
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionWithOrderOverriddenWithNoDirection()
    {

        $createResourceMetadataFactory = function () {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn(new ResourceMetadata(
                /** $shortName */null,
                /** $description */null,
                /** $iri */null,
                /** $itemOperations */null,
                /** $collectionOperations */null,
                /** $attributes */['order' => ['foo', 'bar' => 'DESC']]));

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function () {

            $qbProphecy = $this->prophesize(QueryBuilder::class);

            $qbProphecy->addOrderBy('o.foo', 'ASC')->shouldBeCalled();
            $qbProphecy->addOrderBy('o.bar', 'DESC')->shouldBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);


            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $qbProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $qbProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension('asc', $createResourceMetadataFactory());
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }
}
