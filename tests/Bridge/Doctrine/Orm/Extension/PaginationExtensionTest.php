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

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\PaginationExtension;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author Vincent CHALAMON <vincentchalamon@gmail.com>
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class PaginationExtensionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Client override resource, resource override default pagination.
     */
    public function testApplyToCollection()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request(['pagination' => true, 'itemsPerPage' => 20, '_page' => 2]));

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $attributes = [
                'pagination_enabled' => true,
                'pagination_client_enabled' => true,
                'pagination_items_per_page' => 40,
            ];
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(/** $shortName */null, /** $description */null, /** $iri */null, /** $itemOperation */[], /** $collectionOperation */[], /** $attributes */$attributes))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {

            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(40)->willReturn($qbProphecy)->shouldBeCalled();
            $qbProphecy->setMaxResults(40)->shouldBeCalled();

            return $qbProphecy->reveal();
        };

        $createManagerRegistry = function() {
            return $this->prophesize(ManagerRegistry::class)->reveal();
        };

        $extension = new PaginationExtension(
            $createManagerRegistry(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */true,
            /** $clientEnabled */false,
            /** $clientItemsPerPage */false,
            /** $itemsPerPage */30,
            /** $pageParameterName */'_page'
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), /** $resourceClass */'Foo', /** $operationName */'op');
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage Item per page parameter should not be less than or equal to 0
     */
    public function testApplyToCollectionWithItemPerPageZero()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request(['pagination' => true, 'itemsPerPage' => 0, '_page' => 2]));

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $attributes = [
                'pagination_enabled' => true,
                'pagination_client_enabled' => true,
                'pagination_items_per_page' => 0,
            ];
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], [], $attributes))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(40)->willReturn($qbProphecy)->shouldNotBeCalled();
            $qbProphecy->setMaxResults(40)->shouldNotBeCalled();

            return $qbProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */true,
            /** $clientEnabled */false,
            /** $clientItemsPerPage */false,
            /** $itemsPerPage */0,
            /** $pageParameterName */'_page'
        );

        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage Item per page parameter should not be less than or equal to 0
     */
    public function testApplyToCollectionWithItemPerPageLessThen0()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request(['pagination' => true, 'itemsPerPage' => -20, '_page' => 2]));

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $attributes = [
                'pagination_enabled' => true,
                'pagination_client_enabled' => true,
                'pagination_items_per_page' => 0,
            ];
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], [], $attributes))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(40)->willReturn($qbProphecy)->shouldNotBeCalled();
            $qbProphecy->setMaxResults(40)->shouldNotBeCalled();
            return $qbProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */true,
            /** clientEnabled */false,
            /** clientItemsPerPage */false,
            /** $itemsPerPage */-20,
            /** $pageParameterName */'_page'
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    public function testApplyToCollectionWithItemPerPageTooHigh()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request(['pagination' => true, 'itemsPerPage' => 301, '_page' => 2]));

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $attributes = [
                'pagination_enabled' => true,
                'pagination_client_enabled' => true,
                'pagination_client_items_per_page' => true,
            ];
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], [], $attributes))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(300)->willReturn($qbProphecy)->shouldBeCalled();
            $qbProphecy->setMaxResults(300)->shouldBeCalled();

            return $qbProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */true,
            /** $clientEnabled */false,
            /** clientItemsPerPage */false,
            /** $itemsPerPage */30,
            /** $pageParameterName */'_page',
            /** $enabledParameterName */'pagination',
            /** $itemsPerPageParameterName */'itemsPerPage',
            /** $maximumItemPerPage */300
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    /**
     * No request in requestStack => return
     */
    public function testApplyToCollectionNoRequest()
    {
        $createQueryBuilder = function() {
            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(Argument::any())->shouldNotBeCalled();
            $qbProphecy->setMaxResults(Argument::any())->shouldNotBeCalled();

            return $qbProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            new RequestStack(),
            $this->prophesize(ResourceMetadataFactoryInterface::class)->reveal()
        );

        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    public function testApplyToCollectionEmptyRequest()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata( /** $shortName */null, /** $description */null, /** iri */null, /** $itemOperations */[], /** $collectionOperations */[]))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };


        $createQueryBuilder = function() {
            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(0)->willReturn($qbProphecy)->shouldBeCalled();
            $qbProphecy->setMaxResults(30)->shouldBeCalled();

            return $qbProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory()
        );

        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    public function testApplyToCollectionPaginationDisabled()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(/** $shortName */null, /** $description */null, /** $iri */null, /** $itemOperations */[], /** $collectionOperations */[]))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $qbProphecy = $this->prophesize(QueryBuilder::class);
            $qbProphecy->setFirstResult(Argument::any())->shouldNotBeCalled();
            $qbProphecy->setMaxResults(Argument::any())->shouldNotBeCalled();

            return $qbProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */false
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    public function testDefaultSupportPaginationForEveryResource()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(/** $shortName */null, /** $description */null, /** $iri */null, /** $itemOperations */[], /** $collectionOperations */[]))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory()
        );

        $this->assertTrue($extension->supportsResult(/** $resourceClass */'Foo', /** $operationName */'op'));
    }

    public function testEmptyRequestStackWillNotHandlePagination()
    {
        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            new RequestStack(),
            $this->prophesize(ResourceMetadataFactoryInterface::class)->reveal()
        );
        $this->assertFalse($extension->supportsResult('Foo', 'op'));
    }

    public function testSupportsResultEmptyRequest()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(/** $shortName */null, /** $description */null, /** $iri */null, /** $itemOperations */[], /** $collectionOperations */[]))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory()
        );

        $this->assertTrue($extension->supportsResult('Foo', 'op'));
    }

    /** Because $clientEnabled: false */
    public function testSupportsResultClientNotAllowedToPaginate()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request(['pagination' => true]));

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], []))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */false,
            /** $clientEnabled */false
        );
        $this->assertFalse($extension->supportsResult('Foo', 'op'));
    }

    public function testSupportsResultPaginationDisabled()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());

            return $requestStack;
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(/** $shortName */null, /** $description */null, /** $iri */null, /** $itemOperations */[], /** $collectionOperations */[]))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            /** $enabled */false
        );
        $this->assertFalse($extension->supportsResult('Foo', 'op'));
    }
}
