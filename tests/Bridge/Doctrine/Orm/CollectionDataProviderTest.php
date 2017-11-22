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

namespace ApiPlatform\Core\Tests\Bridge\Doctrine\Orm;

use ApiPlatform\Core\Bridge\Doctrine\Orm\CollectionDataProvider;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\Dummy;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Prophecy\Argument;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class CollectionDataProviderTest extends \PHPUnit_Framework_TestCase
{
    public function testGetCollection()
    {
        $createQueryBuilder = function() {
            $queryProphecy = $this->prophesize(AbstractQuery::class);
            $queryProphecy->getResult()->willReturn([])->shouldBeCalled();

            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            $queryBuilderProphecy->getQuery()->willReturn($queryProphecy->reveal())->shouldBeCalled();

            return $queryBuilderProphecy->reveal();
        };
        $qb = $createQueryBuilder();

        $createExtensions = function() use ($qb) {
            $extensionProphecy = $this->prophesize(QueryCollectionExtensionInterface::class);
            $extensionProphecy->applyToCollection($qb, Argument::type(QueryNameGeneratorInterface::class), Dummy::class, 'foo')->shouldBeCalled();

            return $extensionProphecy->reveal();
        };

        $createManagerRegistry = function() use($qb) {
            $repositoryProphecy = $this->prophesize(EntityRepository::class);
            $repositoryProphecy->createQueryBuilder('o')->willReturn($qb)->shouldBeCalled();

            $managerProphecy = $this->prophesize(ObjectManager::class);
            $managerProphecy->getRepository(Dummy::class)->willReturn($repositoryProphecy->reveal())->shouldBeCalled();

            $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
            $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($managerProphecy->reveal())->shouldBeCalled();

            return $managerRegistryProphecy->reveal();
        };

        $dataProvider = new CollectionDataProvider($createManagerRegistry(), [$createExtensions()]);
        $this->assertEquals([], $dataProvider->getCollection(Dummy::class, /** $operationName */'foo'));
    }

    public function testWithQueryResultCollectionExtension()
    {
        $createQueryBuilder = function() {
            $queryBuilderProphecy = $this->prophesize(QueryBuilder::class);
            return $queryBuilderProphecy->reveal();
        };

        $qb = $createQueryBuilder();

        $createManagerRegistry = function() use ($qb) {
            $repositoryProphecy = $this->prophesize(EntityRepository::class);
            $repositoryProphecy->createQueryBuilder('o')->willReturn($qb)->shouldBeCalled();

            $managerProphecy = $this->prophesize(ObjectManager::class);
            $managerProphecy->getRepository(Dummy::class)->willReturn($repositoryProphecy->reveal())->shouldBeCalled();

            $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
            $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($managerProphecy->reveal())->shouldBeCalled();

            return $managerRegistryProphecy->reveal();
        };

        $createExtensions = function() use ($qb) {
            $extensionProphecy = $this->prophesize(QueryResultCollectionExtensionInterface::class);
            $extensionProphecy->applyToCollection($qb, Argument::type(QueryNameGeneratorInterface::class), Dummy::class, 'foo')->shouldBeCalled();
            $extensionProphecy->supportsResult(Dummy::class, 'foo')->willReturn(true)->shouldBeCalled();
            $extensionProphecy->getResult($qb)->willReturn([])->shouldBeCalled();

            return $extensionProphecy->reveal();
        };

        $dataProvider = new CollectionDataProvider($createManagerRegistry(), [$createExtensions()]);
        $this->assertEquals([], $dataProvider->getCollection(Dummy::class, /** $operationName */'foo'));
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\RuntimeException
     * @expectedExceptionMessage The repository class must have a "createQueryBuilder" method.
     */
    public function testCannotCreateQueryBuilder()
    {
        $createManagerRegistry = function() {
            $repositoryProphecy = $this->prophesize(ObjectRepository::class);

            $managerProphecy = $this->prophesize(ObjectManager::class);
            $managerProphecy->getRepository(Dummy::class)->willReturn($repositoryProphecy->reveal())->shouldBeCalled();

            $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
            $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn($managerProphecy->reveal())->shouldBeCalled();

            return $managerRegistryProphecy->reveal();
        };

        $dataProvider = new CollectionDataProvider($createManagerRegistry());
        $this->assertEquals([], $dataProvider->getCollection(Dummy::class, /** $operationName */'foo'));
    }

    /**
     * @expectedException \ApiPlatform\Core\Exception\ResourceClassNotSupportedException
     */
    public function testThrowResourceClassNotSupportedException()
    {
        $createManagerRegistry = function() {
            $managerRegistryProphecy = $this->prophesize(ManagerRegistry::class);
            $managerRegistryProphecy->getManagerForClass(Dummy::class)->willReturn(null)->shouldBeCalled();

            return $managerRegistryProphecy->reveal();
        };

        $createExtensions = function() {
            $extensionProphecy = $this->prophesize(QueryResultCollectionExtensionInterface::class);

            return $extensionProphecy->reveal();
        };

        $dataProvider = new CollectionDataProvider($createManagerRegistry(), [$createExtensions()]);
        $dataProvider->getCollection(Dummy::class, 'foo');
    }
}
