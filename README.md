ZfrPusher
=========

Version 1.0.0, created by Michaël Gallego

Introduction
------------

Master status [![Build Status](https://travis-ci.org/zf-fr/ZfrPusher.png?branch=master)](https://travis-ci.org/zf-fr/ZfrPusher)

ZfrPusher is a Zend Framework 2 that integrates with Pusher, a service on the cloud that brings real-time to your applications.

Installation
------------

Add "zfr/zfr-pusher" to your composer.json file and update your dependencies.

```
{
    "require": {
        "zfr/zfr-pusher": "1.0.*"
    },
}
```

Then, enable ZfrPusher by adding the key "ZfrPusher" in your `application.config.php`. Finally, copy-paste the
`config/zfr_pusher.local.php.dist` file to your `config/autoload` folder (don't forget to remove the .dist extension !),
and fill your Pusher keys.

Documentation
-------------

### What is Pusher ?

Pusher is a cloud-based service that allows you to bring real-time features to your web applications by using
web-sockets. Your client code (JavaScript) subscribes to events while your server code (PHP) triggers new events.
Events are triggered in channels.

Some use cases of Pusher can be: a chat, notifications, dynamic dashboard...

### How to use it

Using this module is simple. You need to fetch the Pusher service from the service locator:

```php
$pusherService = $serviceLocator->get('ZfrPusher\Service\PusherService');
```

Then, you have access to most of the Pusher REST API. For instance, if you want to trigger the event 'new-message'
in the channel 'foo' with some funny data:

```php
$pusherService = $pusherService->trigger('new-message', 'foo', array('content' => 'Lol catz'));
```

You can trigger a message to multiple channels at once:

```php
$pusherService = $pusherService->trigger('new-message', array('foo', 'bar'), array('content' => 'Lol catz'));
```

For more information about what you can do, please refer to the official Pusher documentation.

### Reference

Here are the current functions offered by ZfrPusher:

* `trigger($event, $channels, array $data, $socketId = '')`: trigger a new event to one or more channels ([docs](http://pusher.com/docs/rest_api#method-post-event))
* `getChannelsInfo($prefix = '', array $info = array())`: get information about multiple channels, optionally filtered by a prefix ([docs](http://pusher.com/docs/rest_api#method-get-channels))
* `getChannelInfo($name, array $info = array())`: get information about a single channel identified by its name ([docs](http://pusher.com/docs/rest_api#method-get-channel))
* `getUsersByChannel($channel)`: get a list of user ids that are currently subscribed to a channel identified by its name. Note that only presence channels (whose name begins by presence-) are allowed here ([docs](http://pusher.com/docs/rest_api#method-get-users))

### Error handling

ZfrPusher triggers exception whenever an error is sent back by Pusher API or if parameters are malformed. Each
exception implements the `ZfrPusher\Exception\ExceptionInterface` interface, so you can easily filter ZfrPusher
exceptions.

Here are the various exceptions thrown by ZfrPusher:

* `ZfrPusher\Service\AuthenticationErrorException`: this exception is most likely thrown if your Pusher credentials are invalid.
* `ZfrPusher\Service\ForbiddenException`: this exception is most likely thrown if you have exceeded your message quota or if your application has been disabled.
* `ZfrPusher\Service\Exception\RuntimeException`: this exception is thrown for any other errors


Todo
----

1. Implement WebHook (maybe through a specific controller class)
2. User authentication
