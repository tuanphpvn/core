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

namespace ApiPlatform\Core\Tests\Documentation\Action;

use ApiPlatform\Core\Documentation\Action\DocumentationAction;
use ApiPlatform\Core\Documentation\Documentation;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceNameCollectionFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceNameCollection;

/**
 * @author Amrouche Hamza <hamza.simperfit@gmail.com>
 */
class DocumentationActionTest extends \PHPUnit_Framework_TestCase
{
    public function testDocumentationAction()
    {
        $createResourceNameCollectionFactory = function() {
            $resourceNameCollectionFactoryProphecy = $this->prophesize(ResourceNameCollectionFactoryInterface::class);
            $resourceNameCollectionFactoryProphecy->create()->willReturn(new ResourceNameCollection(['dummies']));

            return $resourceNameCollectionFactoryProphecy->reveal();
        };

        $documentation = new DocumentationAction($createResourceNameCollectionFactory(), 'My happy hippie api', 'lots of chocolate', '1.0.0', ['formats' => ['jsonld' => 'application/ld+json']]);
        $this->assertEquals(new Documentation(new ResourceNameCollection(['dummies']), /** $title */'My happy hippie api', /** $description */'lots of chocolate', /** $versions */'1.0.0', /** $formats */['formats' => ['jsonld' => 'application/ld+json']]), $documentation());
    }
}
