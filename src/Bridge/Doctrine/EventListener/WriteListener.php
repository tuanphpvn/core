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

namespace ApiPlatform\Core\Bridge\Doctrine\EventListener;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

/**
 * Bridges Doctrine and the API system.
 *
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
final class WriteListener
{
    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * Persists, updates or delete data return by the controller if applicable.
     *
     * @param GetResponseForControllerResultEvent $event
     */
    public function onKernelView(GetResponseForControllerResultEvent $event)
    {
        $isNotProcess = function() use ($event) {

            $request = $event->getRequest();
            if ($request->isMethodSafe(false)) {
                return true;
            }

            $resourceClass = $request->attributes->get('_api_resource_class');
            if (null === $resourceClass) {
                return true;
            }

            $objectManager = $this->managerRegistry->getManagerForClass($resourceClass);
            if (null === $objectManager) {
                return true;
            }

            $controllerResult = $event->getControllerResult();

            if(!is_object($controllerResult)) {
                return true;
            }
        };

        if($isNotProcess())
        {
            return;
        }

        $request = $event->getRequest();
        $controllerResult = $event->getControllerResult();
        $resourceClass = $request->attributes->get('_api_resource_class');
        $objectManager = $this->managerRegistry->getManagerForClass($resourceClass);

        switch ($request->getMethod()) {
            case Request::METHOD_POST:
                $objectManager->persist($controllerResult);
                break;
            case Request::METHOD_DELETE:
                $objectManager->remove($controllerResult);
                $event->setControllerResult(null);
                break;
        }

        $objectManager->flush();
    }
}
