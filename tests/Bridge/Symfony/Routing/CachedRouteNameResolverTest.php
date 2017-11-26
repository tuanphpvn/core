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

use ApiPlatform\Core\Api\OperationType;
use ApiPlatform\Core\Bridge\Symfony\Routing\CachedRouteNameResolver;
use ApiPlatform\Core\Bridge\Symfony\Routing\RouteNameResolverInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use Prophecy\Argument;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

/**
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 */
class CachedRouteNameResolverTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);

        $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);

        $cachedRouteNameResolver = new CachedRouteNameResolver($cacheItemPoolProphecy->reveal(), $decoratedProphecy->reveal());

        $this->assertInstanceOf(RouteNameResolverInterface::class, $cachedRouteNameResolver);
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage No item route associated with the type "AppBundle\Entity\User".
     */
    public function testGetRouteNameForItemRouteWithNoMatchingRoute()
    {
        $cacheItemProphecy = $this->prophesize(CacheItemInterface::class);
        $cacheItemProphecy->isHit()->willReturn(false)->shouldBeCalled();

        $createCacheItemPool = function() use ($cacheItemProphecy) {
            $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);
            $cacheItemPoolProphecy->getItem(Argument::type('string'))->willReturn($cacheItemProphecy);
            $cacheItemPoolProphecy->save($cacheItemProphecy)->shouldNotBeCalled();

            return $cacheItemPoolProphecy->reveal();
        };

        $createDecorated = function() {
            $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);
            $decoratedProphecy->getRouteName('AppBundle\Entity\User', OperationType::ITEM, [])
                ->willThrow(new InvalidArgumentException('No item route associated with the type "AppBundle\Entity\User".'))
                ->shouldBeCalled();

            return $decoratedProphecy->reveal();
        };

        $cachedRouteNameResolver = new CachedRouteNameResolver($createCacheItemPool(), $createDecorated());
        $cachedRouteNameResolver->getRouteName(/** $resourceClass */'AppBundle\Entity\User', /** $operationType */OperationType::ITEM);
    }

    public function testGetRouteNameForItemRouteOnCacheMiss()
    {
        $createCacheItem = function() {
            $cacheItemProphecy = $this->prophesize(CacheItemInterface::class);
            $cacheItemProphecy->isHit()->willReturn(false)->shouldBeCalled();
            $cacheItemProphecy->set('some_item_route')->shouldBeCalled();

            return $cacheItemProphecy->reveal();
        };
        $cacheItem = $createCacheItem();

        $createCacheItemPool = function() use ($cacheItem) {
            $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);
            $cacheItemPoolProphecy->getItem(Argument::type('string'))->willReturn($cacheItem);
            $cacheItemPoolProphecy->save($cacheItem)->willReturn(true)->shouldBeCalled();

            return $cacheItemPoolProphecy->reveal();
        };

        $createDecorate = function() {
            $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);
            $decoratedProphecy->getRouteName('AppBundle\Entity\User', false, [])->willReturn('some_item_route')->shouldBeCalled();

            return $decoratedProphecy->reveal();
        };

        $cachedRouteNameResolver = new CachedRouteNameResolver($createCacheItemPool(), $createDecorate());
        $actual = $cachedRouteNameResolver->getRouteName('AppBundle\Entity\User', /** $operationType */false);

        $this->assertSame('some_item_route', $actual);
    }

    public function testGetRouteNameForItemRouteOnCacheHit()
    {
        $createCacheItem = function() {
            $cacheItemProphecy = $this->prophesize(CacheItemInterface::class);
            $cacheItemProphecy->isHit()->willReturn(true)->shouldBeCalled();
            $cacheItemProphecy->get()->willReturn('some_item_route')->shouldBeCalled();

            return $cacheItemProphecy->reveal();
        };
        $cacheItem = $createCacheItem();

        $createCacheItemPool = function() use ($cacheItem) {
            $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);
            $cacheItemPoolProphecy->getItem(Argument::type('string'))->willReturn($cacheItem);
            $cacheItemPoolProphecy->save($cacheItem)->shouldNotBeCalled();

            return $cacheItemPoolProphecy->reveal();
        };

        $createDecorated = function() {
            $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);
            $decoratedProphecy->getRouteName(Argument::cetera())->shouldNotBeCalled();

            return $decoratedProphecy->reveal();
        };

        $cachedRouteNameResolver = new CachedRouteNameResolver($createCacheItemPool(), $createDecorated());
        $actual = $cachedRouteNameResolver->getRouteName('AppBundle\Entity\User', OperationType::ITEM);

        $this->assertSame('some_item_route', $actual);
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\InvalidArgumentException
     * @expectedExceptionMessage No collection route associated with the type "AppBundle\Entity\User".
     */
    public function testGetRouteNameForCollectionRouteWithNoMatchingRoute()
    {
        $createCacheItem = function() {
            $cacheItemProphecy = $this->prophesize(CacheItemInterface::class);
            $cacheItemProphecy->isHit()->willReturn(false)->shouldBeCalled();

            return $cacheItemProphecy->reveal();
        };
        $cacheItem = $createCacheItem();

        $createCachItemPool = function() use ($cacheItem) {
            $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);
            $cacheItemPoolProphecy->getItem(Argument::type('string'))->willReturn($cacheItem);
            $cacheItemPoolProphecy->save($cacheItem)->shouldNotBeCalled();

            return $cacheItemPoolProphecy->reveal();
        };

        $createDecorated = function() {
            $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);
            $decoratedProphecy->getRouteName('AppBundle\Entity\User', OperationType::COLLECTION, [])
                ->willThrow(new InvalidArgumentException('No collection route associated with the type "AppBundle\Entity\User".'))
                ->shouldBeCalled();

            return $decoratedProphecy->reveal();
        };

        $cachedRouteNameResolver = new CachedRouteNameResolver($createCachItemPool(), $createDecorated());
        $cachedRouteNameResolver->getRouteName('AppBundle\Entity\User', OperationType::COLLECTION);
    }

    public function testGetRouteNameForCollectionRouteOnCacheMiss()
    {
        $createCacheItem = function() {
            $cacheItemProphecy = $this->prophesize(CacheItemInterface::class);
            $cacheItemProphecy->isHit()->willReturn(false)->shouldBeCalled();
            $cacheItemProphecy->set('some_collection_route')->shouldBeCalled();

            return $cacheItemProphecy->reveal();
        };
        $cacheItem = $createCacheItem();

        $createCacheItemPool = function() use ($cacheItem) {
            $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);
            $cacheItemPoolProphecy->getItem(Argument::type('string'))->willReturn($cacheItem);
            $cacheItemPoolProphecy->save($cacheItem)->willReturn(true)->shouldBeCalled();

            return $cacheItemPoolProphecy->reveal();
        };

        $createDecorated = function() {
            $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);
            $decoratedProphecy->getRouteName('AppBundle\Entity\User', true, [])->willReturn('some_collection_route')->shouldBeCalled();

            return $decoratedProphecy->reveal();
        };

        $cachedRouteNameResolver = new CachedRouteNameResolver($createCacheItemPool(), $createDecorated());
        $actual = $cachedRouteNameResolver->getRouteName('AppBundle\Entity\User', true);

        $this->assertSame('some_collection_route', $actual);
    }

    public function testGetRouteNameForCollectionRouteOnCacheHit()
    {
        $createCacheItem = function() {
            $cacheItemProphecy = $this->prophesize(CacheItemInterface::class);
            $cacheItemProphecy->isHit()->willReturn(true)->shouldBeCalled();
            $cacheItemProphecy->get()->willReturn('some_collection_route')->shouldBeCalled();

            return $cacheItemProphecy->reveal();
        };
        $cacheItem = $createCacheItem();

        $createCacheItemPool = function() use ($cacheItem) {
            $cacheItemPoolProphecy = $this->prophesize(CacheItemPoolInterface::class);
            $cacheItemPoolProphecy->getItem(Argument::type('string'))->willReturn($cacheItem);
            $cacheItemPoolProphecy->save($cacheItem)->shouldNotBeCalled();

            return $cacheItemPoolProphecy->reveal();
        };

        $createDecorated = function() {
            $decoratedProphecy = $this->prophesize(RouteNameResolverInterface::class);
            $decoratedProphecy->getRouteName(Argument::cetera())->shouldNotBeCalled();

            return $decoratedProphecy->reveal();
        };

        $cachedRouteNameResolver = new CachedRouteNameResolver($createCacheItemPool(), $createDecorated());
        $actual = $cachedRouteNameResolver->getRouteName('AppBundle\Entity\User', OperationType::COLLECTION);

        $this->assertSame('some_collection_route', $actual);
    }
}
