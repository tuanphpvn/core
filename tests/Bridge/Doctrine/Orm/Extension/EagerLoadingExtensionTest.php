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

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\EagerLoadingExtension;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Core\Exception\PropertyNotFoundException;
use ApiPlatform\Core\Exception\ResourceClassNotFoundException;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Property\PropertyMetadata;
use ApiPlatform\Core\Metadata\Property\PropertyNameCollection;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Serializer\SerializerContextBuilderInterface;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\AbstractDummy;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\ConcreteDummy;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\EmbeddableDummy;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\RelatedDummy;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\UnknownDummy;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Prophecy\Argument;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactoryInterface;

/**
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class EagerLoadingExtensionTest extends \PHPUnit_Framework_TestCase
{
    public function testApplyToCollection()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $relatedNameCollection = new PropertyNameCollection(['id', 'name', 'notindatabase', 'notreadable', 'embeddedDummy']);

        $createPropertyNameCollectionFactory = function() use($relatedNameCollection) {

            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

            $propertyNameCollectionFactoryProphecy->create(RelatedDummy::class)->willReturn($relatedNameCollection)->shouldBeCalled();
            $relatedEmbedableCollection = new PropertyNameCollection(['name']);
            $propertyNameCollectionFactoryProphecy->create(EmbeddableDummy::class)->willReturn($relatedEmbedableCollection)->shouldBeCalled();

            return $propertyNameCollectionFactoryProphecy->reveal();
        };


        $createPropertyMetadataFactory = function() {

            $idPropertyMetadata = new PropertyMetadata();
            $idPropertyMetadata = $idPropertyMetadata->withIdentifier(true);
            $namePropertyMetadata = new PropertyMetadata();
            $namePropertyMetadata = $namePropertyMetadata->withReadable(true);
            $embeddedPropertyMetadata = new PropertyMetadata();
            $embeddedPropertyMetadata = $embeddedPropertyMetadata->withReadable(true);
            $notInDatabasePropertyMetadata = new PropertyMetadata();
            $notInDatabasePropertyMetadata = $notInDatabasePropertyMetadata->withReadable(true);
            $notReadablePropertyMetadata = new PropertyMetadata();
            $notReadablePropertyMetadata = $notReadablePropertyMetadata->withReadable(false);

            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy2', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'id', [])->willReturn($idPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'name', [])->willReturn($namePropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'embeddedDummy', [])->willReturn($embeddedPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'notindatabase', [])->willReturn($notInDatabasePropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'notreadable', [])->willReturn($notReadablePropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };



        $createQueryBuilder = function() use ($relatedNameCollection) {

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => true]], 'targetEntity' => RelatedDummy::class],
                'relatedDummy2' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => RelatedDummy::class],
            ];

            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);

            foreach ($relatedNameCollection as $property) {
                if ('id' !== $property) {
                    $relatedClassMetadataProphecy->hasField($property)->willReturn(!in_array($property, ['notindatabase', 'embeddedDummy'], true))->shouldBeCalled();
                }
            }
            $relatedClassMetadataProphecy->hasField('embeddedDummy.name')->willReturn(true)->shouldBeCalled();

            $relatedClassMetadataProphecy->embeddedClasses = ['embeddedDummy' => ['class' => EmbeddableDummy::class]];

            $relatedClassMetadataProphecy->associationMappings = [];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            $queryBuilderProphecy->leftJoin('o.relatedDummy', 'relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->innerJoin('o.relatedDummy2', 'relatedDummy2_a2')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy_a1.{id,name,embeddedDummy.name}')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy2_a2.{id,name,embeddedDummy.name}')->shouldBeCalled(1);

            return $queryBuilderProphecy->reveal();
        };


        $eagerExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            $_maxjoin = 30, $_force = false, $_requestStack = null, $_serializerContextBuilder = null, $_classMetaDataFactory = true);

        $eagerExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToItem()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $relatedNameCollection = new PropertyNameCollection(['id', 'name', 'embeddedDummy', 'notindatabase', 'notreadable', 'relation']);

        $createPropertyNameCollection = function() use ($relatedNameCollection) {

            $relatedEmbedableCollection = new PropertyNameCollection(['name']);
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            $propertyNameCollectionFactoryProphecy->create(RelatedDummy::class)->willReturn($relatedNameCollection)->shouldBeCalled();
            $propertyNameCollectionFactoryProphecy->create(EmbeddableDummy::class)->willReturn($relatedEmbedableCollection)->shouldBeCalled();
            $propertyNameCollectionFactoryProphecy->create(UnknownDummy::class)->willReturn(new PropertyNameCollection(['id']))->shouldBeCalled();

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {

            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy2', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy3', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy4', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy5', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'singleInheritanceRelation', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            $idPropertyMetadata = new PropertyMetadata();
            $idPropertyMetadata = $idPropertyMetadata->withIdentifier(true);
            $namePropertyMetadata = new PropertyMetadata();
            $namePropertyMetadata = $namePropertyMetadata->withReadable(true);
            $embeddedDummyPropertyMetadata = new PropertyMetadata();
            $embeddedDummyPropertyMetadata = $embeddedDummyPropertyMetadata->withReadable(true);
            $notInDatabasePropertyMetadata = new PropertyMetadata();
            $notInDatabasePropertyMetadata = $notInDatabasePropertyMetadata->withReadable(true);
            $notReadablePropertyMetadata = new PropertyMetadata();
            $notReadablePropertyMetadata = $notReadablePropertyMetadata->withReadable(false);

            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'id', [])->willReturn($idPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'name', [])->willReturn($namePropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'embeddedDummy', [])->willReturn($embeddedDummyPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'notindatabase', [])->willReturn($notInDatabasePropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'notreadable', [])->willReturn($notReadablePropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'relation', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(UnknownDummy::class, 'id', [])->willReturn($idPropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() use ($relatedNameCollection) {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => true]], 'targetEntity' => RelatedDummy::class],
                'relatedDummy2' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => UnknownDummy::class],
                'relatedDummy3' => ['fetch' => 3, 'joinTable' => ['joinColumns' => [['nullable' => false]]], 'targetEntity' => UnknownDummy::class],
                'relatedDummy4' => ['fetch' => 3, 'targetEntity' => UnknownDummy::class],
                'relatedDummy5' => ['fetch' => 2, 'targetEntity' => UnknownDummy::class],
                'singleInheritanceRelation' => ['fetch' => 3, 'targetEntity' => AbstractDummy::class],
            ];

            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);

            foreach ($relatedNameCollection as $property) {
                if ('id' !== $property) {
                    $relatedClassMetadataProphecy->hasField($property)->willReturn(!in_array($property, ['notindatabase', 'embeddedDummy'], true))->shouldBeCalled();
                }
            }
            $relatedClassMetadataProphecy->hasField('embeddedDummy.name')->willReturn(true)->shouldBeCalled();

            $relatedClassMetadataProphecy->associationMappings = [
                'relation' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => UnknownDummy::class],
            ];

            $relatedClassMetadataProphecy->embeddedClasses = ['embeddedDummy' => ['class' => EmbeddableDummy::class]];

            $singleInheritanceClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $singleInheritanceClassMetadataProphecy->subClasses = [ConcreteDummy::class];

            $unknownClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $unknownClassMetadataProphecy->associationMappings = [];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(AbstractDummy::class)->shouldBeCalled()->willReturn($singleInheritanceClassMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(UnknownDummy::class)->shouldBeCalled()->willReturn($unknownClassMetadataProphecy->reveal());

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);
            $queryBuilderProphecy->leftJoin('o.relatedDummy', 'relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->leftJoin('relatedDummy_a1.relation', 'relation_a2')->shouldBeCalled(1);
            $queryBuilderProphecy->innerJoin('o.relatedDummy2', 'relatedDummy2_a3')->shouldBeCalled(1);
            $queryBuilderProphecy->leftJoin('o.relatedDummy3', 'relatedDummy3_a4')->shouldBeCalled(1);
            $queryBuilderProphecy->leftJoin('o.relatedDummy4', 'relatedDummy4_a5')->shouldBeCalled(1);
            $queryBuilderProphecy->leftJoin('o.singleInheritanceRelation', 'singleInheritanceRelation_a6')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy_a1.{id,name,embeddedDummy.name}')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relation_a2.{id}')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy2_a3.{id}')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy3_a4.{id}')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy4_a5.{id}')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('singleInheritanceRelation_a6')->shouldBeCalled(1);

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new EagerLoadingExtension($createPropertyNameCollection(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoin */30, /** $forceEager */ false, /** $requestStack */ null, /** $serializerContext */ null, /** $classMetadataFactory */ true);

        $orderExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), Dummy::class, []);
    }

    public function testCreateItemWithOperationName()
    {
        $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
        $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactoryProphecy->create(Dummy::class, 'foo', ['item_operation_name' => 'item_operation'])->shouldBeCalled()->willReturn(new PropertyMetadata());

        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'foo' => ['fetch' => 1],
            ];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy->reveal();
        };

        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

        $orderExtensionTest = new EagerLoadingExtension($propertyNameCollectionFactoryProphecy->reveal(), $propertyMetadataFactoryProphecy->reveal(), $resourceMetadataFactoryProphecy->reveal(),
            /** $maxJoin */30, /** $forceEager */ false, /** $requestStack */null, /** $serializerContextBuilder */ null, /** $classMetaDataFactory */ true);

        $orderExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), /** $resourceClass */ Dummy::class, /** $identifiers */ [], /** $operationName */ 'item_operation');
    }

    public function testCreateCollectionWithOperationName()
    {
        $createQueryBuilderProphecy = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'foo' => ['fetch' => 1],
            ];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy;
        };

        $queryBuilderProphecy = $createQueryBuilderProphecy();

        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
        $propertyMetadataFactoryProphecy->create(Dummy::class, 'foo', ['collection_operation_name' => 'collection_operation'])->shouldBeCalled()->willReturn(new PropertyMetadata());

        $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
        $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

        $eagerExtensionTest = new EagerLoadingExtension($propertyNameCollectionFactoryProphecy->reveal(), $propertyMetadataFactoryProphecy->reveal(), $resourceMetadataFactoryProphecy->reveal(),
            /** $maxJoin */30, /** $forceEager */false, /** $requestStack */null, /** $serializerContextBuilder */null, /** $classMetaDataFactory */true);

        $eagerExtensionTest->applyToCollection($queryBuilderProphecy->reveal(), new QueryNameGenerator(), Dummy::class, /** $operationName */'collection_operation');
    }

    public function testDenormalizeItemWithCorrectResourceClass()
    {
        $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
        //Dummy is the correct class for the denormalization context serialization groups, and we're fetching RelatedDummy
        $resourceMetadataFactoryProphecy->create(RelatedDummy::class)->willReturn(new ResourceMetadata())->shouldBeCalled();
        $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata())->shouldBeCalled();

        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy->reveal();
        };

        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);

        $eagerExtensionTest = new EagerLoadingExtension($propertyNameCollectionFactoryProphecy->reveal(), $propertyMetadataFactoryProphecy->reveal(), $resourceMetadataFactoryProphecy->reveal(),
            /** $maxJoin */30, /** $forceEager */false, /** $requestStack */null, /** $serializerContextBuilder */null, /** $classMetaDataFactory */true);

        $eagerExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), RelatedDummy::class, /** $identifiers[] */['id' => 1], /** $operationName */'item_operation', /** $context */['resource_class' => Dummy::class]);
    }

    public function testDenormalizeItemWithExistingGroups()
    {
        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy->reveal();
        };

        $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

        $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);

        $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
        //groups exist from the context, we don't need to compute them again
        $resourceMetadataFactoryProphecy->create(RelatedDummy::class)->willReturn(new ResourceMetadata())->shouldBeCalled();
        $resourceMetadataFactoryProphecy->create(Dummy::class)->shouldNotBeCalled();

        $eagerExtensionTest = new EagerLoadingExtension($propertyNameCollectionFactoryProphecy->reveal(), $propertyMetadataFactoryProphecy->reveal(), $resourceMetadataFactoryProphecy->reveal(),
            /** $maxJoins */30, /** $forceEager */false, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true);

        $eagerExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), RelatedDummy::class, ['id' => 1], 'item_operation', ['groups' => 'some_groups']);
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\RuntimeException
     * @expectedExceptionMessage The total number of joined relations has exceeded the specified maximum. Raise the limit if necessary, or use the "max_depth" option of the Symfony serializer.
     */
    public function testMaxJoinsReached()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $relatedNameCollection = new PropertyNameCollection(['dummy']);

        $createPropertyNameCollectionFactory = function() use ($relatedNameCollection) {

            $dummyNameCollection = new PropertyNameCollection(['relatedDummy']);
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            $propertyNameCollectionFactoryProphecy->create(RelatedDummy::class)->willReturn($relatedNameCollection)->shouldBeCalled();
            $propertyNameCollectionFactoryProphecy->create(Dummy::class)->willReturn($dummyNameCollection)->shouldBeCalled();

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            $relatedPropertyMetadata = new PropertyMetadata();
            $relatedPropertyMetadata = $relatedPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'dummy', [])->willReturn($relatedPropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => RelatedDummy::class],
            ];

            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $relatedClassMetadataProphecy->associationMappings = [
                'dummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => Dummy::class],
            ];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            $queryBuilderProphecy->innerJoin(Argument::type('string'), Argument::type('string'))->shouldBeCalled();
            $queryBuilderProphecy->addSelect(Argument::type('string'))->shouldBeCalled();

            return $queryBuilderProphecy->reveal();
        };

        $eagerExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */false, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true);
        $eagerExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testMaxDepth()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadata = new ResourceMetadata();
            $resourceMetadata = $resourceMetadata->withAttributes(['normalization_context' => ['enable_max_depth' => 'true']]);

            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn($resourceMetadata);

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            $relatedPropertyMetadata = new PropertyMetadata();
            $relatedPropertyMetadata = $relatedPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'dummy', [])->willReturn($relatedPropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };

        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

            $relatedNameCollection = new PropertyNameCollection(['dummy']);
            $dummyNameCollection = new PropertyNameCollection(['relatedDummy']);
            $propertyNameCollectionFactoryProphecy->create(RelatedDummy::class)->willReturn($relatedNameCollection)->shouldBeCalled();
            $propertyNameCollectionFactoryProphecy->create(Dummy::class)->willReturn($dummyNameCollection)->shouldBeCalled();

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createClassMetadataFactory = function() {
            $classMetadataFactoryProphecy = $this->prophesize(ClassMetadataFactoryInterface::class);

            $relatedClassMetadataInterfaceProphecy = $this->prophesize(ClassMetadataInterface::class);
            $relatedAttributeMetadata = new AttributeMetadata('relatedDummy');
            $relatedAttributeMetadata->setMaxDepth(4);
            $relatedClassMetadataInterfaceProphecy->getAttributesMetadata()->willReturn(['dummy' => $relatedAttributeMetadata]);
            $classMetadataFactoryProphecy->getMetadataFor(RelatedDummy::class)->willReturn($relatedClassMetadataInterfaceProphecy->reveal());

            $dummyClassMetadataInterfaceProphecy = $this->prophesize(ClassMetadataInterface::class);
            $dummyAttributeMetadata = new AttributeMetadata('dummy');
            $dummyAttributeMetadata->setMaxDepth(2);
            $dummyClassMetadataInterfaceProphecy->getAttributesMetadata()->willReturn(['relatedDummy' => $dummyAttributeMetadata]);
            $classMetadataFactoryProphecy->getMetadataFor(Dummy::class)->willReturn($dummyClassMetadataInterfaceProphecy->reveal());

            return $classMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => RelatedDummy::class],
            ];

            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $relatedClassMetadataProphecy->associationMappings = [
                'dummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => Dummy::class],
            ];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            $queryBuilderProphecy->innerJoin(Argument::type('string'), Argument::type('string'))->shouldBeCalledTimes(2);
            $queryBuilderProphecy->addSelect(Argument::type('string'))->shouldBeCalled();

            return $queryBuilderProphecy->reveal();
        };

        $eagerExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */false, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true, $createClassMetadataFactory());

        $eagerExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testForceEager()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadata = new ResourceMetadata();
            $resourceMetadata = $resourceMetadata->withAttributes(['normalization_context' => ['groups' => 'foobar']]);
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn($resourceMetadata);

            return $resourceMetadataFactoryProphecy->reveal();
        };


        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            $propertyNameCollectionFactoryProphecy->create(UnknownDummy::class)->willReturn(new PropertyNameCollection(['id']))->shouldBeCalled();

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);

            $idPropertyMetadata = new PropertyMetadata();
            $idPropertyMetadata = $idPropertyMetadata->withIdentifier(true);

            $propertyMetadataFactoryProphecy->create(UnknownDummy::class, 'id', ['serializer_groups' => 'foobar'])->willReturn($idPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relation', ['serializer_groups' => 'foobar'])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };


        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relation' => ['fetch' => 2, 'targetEntity' => UnknownDummy::class, 'joinColumns' => [['nullable' => false]]],
            ];

            $unknownClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $unknownClassMetadataProphecy->associationMappings = [];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(UnknownDummy::class)->shouldBeCalled()->willReturn($unknownClassMetadataProphecy->reveal());

            $queryBuilderProphecy->innerJoin('o.relation', 'relation_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relation_a1.{id}')->shouldBeCalled(1);

            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */true, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true);

        $orderExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), Dummy::class, []);
    }

    public function testResourceClassNotFoundException()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };
        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relation', [])->willThrow(new ResourceClassNotFoundException());

            return $propertyMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relation' => ['fetch' => 2, 'targetEntity' => UnknownDummy::class, 'joinColumns' => [['nullable' => false]]],
            ];
            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */true, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true);

        $orderExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), Dummy::class, []);
    }

    public function testPropertyNotFoundException()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relation', [])->willThrow(new PropertyNotFoundException());

            return $propertyMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relation' => ['fetch' => 2, 'targetEntity' => UnknownDummy::class, 'joinColumns' => [['nullable' => false]]],
            ];
            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */true, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true);

        $orderExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), Dummy::class, []);
    }

    public function testResourceClassNotFoundExceptionPropertyNameCollection()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };


        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            $propertyNameCollectionFactoryProphecy->create(UnknownDummy::class)->willThrow(new ResourceClassNotFoundException());

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relation', [])->willReturn($relationPropertyMetadata);

            return $propertyMetadataFactoryProphecy->reveal();
        };


        $createQueryBuilder = function() {
            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relation' => ['fetch' => 2, 'targetEntity' => UnknownDummy::class, 'joinColumns' => [['nullable' => false]]],
            ];
            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(UnknownDummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);
            $queryBuilderProphecy->innerJoin('o.relation', 'relation_a1')->shouldBeCalled(1);

            return $queryBuilderProphecy->reveal();
        };

        $orderExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */true, /** $requestStack */null, /** $serializerContextBuilder */null, /** $fetchPartial */true);

        $orderExtensionTest->applyToItem($createQueryBuilder(), new QueryNameGenerator(), Dummy::class, []);
    }

    public function testApplyToCollectionWithSerializerContextBuilder()
    {
        $relatedNameCollection = new PropertyNameCollection(['id', 'name']);

        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createPropertyNameCollectionFactory = function() use ($relatedNameCollection) {

            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);
            $propertyNameCollectionFactoryProphecy->create(RelatedDummy::class)->willReturn($relatedNameCollection)->shouldBeCalled();

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', ['serializer_groups' => ['foo']])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            $idPropertyMetadata = new PropertyMetadata();
            $idPropertyMetadata = $idPropertyMetadata->withIdentifier(true);
            $namePropertyMetadata = new PropertyMetadata();
            $namePropertyMetadata = $namePropertyMetadata->withReadable(true);

            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'id', ['serializer_groups' => ['foo']])->willReturn($idPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(RelatedDummy::class, 'name', ['serializer_groups' => ['foo']])->willReturn($namePropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };


        $request = Request::create('/api/dummies', 'GET', []);

        $createRequestStack = function() use ($request) {
            $requestStack = new RequestStack();
            $requestStack->push($request);

            return $requestStack;
        };

        $createSerializerContextBuilder = function() use ($request) {
            $serializerContextBuilderProphecy = $this->prophesize(SerializerContextBuilderInterface::class);
            $serializerContextBuilderProphecy->createFromRequest($request, true)->shouldBeCalled()->willReturn(['groups' => ['foo']]);

            return $serializerContextBuilderProphecy->reveal();
        };


        $createQueryBuilder = function() use ($relatedNameCollection, $request) {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => true]], 'targetEntity' => RelatedDummy::class],
            ];

            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);

            foreach ($relatedNameCollection as $property) {
                if ('id' !== $property) {
                    $relatedClassMetadataProphecy->hasField($property)->willReturn(true)->shouldBeCalled();
                }
            }

            $relatedClassMetadataProphecy->associationMappings = [];

            $emProphecy = $this->prophesize(EntityManager::class);
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());

            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            $queryBuilderProphecy->leftJoin('o.relatedDummy', 'relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('partial relatedDummy_a1.{id,name}')->shouldBeCalled(1);



            return $queryBuilderProphecy->reveal();
        };


        $eagerExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(),
            /** $maxJoins */30, /** $forceEager */false, $createRequestStack(), $createSerializerContextBuilder(), /** $fetchPartial */true);

        $eagerExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionNoPartial()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };


        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {
            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(true);

            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy2', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };

        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => true]], 'targetEntity' => RelatedDummy::class],
                'relatedDummy2' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => RelatedDummy::class],
            ];

            $emProphecy = $this->prophesize(EntityManager::class);
            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $relatedClassMetadataProphecy->associationMappings = [];
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());

            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            $queryBuilderProphecy->leftJoin('o.relatedDummy', 'relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->innerJoin('o.relatedDummy2', 'relatedDummy2_a2')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('relatedDummy2_a2')->shouldBeCalled(1);

            return $queryBuilderProphecy->reveal();
        };

        $eagerExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(), /** $maxJoins */30);

        $eagerExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }

    public function testApplyToCollectionWithANonRedableButFetchEagerProperty()
    {
        $createResourceMetadataFactory = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create(Dummy::class)->willReturn(new ResourceMetadata());

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createPropertyNameCollectionFactory = function() {
            $propertyNameCollectionFactoryProphecy = $this->prophesize(PropertyNameCollectionFactoryInterface::class);

            return $propertyNameCollectionFactoryProphecy->reveal();
        };

        $createPropertyMetadataFactory = function() {

            $propertyMetadataFactoryProphecy = $this->prophesize(PropertyMetadataFactoryInterface::class);
            $relationPropertyMetadata = new PropertyMetadata();
            $relationPropertyMetadata = $relationPropertyMetadata->withAttributes(['fetchEager' => true]);
            $relationPropertyMetadata = $relationPropertyMetadata->withReadableLink(false);
            $relationPropertyMetadata = $relationPropertyMetadata->withReadable(false);

            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();
            $propertyMetadataFactoryProphecy->create(Dummy::class, 'relatedDummy2', [])->willReturn($relationPropertyMetadata)->shouldBeCalled();

            return $propertyMetadataFactoryProphecy->reveal();
        };


        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);

            $classMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $classMetadataProphecy->associationMappings = [
                'relatedDummy' => ['fetch' => 3, 'joinColumns' => [['nullable' => true]], 'targetEntity' => RelatedDummy::class],
                'relatedDummy2' => ['fetch' => 3, 'joinColumns' => [['nullable' => false]], 'targetEntity' => RelatedDummy::class],
            ];

            $emProphecy = $this->prophesize(EntityManager::class);
            $relatedClassMetadataProphecy = $this->prophesize(ClassMetadata::class);
            $relatedClassMetadataProphecy->associationMappings = [];
            $emProphecy->getClassMetadata(Dummy::class)->shouldBeCalled()->willReturn($classMetadataProphecy->reveal());
            $emProphecy->getClassMetadata(RelatedDummy::class)->shouldBeCalled()->willReturn($relatedClassMetadataProphecy->reveal());

            $queryBuilderProphecy->getRootAliases()->willReturn(['o']);
            $queryBuilderProphecy->getEntityManager()->willReturn($emProphecy);

            $queryBuilderProphecy->leftJoin('o.relatedDummy', 'relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->innerJoin('o.relatedDummy2', 'relatedDummy2_a2')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('relatedDummy_a1')->shouldBeCalled(1);
            $queryBuilderProphecy->addSelect('relatedDummy2_a2')->shouldBeCalled(1);

            return $queryBuilderProphecy->reveal();
        };

        $eagerExtensionTest = new EagerLoadingExtension($createPropertyNameCollectionFactory(), $createPropertyMetadataFactory(), $createResourceMetadataFactory(), /** $maxJoins */30);

        $eagerExtensionTest->applyToCollection($createQueryBuilder(), new QueryNameGenerator(), Dummy::class);
    }
}
