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
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], [], $attributes))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };



        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(40)->willReturn($queryBuilderProphecy)->shouldBeCalled();
            $queryBuilderProphecy->setMaxResults(40)->shouldBeCalled();

            return $queryBuilderProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            true,
            false,
            false,
            30,
            '_page'
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
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
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(40)->willReturn($queryBuilderProphecy)->shouldNotBeCalled();
            $queryBuilderProphecy->setMaxResults(40)->shouldNotBeCalled();

            return $queryBuilderProphecy->reveal();
        };

        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            true,
            false,
            false,
            0,
            '_page'
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
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(40)->willReturn($queryBuilderProphecy)->shouldNotBeCalled();
            $queryBuilderProphecy->setMaxResults(40)->shouldNotBeCalled();
            return $queryBuilderProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            true,
            false,
            false,
            -20,
            '_page'
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
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(300)->willReturn($queryBuilderProphecy)->shouldBeCalled();
            $queryBuilderProphecy->setMaxResults(300)->shouldBeCalled();

            return $queryBuilderProphecy->reveal();
        };



        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            true,
            false,
            false,
            30,
            '_page',
            'pagination',
            'itemsPerPage',
            300
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    public function testApplyToCollectionNoRequest()
    {
        $createQueryBuidler = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(Argument::any())->shouldNotBeCalled();
            $queryBuilderProphecy->setMaxResults(Argument::any())->shouldNotBeCalled();

            return $queryBuilderProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            new RequestStack(),
            $this->prophesize(ResourceMetadataFactoryInterface::class)->reveal()
        );
        $extension->applyToCollection($createQueryBuidler(), new QueryNameGenerator(), 'Foo', 'op');
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
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], []))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };


        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(0)->willReturn($queryBuilderProphecy)->shouldBeCalled();
            $queryBuilderProphecy->setMaxResults(30)->shouldBeCalled();

            return $queryBuilderProphecy->reveal();
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
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], []))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->setFirstResult(Argument::any())->shouldNotBeCalled();
            $queryBuilderProphecy->setMaxResults(Argument::any())->shouldNotBeCalled();

            return $queryBuilderProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            false
        );
        $extension->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), 'Foo', 'op');
    }

    public function testSupportsResult()
    {
        $createRequestStack = function() {
            $requestStack = new RequestStack();
            $requestStack->push(new Request());

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
            $createResourceMetadataFactory()
        );
        $this->assertTrue($extension->supportsResult('Foo', 'op'));
    }

    public function testSupportsResultNoRequest()
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
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], []))->shouldBeCalled();

            return $resourceMetadataFactoryProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory()
        );
        $this->assertTrue($extension->supportsResult('Foo', 'op'));
    }

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
            false,
            false
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
            $resourceMetadataFactoryProphecy->create('Foo')->willReturn(new ResourceMetadata(null, null, null, [], []))->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };


        $extension = new PaginationExtension(
            $this->prophesize(ManagerRegistry::class)->reveal(),
            $createRequestStack(),
            $createResourceMetadataFactory(),
            false
        );
        $this->assertFalse($extension->supportsResult('Foo', 'op'));
    }
}
