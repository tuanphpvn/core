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

namespace ApiPlatform\Core\Tests\Bridge\Symfony\Validator\EventListener;

use ApiPlatform\Core\Bridge\Symfony\Validator\EventListener\ValidateListener;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Tests\Fixtures\DummyEntity;
use Prophecy\Argument;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @author Samuel ROZE <samuel.roze@gmail.com>
 */
class ValidateListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testNotAnApiPlatformRequest()
    {
        $createValidator = function() {
            $validatorProphecy = $this->prophesize(ValidatorInterface::class);
            $validatorProphecy->validate()->shouldNotBeCalled();
            return $validatorProphecy->reveal();
        };

        $createResourceMetadata = function() {
            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            $resourceMetadataFactoryProphecy->create()->shouldNotBeCalled();
            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createPostRequest = function() {
            $request = new Request();
            $request->setMethod('POST');

            return $request;
        };
        $postRequest = $createPostRequest();

        $createEvent = function() use ($postRequest) {
            $event = $this->prophesize(GetResponseForControllerResultEvent::class);
            $event->getRequest()->willReturn($postRequest)->shouldBeCalled();

            return $event->reveal();
        };

        $listener = new ValidateListener($createValidator(), $createResourceMetadata());
        $listener->onKernelView($createEvent());
    }

    public function testValidatorIsCalled()
    {
        $expectedValidationGroups = ['a', 'b', 'c'];
        $data = new DummyEntity();

        $createValidator = function() use ($expectedValidationGroups, $data) {
            $validatorProphecy = $this->prophesize(ValidatorInterface::class);
            $constraintViolationList = $this->prophesize(ConstraintViolationListInterface::class);
            $validatorProphecy->validate($data, null, $expectedValidationGroups)->willReturn($constraintViolationList)->shouldBeCalled();
            return $validatorProphecy->reveal();
        };

        $createContainer = function() {
            $containerProphecy = $this->prophesize(ContainerInterface::class);
            $containerProphecy->has(Argument::any())->shouldNotBeCalled();

            return $containerProphecy->reveal();
        };

        list($resourceMetadataFactory, $getResponseForControllerEvent) = $this->createEventObject($expectedValidationGroups, $data);

        $validationViewListener = new ValidateListener($createValidator(), $resourceMetadataFactory, $createContainer());
        $validationViewListener->onKernelView($getResponseForControllerEvent);
    }

    public function testGetGroupsFromCallable()
    {
        $data = new DummyEntity();
        $expectedValidationGroups = ['a', 'b', 'c'];

        $validatorProphecy = $this->prophesize(ValidatorInterface::class);
        $constraintViolationList = $this->prophesize(ConstraintViolationListInterface::class);
        $validatorProphecy->validate($data, null, $expectedValidationGroups)->willReturn($constraintViolationList)->shouldBeCalled();
        $validator = $validatorProphecy->reveal();

        $closure = function ($data) use ($expectedValidationGroups): array {
            return $data instanceof DummyEntity ? $expectedValidationGroups : [];
        };

        list($resourceMetadataFactory, $event) = $this->createEventObject($closure, $data);

        $validationViewListener = new ValidateListener($validator, $resourceMetadataFactory);
        $validationViewListener->onKernelView($event);
    }

    public function testGetGroupsFromService()
    {
        $data = new DummyEntity();

        $validatorProphecy = $this->prophesize(ValidatorInterface::class);
        $constraintViolationList = $this->prophesize(ConstraintViolationListInterface::class);
        $validatorProphecy->validate($data, null, ['a', 'b', 'c'])->willReturn($constraintViolationList)->shouldBeCalled();
        $validator = $validatorProphecy->reveal();

        list($resourceMetadataFactory, $event) = $this->createEventObject('groups_builder', $data);

        $containerProphecy = $this->prophesize(ContainerInterface::class);
        $containerProphecy->has('groups_builder')->willReturn(true)->shouldBeCalled();
        $containerProphecy->get('groups_builder')->willReturn(new class() {
            public function __invoke($data): array
            {
                return $data instanceof DummyEntity ? ['a', 'b', 'c'] : [];
            }
        }
        )->shouldBeCalled();

        $validationViewListener = new ValidateListener($validator, $resourceMetadataFactory, $containerProphecy->reveal());
        $validationViewListener->onKernelView($event);
    }

    public function testValidatorWithScalarGroup()
    {
        $data = new DummyEntity();
        $expectedValidationGroups = ['foo'];

        $validatorProphecy = $this->prophesize(ValidatorInterface::class);
        $constraintViolationList = $this->prophesize(ConstraintViolationListInterface::class);
        $validatorProphecy->validate($data, null, $expectedValidationGroups)->willreturn($constraintViolationList)->shouldBeCalled();

        $containerProphecy = $this->prophesize(ContainerInterface::class);
        $containerProphecy->has('foo')->willReturn(false)->shouldBeCalled();

        list($resourceMetadataFactory, $event) = $this->createEventObject('foo', $data);

        $validationViewListener = new ValidateListener($validatorProphecy->reveal(), $resourceMetadataFactory, $containerProphecy->reveal());
        $validationViewListener->onKernelView($event);
    }

    public function testDoNotCallWhenReceiveFlagIsFalse()
    {
        $data = new DummyEntity();
        $expectedValidationGroups = ['a', 'b', 'c'];

        $validatorProphecy = $this->prophesize(ValidatorInterface::class);
        $validatorProphecy->validate($data, null, $expectedValidationGroups)->shouldNotBeCalled();
        $validator = $validatorProphecy->reveal();

        list($resourceMetadataFactory, $event) = $this->createEventObject($expectedValidationGroups, $data, false);

        $validationViewListener = new ValidateListener($validator, $resourceMetadataFactory);
        $validationViewListener->onKernelView($event);
    }

    /**
     * @expectedException \ApiPlatform\Core\Bridge\Symfony\Validator\Exception\ValidationException
     */
    public function testThrowsValidationExceptionWithViolationsFound()
    {
        $data = new DummyEntity();
        $expectedValidationGroups = ['a', 'b', 'c'];

        $violationsProphecy = $this->prophesize(ConstraintViolationListInterface::class);
        $violationsProphecy->count()->willReturn(1)->shouldBeCalled();
        $violations = $violationsProphecy->reveal();

        $validatorProphecy = $this->prophesize(ValidatorInterface::class);
        $validatorProphecy->validate($data, null, $expectedValidationGroups)->willReturn($violations)->shouldBeCalled();
        $validator = $validatorProphecy->reveal();

        list($resourceMetadataFactory, $event) = $this->createEventObject($expectedValidationGroups, $data);

        $validationViewListener = new ValidateListener($validator, $resourceMetadataFactory);
        $validationViewListener->onKernelView($event);
    }

    private function createEventObject($expectedValidationGroups, $data, bool $receive = true): array
    {
        $createResourceMetadataFactory = function() use ($expectedValidationGroups, $receive) {
            $resourceMetadata = new ResourceMetadata(null, null, null, [
                'create' => ['validation_groups' => $expectedValidationGroups],
            ]);

            $resourceMetadataFactoryProphecy = $this->prophesize(ResourceMetadataFactoryInterface::class);
            if ($receive) {
                $resourceMetadataFactoryProphecy->create(DummyEntity::class)->willReturn($resourceMetadata)->shouldBeCalled();
            }

            return $resourceMetadataFactoryProphecy->reveal();
        };

        $createEvent = function() use ($receive, $data) {
            $kernel = $this->prophesize(HttpKernelInterface::class)->reveal();
            $request = new Request(/** $query */[], /** $request */[], /** $attributes */[
                '_api_resource_class' => DummyEntity::class,
                '_api_item_operation_name' => 'create',
                '_api_format' => 'json',
                '_api_mime_type' => 'application/json',
                '_api_receive' => $receive,
            ]);

            $request->setMethod(Request::METHOD_POST);
            $event = new GetResponseForControllerResultEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $data);

            return $event;
        };

        return [$createResourceMetadataFactory(), $createEvent()];
    }
}
