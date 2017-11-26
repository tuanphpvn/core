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

namespace ApiPlatform\Core\Tests\Bridge\Symfony\Routing;

use ApiPlatform\Core\Api\IdentifiersExtractor;
use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Api\UrlGeneratorInterface;
use ApiPlatform\Core\Bridge\Symfony\Routing\IriConverter;
use ApiPlatform\Core\Bridge\Symfony\Routing\RouteNameResolverInterface;
use ApiPlatform\Core\DataProvider\ItemDataProviderInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\RelatedDummy;
use Prophecy\Argument;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * @author Vincent Chalamon <vincentchalamon@gmail.com>
 */
class IriConverterTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage No route matches "/users/3".
     */
    public function testGetItemFromIriNoRouteException()
    {
        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            return $propertyNameCollectionFactoryProphecy->reveal();
        };
        $propertyNameCollectionFactory = $createPropertyNameCollectionFactory();

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            return $propertyMetadataFactoryProphecy->reveal();
        };
        $propertyMetadataFactory = $createPropertyMetadataFactory();

        $itemDataProvider = $this->prophesize(ItemDataProviderInterface::class)->reveal();

        $routeNameResolver = $this->prophesize(RouteNameResolverInterface::class)->reveal();

        $createRouter = function() {
            $routerProphecy = $this->prophesize(RouterInterface::class);
            $routerProphecy->match('/users/3')->willThrow(new RouteNotFoundException())->shouldBeCalledTimes(1);

            return $routerProphecy->reveal();
        };

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $createPropertyMetadataFactory(),
            $itemDataProvider,
            $routeNameResolver,
            $createRouter(),
            null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getItemFromIri('/users/3');
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage No resource associated to "/users/3".
     */
    public function testGetItemFromIriNoResourceException()
    {
        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            return $propertyNameCollectionFactoryProphecy->reveal();
        };
        $propertyNameCollectionFactory = $createPropertyNameCollectionFactory();

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            return $propertyMetadataFactoryProphecy->reveal();
        };
        $propertyMetadataFactory = $createPropertyMetadataFactory();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);

        $createRouter = function() {
            $routerProphecy = $this->prophesize(RouterInterface::class);
            $routerProphecy->match('/users/3')->willReturn([])->shouldBeCalledTimes(1);

            return $routerProphecy->reveal();
        };

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $createRouter(),
            /** $propertyAccessor */null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getItemFromIri('/users/3');
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage Item not found for "/users/3".
     */
    public function testGetItemFromIriItemNotFoundException()
    {
        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            return $propertyNameCollectionFactoryProphecy->reveal();
        };
        $propertyNameCollectionFactory = $createPropertyNameCollectionFactory();

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            return $propertyMetadataFactoryProphecy->reveal();
        };
        $propertyMetadataFactory  = $createPropertyMetadataFactory();

        $createItemDataProvider = function() {
            $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);
            $itemDataProviderProphecy->getItem('AppBundle\Entity\User', 3, null, [])->shouldBeCalledTimes(1);

            return $itemDataProviderProphecy->reveal();
        };


        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);

        $createRouter = function() {
            $routerProphecy = $this->prophesize(RouterInterface::class);
            $routerProphecy->match('/users/3')->willReturn([
                '_api_resource_class' => 'AppBundle\Entity\User',
                'id' => 3,
            ])->shouldBeCalledTimes(1);

            return $routerProphecy->reveal();
        };

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $createItemDataProvider(),
            $routeNameResolverProphecy->reveal(),
            $createRouter(),
            /** $propertyAccessor */null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getItemFromIri('/users/3');
    }

    public function testGetItemFromIri()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);
        $itemDataProviderProphecy->getItem('AppBundle\Entity\User', 3, null, ['fetch_data' => true])
            ->willReturn('foo')
            ->shouldBeCalledTimes(1);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->match('/users/3')->willReturn([
            '_api_resource_class' => 'AppBundle\Entity\User',
            'id' => 3,
        ])->shouldBeCalledTimes(1);

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            /** $propertyAccessor */null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getItemFromIri(/** $iri */'/users/3', /** $context */['fetch_data' => true]);
    }

    public function testGetIriFromResourceClass()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);
        $routeNameResolverProphecy->getRouteName(Dummy::class, OperationType::COLLECTION)->willReturn('dummies');

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->generate('dummies', /** $parameters */[], UrlGeneratorInterface::ABS_PATH)->willReturn('/dummies');

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            /** $propertyAccessor */null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $this->assertEquals('/dummies', $converter->getIriFromResourceClass(Dummy::class));
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unable to generate an IRI for "ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy"
     */
    public function testNotAbleToGenerateGetIriFromResourceClass()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);
        $routeNameResolverProphecy->getRouteName(Dummy::class, OperationType::COLLECTION)->willReturn('dummies');

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->generate('dummies', [], UrlGeneratorInterface::ABS_PATH)->willThrow(new RouteNotFoundException());

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getIriFromResourceClass(Dummy::class);
    }

    public function testGetSubresourceIriFromResourceClass()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);
        $routeNameResolverProphecy->getRouteName(Dummy::class, OperationType::SUBRESOURCE, Argument::type('array'))->willReturn('api_dummies_related_dummies_get_subresource');

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->generate('api_dummies_related_dummies_get_subresource', ['id' => 1], UrlGeneratorInterface::ABS_PATH)->willReturn('/dummies/1/related_dummies');

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $this->assertEquals('/dummies/1/related_dummies', $converter->getSubresourceIriFromResourceClass(Dummy::class, ['subresource_identifiers' => ['id' => 1], 'subresource_resources' => [RelatedDummy::class => 1]]));
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unable to generate an IRI for "ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy"
     */
    public function testNotAbleToGenerateGetSubresourceIriFromResourceClass()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);
        $routeNameResolverProphecy->getRouteName(Dummy::class, OperationType::SUBRESOURCE, Argument::type('array'))->willReturn('dummies');

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->generate('dummies', ['id' => 1], UrlGeneratorInterface::ABS_PATH)->willThrow(new RouteNotFoundException());

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getSubresourceIriFromResourceClass(Dummy::class, ['subresource_identifiers' => ['id' => 1], 'subresource_resources' => [RelatedDummy::class => 1]]);
    }

    public function testGetItemIriFromResourceClass()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);
        $routeNameResolverProphecy->getRouteName(Dummy::class, OperationType::ITEM)->willReturn('api_dummies_get_item');

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->generate('api_dummies_get_item', ['id' => 1], UrlGeneratorInterface::ABS_PATH)->willReturn('/dummies/1');

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $this->assertEquals($converter->getItemIriFromResourceClass(Dummy::class, ['id' => 1]), '/dummies/1');
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage Unable to generate an IRI for "ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy"
     */
    public function testNotAbleToGenerateGetItemIriFromResourceClass()
    {
        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
        $propertyNameCollectionFactory = $propertyNameCollectionFactoryProphecy->reveal();

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactory = $propertyMetadataFactoryProphecy->reveal();

        $itemDataProviderProphecy = $this->prophesize(ItemDataProviderInterface::class);

        $routeNameResolverProphecy = $this->prophesize(RouteNameResolverInterface::class);
        $routeNameResolverProphecy->getRouteName(Dummy::class, OperationType::ITEM)->willReturn('dummies');

        $routerProphecy = $this->prophesize(RouterInterface::class);
        $routerProphecy->generate('dummies', ['id' => 1], UrlGeneratorInterface::ABS_PATH)->willThrow(new RouteNotFoundException());

        $converter = new IriConverter(
            $propertyNameCollectionFactory,
            $propertyMetadataFactory,
            $itemDataProviderProphecy->reveal(),
            $routeNameResolverProphecy->reveal(),
            $routerProphecy->reveal(),
            null,
            new IdentifiersExtractor($propertyNameCollectionFactory, $propertyMetadataFactory)
        );
        $converter->getItemIriFromResourceClass(Dummy::class, ['id' => 1]);
    }
}
