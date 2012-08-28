# React/STOMP

STOMP bindings for React.

[![Build Status](https://secure.travis-ci.org/react-php/stomp.png?branch=master)](http://travis-ci.org/react-php/stomp)

## Install

The recommended way to install react/stomp is [through composer](http://getcomposer.org).

```JSON
{
    "require": {
        "react/stomp": "dev-master"
    }
}
```

## Example

You can interact with a STOMP server by using the `React\Stomp\Client`.

```php
<?php

$loop = React\EventLoop\Factory::create();

$client = new React\Stomp\Client(array('loop' => $loop, 'login' => 'guest', 'passcode' => 'guest'));
$client->on('ready', function () use ($loop, $client) {
    $client->subscribe('/foo', function ($frame) {
        echo "Message received: {$frame->body}\n";
    });

    $loop->addPeriodicTimer(1, function () use ($client) {
        $client->send('/foo', 'le message');
    });
});

$loop->run();
```

## Todo

* Support nul bytes in frame body
* Heart-beating
* Usable API for ack/nack
* Transactions
* Streaming frame bodies (using stream API)
* Error handling

## Tests

To run the test suite, you need PHPUnit.

    $ phpunit

## License

MIT, see LICENSE.

## Resources

* [STOMP Protocol Specification, Version 1.1](http://stomp.github.com/stomp-specification-1.1.html)
* [RabbitMQ STOMP Adapter](http://www.rabbitmq.com/stomp.html)
