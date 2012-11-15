# React/STOMP

STOMP bindings for React.

[![Build Status](https://secure.travis-ci.org/reactphp/stomp.png?branch=master)](http://travis-ci.org/reactphp/stomp)

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

$client->on('ready', function () use ($loop, $client) {
    $client->subscribe('/topic/foo', function ($frame) {
        echo "Message received: {$frame->body}\n";
    });

    $loop->addPeriodicTimer(1, function () use ($client) {
        $client->send('/topic/foo', 'le message');
    });
});

$loop->run();
```

## Todo

* Support nul bytes in frame body
* Heart-beating
* Transactions
* Streaming frame bodies (using stream API)

## Tests

To run the test suite, you need PHPUnit.

    $ phpunit

## License

MIT, see LICENSE.

## Resources

* [STOMP Protocol Specification, Version 1.1](http://stomp.github.com/stomp-specification-1.1.html)
* [RabbitMQ STOMP Adapter](http://www.rabbitmq.com/stomp.html)
