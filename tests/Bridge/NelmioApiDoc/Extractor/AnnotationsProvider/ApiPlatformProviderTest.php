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

namespace ApiPlatform\Core\Tests\Bridge\NelmioApiDoc\Extractor\AnnotationsProvider;

use ApiPlatform\Core\Api\FilterCollection;
use ApiPlatform\Core\Api\FilterInterface;
use ApiPlatform\Core\Bridge\NelmioApiDoc\Extractor\AnnotationsProvider\ApiPlatformProvider;
use ApiPlatform\Core\Bridge\NelmioApiDoc\Parser\ApiPlatformParser;
use ApiPlatform\Core\Bridge\Symfony\Routing\OperationMethodResolverInterface;
use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Metadata\Resource\ResourceNameCollection;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Nelmio\ApiDocBundle\Extractor\AnnotationsProviderInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 */
class ApiPlatformProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $createResourceNameCollectionFactory = function() {
            $resourceNameCollectionFactoryProphecy = $this->prophesize(ResourceNameCollectionFactoryInterface::class);
            return $resourceNameCollectionFactoryProphecy->reveal();
        };

        $createDocumentationNormalizer = function() {
            $documentationNormalizerProphecy = $this->prophesize(NormalizerInterface::class);
            return $documentationNormalizerProphecy->reveal();
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            return  $resourceMetadataFactoryProphecy->reveal();
        };

        $createFilterLocator = function() {
            $filterLocatorProphecy = $this->prophesize(ContainerInterface::class);
            return $filterLocatorProphecy->reveal();
        };

        $createOperationMethodResolver = function() {
            $operationMethodResolverProphecy = $this->prophesize(OperationMethodResolverInterface::class);
            return $operationMethodResolverProphecy->reveal();
        };

        $apiPlatformProvider = new ApiPlatformProvider($createResourceNameCollectionFactory(), $createDocumentationNormalizer(), $createResourceMetadataFactory(), $createFilterLocator(), $createOperationMethodResolver());
        $this->assertInstanceOf(AnnotationsProviderInterface::class, $apiPlatformProvider);
    }

    public function getFilterLocator()
    {
        $createFilterLocator = function() {
            $dummySearchFilterProphecy = $this->prophesize(FilterInterface::class);
            $dummySearchFilterProphecy->getDescription(Dummy::class)->willReturn([
                'name' => [
                    'property' => 'name',
                    'type' => 'string',
                    'required' => 'false',
                    'strategy' => 'partial',
                ],
            ])->shouldBeCalled();

            $filterLocatorProphecy = $this->prophesize(ContainerInterface::class);
            $filterLocatorProphecy->has('my_dummy.search')->willReturn(true)->shouldBeCalled();
            $filterLocatorProphecy->get('my_dummy.search')->willReturn($dummySearchFilterProphecy->reveal())->shouldBeCalled();

            return $filterLocatorProphecy->reveal();
        };

        $createDeprecated = function() {
            $dummySearchFilterProphecy = $this->prophesize(FilterInterface::class);
            $dummySearchFilterProphecy->getDescription(Dummy::class)->willReturn([
                'name' => [
                    'property' => 'name',
                    'type' => 'string',
                    'required' => 'false',
                    'strategy' => 'partial',
                ],
            ])->shouldBeCalled();

            return new FilterCollection(['my_dummy.search' => $dummySearchFilterProphecy->reveal()]);
        };

        return [['simple' => $createFilterLocator()],
            ['deprecated' => $createDeprecated(), [
                    '@expectedDeprecation' => 'The ApiPlatform\Core\Api\FilterCollection class is deprecated since version 2.1 and will be removed in 3.0. Provide an implementation of Psr\Container\ContainerInterface instead.',
                ]
            ]
        ];
    }

    /**
     * @group legacy
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage
     */
    public function testConstructWithInvalidFilterLocator()
    {
        new ApiPlatformProvider(
            $this->prophesize(ResourceNameCollectionFactoryInterface::class)->reveal(),
            $this->prophesize(NormalizerInterface::class)->reveal(),
            $this->prophesize(ResourceMetadataFactoryInterface::class)->reveal(),
            new \ArrayObject(),
            $this->prophesize(OperationMethodResolverInterface::class)->reveal()
        );
    }

    /**
     *@dataProvider getFilterLocator
     */
    public function testGetAnnotations($filterLocator, $arrError = [])
    {
        if(!empty($arrError)) {
            if(isset($arrError['@expectedDeprecation'])) {
//                $this->expectException($arrError['@expectedDeprecation']);
            }
        }
        $createResourceNameCollectionFactory = function() {
            $resourceNameCollectionFactoryProphecy = $this->prophesize(ResourceNameCollectionFactoryInterface::class);
            $resourceNameCollectionFactoryProphecy->create()->willReturn(new ResourceNameCollection([Dummy::class]))->shouldBeCalled();

            return $resourceNameCollectionFactoryProphecy->reveal();
        };

        $createApiDocumentationNormalizer = function() {
            $apiDocumentationBuilderProphecy = $this->prophesize(NormalizerInterface::class);
            $hydraDoc = $this->getHydraDoc();
            $apiDocumentationBuilderProphecy->normalize(new Documentation(new ResourceNameCollection([Dummy::class])))->willReturn($hydraDoc)->shouldBeCalled();

            return $apiDocumentationBuilderProphecy->reveal();
        };

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $dummyResourceMetadata = (new ResourceMetadata())
                ->withShortName('Dummy')
                ->withItemOperations([
                    'get' => [
                        'method' => 'GET',
                    ],
                    'put' => [
                        'method' => 'PUT',
                    ],
                    'delete' => [
                        'method' => 'DELETE',
                    ],
                ])
                ->withCollectionOperations([
                    'get' => [
                        'filters' => [
                            'my_dummy.search',
                        ],
                        'method' => 'GET',
                    ],
                    'post' => [
                        'method' => 'POST',
                    ],
                ]);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn($dummyResourceMetadata)->shouldBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createOperationMethodResolver = function() {
            $operationMethodResolverProphecy = $this->prophesize(OperationMethodResolverInterface::class);
            $operationMethodResolverProphecy->getCollectionOperationMethod(Dummy::class, 'get')->willReturn('GET')->shouldBeCalled();
            $operationMethodResolverProphecy->getCollectionOperationMethod(Dummy::class, 'post')->willReturn('POST')->shouldBeCalled();
            $operationMethodResolverProphecy->getItemOperationMethod(Dummy::class, 'get')->willReturn('GET')->shouldBeCalled();
            $operationMethodResolverProphecy->getItemOperationMethod(Dummy::class, 'put')->willReturn('PUT')->shouldBeCalled();
            $operationMethodResolverProphecy->getItemOperationMethod(Dummy::class, 'delete')->willReturn('DELETE')->shouldBeCalled();
            $operationMethodResolverProphecy->getCollectionOperationRoute(Dummy::class, 'get')->willReturn((new Route('/dummies'))->setMethods(['GET']))->shouldBeCalled();
            $operationMethodResolverProphecy->getCollectionOperationRoute(Dummy::class, 'post')->willReturn((new Route('/dummies'))->setMethods(['POST']))->shouldBeCalled();
            $operationMethodResolverProphecy->getItemOperationRoute(Dummy::class, 'get')->willReturn((new Route('/dummies/{id}'))->setMethods(['GET']))->shouldBeCalled();
            $operationMethodResolverProphecy->getItemOperationRoute(Dummy::class, 'put')->willReturn((new Route('/dummies/{id}'))->setMethods(['PUT']))->shouldBeCalled();
            $operationMethodResolverProphecy->getItemOperationRoute(Dummy::class, 'delete')->willReturn((new Route('/dummies/{id}'))->setMethods(['DELETE']))->shouldBeCalled();

            return $operationMethodResolverProphecy->reveal();
        };

        $apiPlatformProvider = new ApiPlatformProvider($createResourceNameCollectionFactory(), $createApiDocumentationNormalizer(), $createResourceMetadataFactory(), $filterLocator, $createOperationMethodResolver());

        $actual = $apiPlatformProvider->getAnnotations();

        $this->assertInternalType('array', $actual);
        $this->assertCount(5, $actual);

        $this->assertInstanceOf(ApiDoc::class, $actual[0]);
        $this->assertEquals('/dummies', $actual[0]->getResource());
        $this->assertEquals('Retrieves the collection of Dummy resources.', $actual[0]->getDescription());
        $this->assertEquals('Dummy', $actual[0]->getResourceDescription());
        $this->assertEquals('Dummy', $actual[0]->getSection());
        $this->assertEquals(sprintf('%s:%s:%s', ApiPlatformParser::OUT_PREFIX, Dummy::class, 'get'), $actual[0]->getOutput());
        $this->assertEquals([
            'name' => [
                'property' => 'name',
                'type' => 'string',
                'required' => 'false',
                'strategy' => 'partial',
            ],
        ], $actual[0]->getFilters());
        $this->assertInstanceOf(Route::class, $actual[0]->getRoute());
        $this->assertEquals('/dummies', $actual[0]->getRoute()->getPath());
        $this->assertEquals(['GET'], $actual[0]->getRoute()->getMethods());

        $this->assertInstanceOf(ApiDoc::class, $actual[1]);
        $this->assertEquals('/dummies', $actual[1]->getResource());
        $this->assertEquals('Creates a Dummy resource.', $actual[1]->getDescription());
        $this->assertEquals('Dummy', $actual[1]->getResourceDescription());
        $this->assertEquals('Dummy', $actual[1]->getSection());
        $this->assertEquals(sprintf('%s:%s:%s', ApiPlatformParser::IN_PREFIX, Dummy::class, 'post'), $actual[1]->getInput());
        $this->assertEquals(sprintf('%s:%s:%s', ApiPlatformParser::OUT_PREFIX, Dummy::class, 'post'), $actual[1]->getOutput());
        $this->assertInstanceOf(Route::class, $actual[1]->getRoute());
        $this->assertEquals('/dummies', $actual[1]->getRoute()->getPath());
        $this->assertEquals(['POST'], $actual[1]->getRoute()->getMethods());

        $this->assertInstanceOf(ApiDoc::class, $actual[2]);
        $this->assertEquals('/dummies/{id}', $actual[2]->getResource());
        $this->assertEquals('Retrieves Dummy resource.', $actual[2]->getDescription());
        $this->assertEquals('Dummy', $actual[2]->getResourceDescription());
        $this->assertEquals('Dummy', $actual[2]->getSection());
        $this->assertEquals(sprintf('%s:%s:%s', ApiPlatformParser::OUT_PREFIX, Dummy::class, 'get'), $actual[2]->getOutput());
        $this->assertInstanceOf(Route::class, $actual[2]->getRoute());
        $this->assertEquals('/dummies/{id}', $actual[2]->getRoute()->getPath());
        $this->assertEquals(['GET'], $actual[2]->getRoute()->getMethods());

        $this->assertInstanceOf(ApiDoc::class, $actual[3]);
        $this->assertEquals('/dummies/{id}', $actual[3]->getResource());
        $this->assertEquals('Replaces the Dummy resource.', $actual[3]->getDescription());
        $this->assertEquals('Dummy', $actual[3]->getResourceDescription());
        $this->assertEquals('Dummy', $actual[3]->getSection());
        $this->assertEquals(sprintf('%s:%s:%s', ApiPlatformParser::IN_PREFIX, Dummy::class, 'put'), $actual[3]->getInput());
        $this->assertEquals(sprintf('%s:%s:%s', ApiPlatformParser::OUT_PREFIX, Dummy::class, 'put'), $actual[3]->getOutput());
        $this->assertInstanceOf(Route::class, $actual[3]->getRoute());
        $this->assertEquals('/dummies/{id}', $actual[3]->getRoute()->getPath());
        $this->assertEquals(['PUT'], $actual[3]->getRoute()->getMethods());

        $this->assertInstanceOf(ApiDoc::class, $actual[4]);
        $this->assertEquals('/dummies/{id}', $actual[4]->getResource());
        $this->assertEquals('Deletes the Dummy resource.', $actual[4]->getDescription());
        $this->assertEquals('Dummy', $actual[4]->getResourceDescription());
        $this->assertEquals('Dummy', $actual[4]->getSection());
        $this->assertInstanceOf(Route::class, $actual[4]->getRoute());
        $this->assertEquals('/dummies/{id}', $actual[4]->getRoute()->getPath());
        $this->assertEquals(['DELETE'], $actual[4]->getRoute()->getMethods());
    }

    private function getHydraDoc()
    {
        $hydraDocJson = <<<JSON
            {
                "hydra:supportedClass": [
                    {
                        "@id": "#Dummy",
                        "hydra:title": "Dummy",
                        "hydra:supportedOperation": [
                            {
                                "hydra:method": "GET",
                                "hydra:title": "Retrieves Dummy resource.",
                                "returns": "#Dummy"
                            },
                            {
                                "expects": "#Dummy",
                                "hydra:method": "PUT",
                                "hydra:title": "Replaces the Dummy resource.",
                                "returns": "#Dummy"
                            },
                            {
                                "hydra:method": "DELETE",
                                "hydra:title": "Deletes the Dummy resource.",
                                "returns": "owl:Nothing"
                            }
                        ]
                    },
                    {
                        "@id": "#Entrypoint",
                        "hydra:supportedProperty": [
                            {
                                "hydra:property": {
                                    "@id": "#Entrypoint\/dummy",
                                    "hydra:supportedOperation": [
                                        {
                                            "hydra:method": "GET",
                                            "hydra:title": "Retrieves the collection of Dummy resources.",
                                            "returns": "hydra:PagedCollection"
                                        },
                                        {
                                            "expects": "#Dummy",
                                            "hydra:method": "POST",
                                            "hydra:title": "Creates a Dummy resource.",
                                            "returns": "#Dummy"
                                        }
                                    ]
                                }
                            }
                        ]
                    }
                ]
            }
JSON;

        return json_decode($hydraDocJson, true);
    }
}
