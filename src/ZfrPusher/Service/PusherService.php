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

namespace ZfrPusher\Service;

use Zend\Http\Client as HttpClient;
use Zend\Http\Request as HttpRequest;
use Zend\Http\Response as HttpResponse;

/**
 * This service implements the current specification of Pusher REST API (as of 16th april 2013)
 *
 * @licence MIT
 */
class PusherService
{
    /**
     * Pusher API endpoint
     */
    const API_ENDPOINT = '//api.pusherapp.com';

    /**
     * Limit of channels an event can be sent to
     */
    const LIMIT_CHANNELS = 100;

    /**
     * Zend HTTP client used to perform REST requests
     *
     * @var HttpClient
     */
    protected $client;

    /**
     * Id of the application to use
     *
     * @var string
     */
    protected $appId;

    /**
     * Pusher API key
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Pusher secret key
     *
     * @var string
     */
    protected $secretKey;

    /**
     * @param string $appId
     * @param string $apiKey
     * @param string $secretKey
     */
    public function __construct($appId, $apiKey, $secretKey)
    {
        $this->appId     = (string) $appId;
        $this->apiKey    = (string) $apiKey;
        $this->secretKey = (string) $secretKey;

        $this->client = new HttpClient();
    }

    /**
     * ----------------------------------------------------------------------------------------------------
     * EVENTS
     * ----------------------------------------------------------------------------------------------------
     */

    /**
     * Trigger a new event
     *
     * @link  http://pusher.com/docs/rest_api#method-post-event
     * @param  string        $event    Event name
     * @param  array|string  $channels Single or list of channels
     * @param  array         $data     Event data (limited to 10 Kb)
     * @param  string        $socketId Exclude a specific socket id from the event
     * @throws Exception\RuntimeException If you trigger an event to more than 100 channels
     * @return void
     */
    public function trigger($event, $channels, array $data, $socketId = '')
    {
        if (count($channels) > self::LIMIT_CHANNELS) {
            throw new Exception\RuntimeException(sprintf(
                'You are trying to trigger an event to more channels than it is allowed (maximum %s, %s given)',
                self::LIMIT_CHANNELS,
                count($channels)
            ));
        }

        $parameters = array(
            'name'      => $event,
            'channels'  => (is_array($channels) ? $channels : array($channels)),
            'data'      => json_encode($data), // data must be a string
            'socket_id' => $socketId
        );

        $client = $this->prepareHttpClient('/apps/' . $this->appId . '/events', $parameters);

        $this->signRequest($client->getRequest());

        $this->parseResponse($client->send());
    }

    /**
     * ----------------------------------------------------------------------------------------------------
     * CHANNELS
     * ----------------------------------------------------------------------------------------------------
     */

    /**
     * Get information about multiple channels, optionally filtered by a prefix
     *
     * @link   http://pusher.com/docs/rest_api#method-get-channels
     * @param  string $prefix
     * @param  array  $info
     * @return array
     */
    public function getChannelsInfo($prefix = '', array $info = array())
    {
        $parameters = array(
            'filter_by_prefix' => $prefix,
            'info'             => implode(',', $info)
        );

        $client = $this->prepareHttpClient('/apps/' . $this->appId . '/channels')
                       ->setMethod(HttpRequest::METHOD_GET)
                       ->setParameterGet(array_filter($parameters));

        $this->signRequest($client->getRequest());

        return $this->parseResponse($client->send());
    }

    /**
     * Get information about a single channel identified by its name
     *
     * @link   http://pusher.com/docs/rest_api#method-get-channel
     * @param  string $name
     * @param  array  $info
     * @return array
     */
    public function getChannelInfo($name, array $info = array())
    {
        $parameters = array('info' => implode(',', $info));

        $client = $this->prepareHttpClient('/apps/' . $this->appId . '/channels/' . $name)
                       ->setMethod(HttpRequest::METHOD_GET)
                       ->setParameterGet(array_filter($parameters));

        $this->signRequest($client->getRequest());

        return $this->parseResponse($client->send());
    }

    /**
     * ----------------------------------------------------------------------------------------------------
     * USERS
     * ----------------------------------------------------------------------------------------------------
     */

    /**
     * Get a list of user ids that are currently subscribed to a channel identified by its name. Note that
     * only presence channels (whose name begins by presence-) are allowed here
     *
     * @link   http://pusher.com/docs/rest_api#method-get-users
     * @param  string $channel
     * @throws Exception\RuntimeException If channel given is not a presence channel
     * @return array
     */
    public function getUsersByChannel($channel)
    {
        if (substr($channel, 0, 9) !== 'presence-') {
            throw new Exception\RuntimeException(sprintf(
                'You can get a list of user ids only for presence channel, "%s" given',
                $channel
            ));
        }

        $client = $this->prepareHttpClient('/apps/' . $this->appId . '/channels/' . $channel . '/users')
                       ->setMethod(HttpRequest::METHOD_GET);

        $this->signRequest($client->getRequest());

        return $this->parseResponse($client->send());
    }

    /**
     * Prepare the HTTP client to send the REST queries
     *
     * @param  string $uri
     * @param  array  $parameters
     * @return HttpClient
     */
    private function prepareHttpClient($uri, array $parameters = array())
    {
        $this->client->getRequest()
                     ->getHeaders()
                     ->addHeaderLine('Content-Type', 'application/json');

        return $this->client->setMethod(HttpRequest::METHOD_POST)
                            ->setUri(self::API_ENDPOINT . $uri)
                            ->setRawBody(json_encode(array_filter($parameters)));
    }

    /**
     * Each request send to Pusher must be signed properly. The algorithm is described in Pusher's documentation
     *
     * @link   http://pusher.com/docs/rest_api#authentication
     * @param  HttpRequest $request
     * @return void
     */
    private function signRequest(HttpRequest $request)
    {
        $queryParameters = array(
            'auth_key'       => $this->apiKey,
            'auth_timestamp' => time(),
            'auth_version'   => '1.0',
            'body_md5'       => $request->getContent() ? md5($request->getContent()) : ''
        );

        // We need to traverse each Query parameter to make sure the key is lowercased
        foreach ($request->getQuery() as $key => $value) {
            $queryParameters[strtolower($key)] = $value;
        }

        ksort($queryParameters);

        $method      = strtoupper($request->getMethod());
        $requestPath = $request->getUri()->getPath();
        $query       = urldecode(http_build_query(array_filter($queryParameters)));

        $signature = hash_hmac('sha256', implode(PHP_EOL, array($method, $requestPath, $query)), $this->secretKey);

        $queryParameters['auth_signature'] = $signature;

        $request->getQuery()->fromArray($queryParameters);
    }

    /**
     * @param  HttpResponse $response
     * @throws Exception\ForbiddenException
     * @throws Exception\AuthenticationErrorException
     * @throws Exception\RuntimeException
     * @return array
     */
    private function parseResponse(HttpResponse $response)
    {
        if ($response->isSuccess()) {
            return json_decode($response->getBody(), true);
        }

        switch ($response->getStatusCode()) {
            case 401:
                throw new Exception\AuthenticationErrorException($response->getBody(), 401);
            case 403:
                throw new Exception\ForbiddenException($response->getBody(), 403);
            case 400:
            default:
                throw new Exception\RuntimeException($response->getBody(), $response->getStatusCode());
        }
    }
}
