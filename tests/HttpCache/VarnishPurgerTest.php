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

namespace ApiPlatform\Core\Tests\HttpCache;

use ApiPlatform\Core\HttpCache\VarnishPurger;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class VarnishPurgerTest extends \PHPUnit_Framework_TestCase
{
    public function testPurge()
    {
        $createClient1 = function() {
            $clientProphecy1 = $this->prophesize(ClientInterface::class);
            $clientProphecy1->request('BAN', /** $uri */'', ['headers' => ['ApiPlatform-Ban-Regex' => '(^|\,)/foo($|\,)']])->willReturn(new Response())->shouldBeCalled();
            $clientProphecy1->request('BAN', /** $uri */'', ['headers' => ['ApiPlatform-Ban-Regex' => '((^|\,)/foo($|\,))|((^|\,)/bar($|\,))']])->willReturn(new Response())->shouldBeCalled();

            return $clientProphecy1->reveal();
        };
        $client1 = $createClient1();

        $createClient2 = function() {
            $clientProphecy2 = $this->prophesize(ClientInterface::class);
            $clientProphecy2->request('BAN', /** $uri */'', ['headers' => ['ApiPlatform-Ban-Regex' => '(^|\,)/foo($|\,)']])->willReturn(new Response())->shouldBeCalled();
            $clientProphecy2->request('BAN', /** $uri */'', ['headers' => ['ApiPlatform-Ban-Regex' => '((^|\,)/foo($|\,))|((^|\,)/bar($|\,))']])->willReturn(new Response())->shouldBeCalled();

            return $clientProphecy2->reveal();
        };
        $client2 = $createClient2();

        $purger = new VarnishPurger([$client1, $client2]);
        $purger->purge(['/foo']);
        $purger->purge(['/foo' => '/foo', '/bar' => '/bar']);
    }

    public function testEmptyTags()
    {
        $clientProphecy1 = $this->prophesize(ClientInterface::class);
        $clientProphecy1->request()->shouldNotBeCalled();

        $purger = new VarnishPurger([$clientProphecy1->reveal()]);
        $purger->purge([]);
    }
}
