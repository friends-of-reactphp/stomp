# React/STOMP

STOMP bindings for React.

STOMP is a messaging protocol. It is supported by most message queue brokers,
such as [RabbitMQ](http://www.rabbitmq.com/),
[Apollo](http://activemq.apache.org/apollo/) and many others.

A message queue is used for asynchronous inter-process communication. This can
be useful for workers, general long-running tasks, or communication between
long-running daemons.

[![Build Status](https://secure.travis-ci.org/reactphp/stomp.png?branch=master)](http://travis-ci.org/reactphp/stomp) [![Code Climate](https://codeclimate.com/github/reactphp/stomp/badges/gpa.svg)](https://codeclimate.com/github/reactphp/stomp)

## Install

The recommended way to install react/stomp is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "react/stomp": "0.1.*"
    }
}
```

## Example

You can interact with a STOMP server by using the `React\Stomp\Client`.

```php
<?php

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient(array('vhost' => '/', 'login' => 'guest', 'passcode' => 'guest'));

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $client->subscribe('/topic/foo', function ($frame) {
            echo "Message received: {$frame->body}\n";
        });

        $loop->addPeriodicTimer(1, function () use ($client) {
            $client->send('/topic/foo', 'le message');
        });
    });

$loop->run();
```

## Options

* `host`: Host to connect to, defaults to `127.0.0.1`.
* `port`: Port to connect to, defaults to `61613` (rabbitmq's stomp plugin).
* `vhost`: Virtual host, defaults to `/`.
* `login`: Login user name, defaults to `guest`.
* `passcode`: Login passcode, defaults to `guest`.

## Acknowledgement

When subscribing with the `subscribe` method, messages are considered
acknowledged as soon as they are sent by the server (ack header is set to
'auto').

You can subscribe with a manual acknowledgement by using `subscribeWithAck`
(see [SUBSCRIBE](http://stomp.github.com//stomp-specification-1.1.html#SUBSCRIBE)
in the STOMP spec for available ack mode values).

You will get a `React\Stomp\AckResolver` as second argument of the callback to
acknowledge or not the message :

```php
$client->subscribeWithAck('/topic/foo', 'client', function ($frame, $ackResolver) {
    if ($problem) {
        $ackResolver->nack();
    } else {
        $ackResolver->ack();
    }
});
```

## Todo

* Support nul bytes in frame body
* Heart-beating
* Consuming ACKs
* Transactions
* Streaming frame bodies (using stream API)

## Tests

To run the test suite, you need PHPUnit.

    $ phpunit

## License

MIT, see LICENSE.

## Resources

* [STOMP Protocol Specification, Version 1.1](https://stomp.github.io/stomp-specification-1.1.html)
* [RabbitMQ STOMP Adapter](http://www.rabbitmq.com/stomp.html)
