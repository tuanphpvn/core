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

namespace ApiPlatform\Core\Tests\Bridge\FosUser;

use ApiPlatform\Core\Bridge\FosUser\EventListener;
use ApiPlatform\Core\Tests\Fixtures\TestBundle\Entity\User;
use FOS\UserBundle\Model\UserInterface;
use FOS\UserBundle\Model\UserManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class EventListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testDelete()
    {
        $user = $this->prophesize(UserInterface::class);

        $createUserManager = function() use ($user) {

            $manager = $this->prophesize(UserManagerInterface::class);
            $manager->deleteUser($user)->shouldBeCalled();
            return $manager->reveal();
        };

        $createGetResponseForControllerResultEvent = function() use ($user) {

            $request = new Request(/** $query */[], /** $request */[], /** $attributes */['_api_resource_class' => User::class, '_api_item_operation_name' => 'delete']);
            $request->setMethod(Request::METHOD_DELETE);
            $event = $this->prophesize(GetResponseForControllerResultEvent::class);
            $event->getControllerResult()->willReturn($user)->shouldBeCalled();
            $event->getRequest()->willReturn($request)->shouldBeCalled();
            $event->setControllerResult(null)->shouldBeCalled();

            return $event->reveal();
        };

        $listener = new EventListener($createUserManager());
        $listener->onKernelView($createGetResponseForControllerResultEvent());
    }

    public function testUpdate()
    {
        $user = $this->prophesize(UserInterface::class);

        $createUserManager = function() use ($user) {
            $manager = $this->prophesize(UserManagerInterface::class);
            $manager->updateUser($user)->shouldBeCalled();

            return $manager->reveal();
        };

        $createResponseForControllerResultEvent = function() use ($user) {

            $request = new Request(/** $query */[], /** $request */[], /** $attributes */['_api_resource_class' => User::class, '_api_item_operation_name' => 'put']);
            $request->setMethod(Request::METHOD_PUT);

            $event = $this->prophesize(GetResponseForControllerResultEvent::class);
            $event->getControllerResult()->willReturn($user)->shouldBeCalled();
            $event->getRequest()->willReturn($request)->shouldBeCalled();
            $event->setControllerResult()->shouldNotBeCalled();

            return $event->reveal();
        };

        $listener = new EventListener($createUserManager());
        $listener->onKernelView($createResponseForControllerResultEvent());
    }

    public function testNotApiRequest()
    {
        $createUserManager = function() {
            $manager = $this->prophesize(UserManagerInterface::class);
            $manager->deleteUser()->shouldNotBeCalled();
            $manager->updateUser()->shouldNotBeCalled();

            return $manager->reveal();
        };

        $createGetResponseForControllerResultEvent = function() {
            $request = new Request();
            $event = $this->prophesize(GetResponseForControllerResultEvent::class);
            $event->getRequest()->willReturn($request)->shouldBeCalled();

            return $event->reveal();
        };

        $listener = new EventListener($createUserManager());
        $listener->onKernelView($createGetResponseForControllerResultEvent());
    }

    public function testNotUser()
    {
        $createUserManager = function() {
            $manager = $this->prophesize(UserManagerInterface::class);
            $manager->deleteUser()->shouldNotBeCalled();
            $manager->updateUser()->shouldNotBeCalled();

            return $manager->reveal();
        };

        $createGetResponseForControllerResultEvent = function() {
            $request = new Request(/** $query */[], /** $request */[], /** $attributes */['_api_resource_class' => User::class, '_api_item_operation_name' => 'put']);
            $request->setMethod(Request::METHOD_PUT);

            $event = $this->prophesize(GetResponseForControllerResultEvent::class);
            $event->getRequest()->willReturn($request)->shouldBeCalled();
            $event->getControllerResult()->willReturn(new \stdClass());

            return $event->reveal();
        };

        $listener = new EventListener($createUserManager());
        $listener->onKernelView($createGetResponseForControllerResultEvent());
    }

    public function testSafeMethod()
    {
        $createUserManager = function() {
            $manager = $this->prophesize(UserManagerInterface::class);
            $manager->deleteUser()->shouldNotBeCalled();
            $manager->updateUser()->shouldNotBeCalled();

            return $manager->reveal();
        };

        $createGetResponseForControllerEvent = function() {
            $request = new Request(/** $query */[], /** $request */[], /** $attributes */['_api_resource_class' => User::class, '_api_item_operation_name' => 'put']);
            $event = $this->prophesize(GetResponseForControllerResultEvent::class);
            $event->getRequest()->willReturn($request)->shouldBeCalled();
            $event->getControllerResult()->willReturn(new User());

            return $event->reveal();
        };

        $listener = new EventListener($createUserManager());
        $listener->onKernelView($createGetResponseForControllerEvent());
    }
}
