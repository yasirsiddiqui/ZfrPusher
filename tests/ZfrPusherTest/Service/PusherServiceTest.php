<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the MIT license.
 */

namespace ZfrPusherTest\Service;

use PHPUnit_Framework_TestCase;
use ReflectionMethod;
use Zend\Http\Request as HttpRequest;
use Zend\Http\Response as HttpResponse;
use ZfrPusher\Service\PusherService;
use ZfrPusherTest\Util\ServiceManagerFactory;

class PusherServiceTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var PusherService
     */
    protected $pusherService;

    public function setUp()
    {
        $this->pusherService = new PusherService('app-id', 'key', 'secret');
    }

    public function testCanCreateFromFactory()
    {
        $serviceLocator = ServiceManagerFactory::getServiceManager();
        $pusherService  = $serviceLocator->get('ZfrPusher\Service\PusherService');

        $this->assertInstanceOf('ZfrPusher\Service\PusherService', $pusherService);
    }

    public function testThrowExceptionIfEventExceedChannelsLimit()
    {
        $channels = array_fill(0, 101, 'channel-name');

        $this->setExpectedException('ZfrPusher\Service\Exception\RuntimeException', sprintf(
            'You are trying to trigger an event to more channels than it is allowed (maximum %s, %s given)',
            PusherService::LIMIT_CHANNELS,
            count($channels)
        ));

        $this->pusherService->trigger('event', $channels, array('key' => 'value'));
    }

    public function testThrowExceptionIfGetUserIdsOnNonPresenceChannel()
    {
        $this->setExpectedException('ZfrPusher\Service\Exception\RuntimeException', sprintf(
            'You can get a list of user ids only for presence channel, "private-channel" given'
        ));

        $this->pusherService->getUsersByChannel('private-channel');
    }

    public function testCanSignRequest()
    {
        $pusherService = new PusherService('app-id', 'api-key', '7ad3773142a6692b25b8');

        // We set variables in query to have always the same result
        $request = new HttpRequest();
        $request->getQuery()->fromArray(array(
            'auth_key'       => '278d425bdf160c739803',
            'auth_timestamp' => '1353088179',
            'auth_version'   => '1.0',
            'body_md5'       => 'ec365a775a4cd0599faeb73354201b6f'
        ));

        $request->setMethod(HttpRequest::METHOD_POST)
                ->setUri('/apps/3/events')
                ->setContent('{"name":"foo","channels":["project-3"],"data":"{\"some\":\"data\"}"}');

        $method = new ReflectionMethod('ZfrPusher\Service\PusherService', 'signRequest');
        $method->setAccessible(true);

        $method->invoke($pusherService, $request);

        $this->assertEquals('da454824c97ba181a32ccc17a72625ba02771f50b50e1e7430e47a1f3f457e6c', $request->getQuery('auth_signature'));
    }

    public function exceptionDataProvider()
    {
        return array(
            array(200, null),
            array(401, 'ZfrPusher\Service\Exception\AuthenticationErrorException'),
            array(403, 'ZfrPusher\Service\Exception\ForbiddenException'),
            array(400, 'ZfrPusher\Service\Exception\RuntimeException'),
            array(500, 'ZfrPusher\Service\Exception\RuntimeException')
        );
    }

    /**
     * @dataProvider exceptionDataProvider
     */
    public function testExceptionsAreThrownOnErrors($statusCode, $expectedException)
    {
        $method = new ReflectionMethod('ZfrPusher\Service\PusherService', 'parseResponse');
        $method->setAccessible(true);

        $response = new HttpResponse();
        $response->setStatusCode($statusCode);

        $this->setExpectedException($expectedException);

        $method->invoke($this->pusherService, $response);
    }
}
