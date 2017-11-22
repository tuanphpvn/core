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

use ApiPlatform\Core\Api\FilterCollection;
use ApiPlatform\Core\Api\FilterInterface as ApiFilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\FilterExtension;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use Doctrine\ORM\QueryBuilder;
use Psr\Container\ContainerInterface;

/**
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
class FilterExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testApplyToCollectionWithValidFilters()
    {
        $createResourceMetadataFactory = function() {
            $dummyMetadata = new ResourceMetadata(
                /** $shortName */'dummy',
                /** $description */'dummy',
                /** $uri */'#dummy',
                /** $itemOperation */['get' => ['method' => 'GET'], 'put' => ['method' => 'PUT']],
                /** $collectionOperation */['get' => ['method' => 'GET', 'filters' => ['dummyFilter', 'dummyBadFilter']], 'post' => ['method' => 'POST'], 'custom' => ['method' => 'GET', 'path' => '/foo'], 'custom2' => ['method' => 'POST', 'path' => '/foo']], []);
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn($dummyMetadata);

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            return $queryBuilderProphecy->reveal();
        };

        $qb = $createQueryBuilder();

        $createOrmFilter = function() use ($qb) {
            $ormFilterProphecy = $this->prophesize(FilterInterface::class);
            $ormFilterProphecy->apply($qb, new QueryNameGenerator(), Dummy::class, 'get')->shouldBeCalled();

            return $ormFilterProphecy->reveal();
        };

        $createFilterLocator = function() use ($createOrmFilter) {
            $ordinaryFilterProphecy = $this->prophesize(ApiFilterInterface::class);
            $filterLocatorProphecy = $this->prophesize(ContainerInterface::class);
            $filterLocatorProphecy->has('dummyFilter')->willReturn(true)->shouldBeCalled();
            $filterLocatorProphecy->has('dummyBadFilter')->willReturn(true)->shouldBeCalled();
            $filterLocatorProphecy->get('dummyFilter')->willReturn($createOrmFilter())->shouldBeCalled();
            $filterLocatorProphecy->get('dummyBadFilter')->willReturn($ordinaryFilterProphecy->reveal())->shouldBeCalled();

            return $filterLocatorProphecy->reveal();
        };

        $orderExtensionTest = new FilterExtension($createResourceMetadataFactory(), $createFilterLocator());
        $orderExtensionTest->applyToCollection($qb, new QueryNameGenerator(), Dummy::class, 'get');
    }

    /**
     * @group legacy
     * @expectedDeprecation The ApiPlatform\Core\Api\FilterCollection class is deprecated since version 2.1 and will be removed in 3.0. Provide an implementation of Psr\Container\ContainerInterface instead.
     */
    public function testApplyToCollectionWithValidFiltersAndDeprecatedFilterCollection()
    {
        $createResourceMetdataFactory = function() {
            $dummyMetadata = new ResourceMetadata(
                /** $shortName */'dummy',
                /** $description */'dummy',
                /** $iri */'#dummy',
                /** $itemOperation */['get' => ['method' => 'GET'], 'put' => ['method' => 'PUT']],
                /** $collectionOperation */['get' => ['method' => 'GET', 'filters' => ['dummyFilter']], 'post' => ['method' => 'POST'], 'custom' => ['method' => 'GET', 'path' => '/foo'], 'custom2' => ['method' => 'POST', 'path' => '/foo']], []);

            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn($dummyMetadata);

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            return $queryBuilderProphecy->reveal();
        };

        $qb = $createQueryBuilder();

        $createFilter = function() use ($qb) {
            $filterProphecy = $this->prophesize(FilterInterface::class);
            $filterProphecy->apply($qb, new QueryNameGenerator(), Dummy::class, 'get')->shouldBeCalled();

            return $filterProphecy->reveal();
        };

        $orderExtensionTest = new FilterExtension($createResourceMetdataFactory(), /** $filterLocator */new FilterCollection(['dummyFilter' => $createFilter()]));
        $orderExtensionTest->applyToCollection($qb, new QueryNameGenerator(), Dummy::class, 'get');
    }

    /**
     * @group legacy
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage The "$filterLocator" argument is expected to be an implementation of the "Psr\Container\ContainerInterface" interface.
     */
    public function testConstructWithInvalidFilterLocator()
    {
        new FilterExtension($this->prophesize(ResourceMetadataFactoryInterface::class)->reveal(), new \ArrayObject());
    }

    public function testApplyToCollectionWithoutFilters()
    {

        $createResourceMetadataFactory = function() {
            $dummyMetadata = new ResourceMetadata(
                /** $shortName */'dummy',
                /** $description */'dummy',
                /** $iri */'#dummy',
                /** $itemOperation */['get' => ['method' => 'GET'], 'put' => ['method' => 'PUT']],
                /** $collectionOperation */['get' => ['method' => 'GET'], 'post' => ['method' => 'POST'], 'custom' => ['method' => 'GET', 'path' => '/foo'], 'custom2' => ['method' => 'POST', 'path' => '/foo']]);

            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldBeCalled()->willReturn($dummyMetadata);

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            return $this->prophesize(QueryBuilder::class)->reveal();
        };

        $createFilterLocator = function() {
            return $this->prophesize(ContainerInterface::class)->reveal();
        };

        $orderExtensionTest = new FilterExtension($createResourceMetadataFactory(), $createFilterLocator());
        $orderExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class, 'get');
    }
}
