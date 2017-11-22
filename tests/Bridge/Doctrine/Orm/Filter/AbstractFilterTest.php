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

namespace ApiPlatform\Core\Tests\Bridge\Doctrine\Orm\Filter;

use ApiPlatform\Core\Tests\Fixtures\TestBundle\Doctrine\Orm\Filter\DummyFilter;
use Doctrine\Common\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Regression test case on issue 1154.
 *
 * @author Antoine Bluchet <soyuka@gmail.com>
 */
class AbstractFilterTest extends \PHPUnit_Framework_TestCase
{
    public function testSplitPropertiesWithoutResourceClass()
    {
        /** @var ManagerRegistry $managerRegistry */
        $managerRegistry = $this->prophesize(ManagerRegistry::class)->reveal();

        /** @var RequestStack $requestStack */
        $requestStack = $this->prophesize(RequestStack::class)->reveal();

        $filter = new DummyFilter($managerRegistry, $requestStack);

        $this->assertEquals([
            'associations' => ['foo'],
            'field' => 'bar',
        ], $filter->doSplitPropertiesWithoutResourceClass('foo.bar'));
    }
}
