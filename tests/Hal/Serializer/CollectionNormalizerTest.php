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

namespace ApiPlatform\Core\Tests\Hal\Serializer;

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Core\DataProvider\PaginatorInterface;
use ApiPlatform\Core\Hal\Serializer\CollectionNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CollectionNormalizerTest extends \PHPUnit_Framework_TestCase
{
    public function testSupportsNormalize()
    {
        $createCollectionNormalizer = function() {
            $resourceClassResolverProphecy = $this->prophesize(ResourceClassResolverInterface::class);
            return new CollectionNormalizer($resourceClassResolverProphecy->reveal(), 'page');
        };
        $normalizer = $createCollectionNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(/** $data */[], CollectionNormalizer::FORMAT));
        $this->assertTrue($normalizer->supportsNormalization(new \ArrayObject(), CollectionNormalizer::FORMAT));
        $this->assertFalse($normalizer->supportsNormalization(/** $data */[], 'xml'));
        $this->assertFalse($normalizer->supportsNormalization(new \ArrayObject(), 'xml'));
    }

    public function testNormalizeApiSubLevel()
    {
        $createResourceClassResolver = function() {
            $resourceClassResolverProphecy = $this->prophesize(ResourceClassResolverInterface::class);
            $resourceClassResolverProphecy->getResourceClass()->shouldNotBeCalled();

            return $resourceClassResolverProphecy->reveal();
        };

        $createItemNormalizer = function() {
            $itemNormalizer = $this->prophesize(NormalizerInterface::class);
            $itemNormalizer->normalize('bar', /** $format */null, ['api_sub_level' => true])->willReturn(22);

            return $itemNormalizer->reveal();
        };

        $createCollectionNormalizer = function() use ($createItemNormalizer, $createResourceClassResolver) {
            $normalizer = new CollectionNormalizer($createResourceClassResolver(), 'page');
            $normalizer->setNormalizer($createItemNormalizer());

            return $normalizer;
        };

        $this->assertEquals(['foo' => 22], $createCollectionNormalizer()->normalize(/** $object */['foo' => 'bar'], /** $format */null, /** $context */['api_sub_level' => true]));
    }

    public function testNormalizePaginator()
    {
        $createPaginator = function() {
            $paginatorProphecy = $this->prophesize(PaginatorInterface::class);
            $paginatorProphecy->getCurrentPage()->willReturn(3);
            $paginatorProphecy->getLastPage()->willReturn(7);
            $paginatorProphecy->getItemsPerPage()->willReturn(12);
            $paginatorProphecy->getTotalItems()->willReturn(1312);
            $paginatorProphecy->rewind()->shouldBeCalled();
            $paginatorProphecy->valid()->willReturn(true, false)->shouldBeCalled();
            $paginatorProphecy->current()->willReturn('foo')->shouldBeCalled();
            $paginatorProphecy->next()->willReturn()->shouldBeCalled();
            return $paginatorProphecy->reveal();
        };
        $paginator = $createPaginator();

        $createResourceClassResolver = function() use ($paginator) {
            $resourceClassResolverProphecy = $this->prophesize(ResourceClassResolverInterface::class);
            $resourceClassResolverProphecy->getResourceClass($paginator, null, true)->willReturn('Foo')->shouldBeCalled();

            return $resourceClassResolverProphecy->reveal();
        };

        $itemNormalizer = $this->prophesize(NormalizerInterface::class);
        $itemNormalizer->normalize('foo', null, ['api_sub_level' => true, 'resource_class' => 'Foo'])->willReturn(['_links' => ['self' => '/me'], 'name' => 'Kévin']);

        $normalizer = new CollectionNormalizer($createResourceClassResolver(), 'page');
        $normalizer->setNormalizer($itemNormalizer->reveal());

        $expected = [
            '_links' => [
                'self' => '/?page=3',
                'first' => '/?page=1',
                'last' => '/?page=7',
                'prev' => '/?page=2',
                'next' => '/?page=4',
                'item' => [
                        '/me',
                    ],
            ],
            '_embedded' => [
                    'item' => [
                        [
                            '_links' => [
                                    'self' => '/me',
                                ],
                            'name' => 'Kévin',
                        ],
                    ],
            ],
            'totalItems' => 1312,
            'itemsPerPage' => 12,
        ];
        $this->assertEquals($expected, $normalizer->normalize($paginator));
    }
}
