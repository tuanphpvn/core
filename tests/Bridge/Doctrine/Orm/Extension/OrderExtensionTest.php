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


        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $queryBuilderProphecy->addOrderBy('o.name', 'asc')->shouldBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $queryBuilderProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension('asc');
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionWithWrongOrder()
    {
        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $queryBuilderProphecy->addOrderBy('o.name', 'asc')->shouldNotBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $queryBuilderProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension();

        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionWithOrderOverriden()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn(new ResourceMetadata(null, null, null, null, null, ['order' => ['foo' => 'DESC']]));

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $queryBuilderProphecy->addOrderBy('o.foo', 'DESC')->shouldBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $queryBuilderProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension('asc', $createResourceMetadataFactory());
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionWithOrderOverridenWithNoDirection()
    {

        $createResourcMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn(new ResourceMetadata(null, null, null, null, null, ['order' => ['foo', 'bar' => 'DESC']]));

            return $resourceMetadataFactoryProphecy->reveal();
        };


        $createQueryBuilder = function() {

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $queryBuilderProphecy->addOrderBy('o.foo', 'ASC')->shouldBeCalled();
            $queryBuilderProphecy->addOrderBy('o.bar', 'DESC')->shouldBeCalled();

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->getIdentifier()->shouldBeCalled()->willReturn(['name']);



            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $queryBuilderProphecy->getEntityManager()->shouldBeCalled()->willReturn($emProphecy->reveal());

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new OrderExtension('asc', $createResourcMetadataFactory());
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }
}
