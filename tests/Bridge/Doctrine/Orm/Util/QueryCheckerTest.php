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

namespace ApiPlatform\Core\Tests\Bridge\Doctrine\Orm\Util;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryChecker;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\RelatedDummy;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\Query\Expr\OrderBy;
use Doctrine\ORM\QueryBuilder;

class QueryCheckerTest extends \PHPUnit_Framework_TestCase
{
    public function testHasHavingClauseWithHavingClause()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getDQLPart('having')->willReturn(['having' => 'toto']);

            return $queryBuilder->reveal();
        };

        $this->assertTrue(QueryChecker::hasHavingClause($createQueryBuilder()));
    }

    public function testHasHavingClauseWithEmptyHavingClause()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getDQLPart('having')->willReturn([]);

            return $queryBuilder->reveal();
        };

        $this->assertFalse(QueryChecker::hasHavingClause($createQueryBuilder()));
    }

    public function testHasMaxResult()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getMaxResults()->willReturn(10);

            return $queryBuilder->reveal();
        };

        $this->assertTrue(QueryChecker::hasMaxResults($createQueryBuilder()));
    }

    public function testHasMaxResultWithNoMaxResult()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getMaxResults()->willReturn(null);

            return $queryBuilder->reveal();
        };

        $this->assertFalse(QueryChecker::hasMaxResults($createQueryBuilder()));
    }

    public function testHasRootEntityWithCompositeIdentifier()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = new ClassMetadata('Dummy');
            $classMetadata->containsForeignIdentifier = true;
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata);
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $this->assertTrue(QueryChecker::hasRootEntityWithCompositeIdentifier($createQueryBuilder(), $createManagerRegistry()));
    }

    public function testHasRootEntityWithNoCompositeIdentifier()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);

            return $queryBuilder->reveal();
        };

        $createMangerRegistry = function() {
            $classMetadata = new ClassMetadata('Dummy');
            $classMetadata->containsForeignIdentifier = false;
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata);
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $this->assertFalse(QueryChecker::hasRootEntityWithCompositeIdentifier($createQueryBuilder(), $createMangerRegistry()));
    }

    public function testHasRootEntityWithForeignKeyIdentifier()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = new ClassMetadata('Dummy');
            $classMetadata->setIdentifier(['id', 'name']);
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata);
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $this->assertTrue(QueryChecker::hasRootEntityWithForeignKeyIdentifier($createQueryBuilder(), $createManagerRegistry()));
    }

    public function testHasRootEntityWithNoForeignKeyIdentifier()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = new ClassMetadata('Dummy');
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata);
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $this->assertFalse(QueryChecker::hasRootEntityWithForeignKeyIdentifier($createQueryBuilder(), $createManagerRegistry()));
    }

    public function testHasOrderByOnToManyJoinWithoutJoin()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);
            $queryBuilder->getDQLPart('join')->willReturn([]);
            $queryBuilder->getDQLPart('orderBy')->willReturn(['name' => new OrderBy('name', 'asc')]);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadata::class);
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata->reveal());
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };


        $this->assertFalse(QueryChecker::hasOrderByOnToManyJoin($createQueryBuilder(), $createManagerRegistry()));
    }

    public function testHasOrderByOnToManyJoinWithoutOrderBy()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);
            $queryBuilder->getDQLPart('join')->willReturn(['a_1' => new Join('INNER_JOIN', 'relatedDummy', 'a_1', null, 'a_1.name = r.name')]);
            $queryBuilder->getDQLPart('orderBy')->willReturn([]);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadata::class);
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata->reveal());
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $this->assertFalse(QueryChecker::hasOrderByOnToManyJoin($createQueryBuilder(), $createManagerRegistry()));
    }

    public function testHasOrderByOnToManyJoinWithoutJoinAndWithoutOrderBy()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);
            $queryBuilder->getDQLPart('join')->willReturn([]);
            $queryBuilder->getDQLPart('orderBy')->willReturn([]);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadata::class);
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata->reveal());
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };

        $this->assertFalse(QueryChecker::hasOrderByOnToManyJoin($createQueryBuilder(), $createManagerRegistry()));
    }

    public function testHasOrderByOnToManyJoinWithClassLeftJoin()
    {
        $createQueryBuilder = function() {
            $queryBuilder = $this->prophesize(QueryBuilder::class);
            $queryBuilder->getRootEntities()->willReturn(['Dummy']);
            $queryBuilder->getRootAliases()->willReturn(['d']);
            $queryBuilder->getDQLPart('join')->willReturn(['a_1' => [new Join('LEFT_JOIN', RelatedDummy::class, 'a_1', null, 'a_1.name = d.name')]]);
            $queryBuilder->getDQLPart('orderBy')->willReturn(['a_1.name' => new OrderBy('a_1.name', 'asc')]);

            return $queryBuilder->reveal();
        };

        $createManagerRegistry = function() {
            $classMetadata = $this->prophesize(ClassMetadata::class);
            $classMetadata->getAssociationsByTargetClass(RelatedDummy::class)->willReturn(['relatedDummy' => ['targetEntity' => RelatedDummy::class]]);
            $relatedClassMetadata = $this->prophesize(ClassMetadata::class);
            $relatedClassMetadata->isCollectionValuedAssociation('relatedDummy')->willReturn(true);
            $objectManager = $this->prophesize(ObjectManager::class);
            $objectManager->getClassMetadata('Dummy')->willReturn($classMetadata->reveal());
            $objectManager->getClassMetadata(RelatedDummy::class)->willReturn($relatedClassMetadata->reveal());
            $managerRegistry = $this->prophesize(ManagerRegistry::class);
            $managerRegistry->getManagerForClass('Dummy')->willReturn($objectManager->reveal());
            $managerRegistry->getManagerForClass(RelatedDummy::class)->willReturn($objectManager->reveal());

            return $managerRegistry->reveal();
        };


        $this->assertTrue(QueryChecker::hasOrderByOnToManyJoin($createQueryBuilder(), $createManagerRegistry()));
    }
}
