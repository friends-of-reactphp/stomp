<?php

require __DIR__.'/../vendor/autoload.php';

// run this script and turn off your RabbitMQ server

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient(array(
    'heartbeat-guarantee' => 100,
    'heartbeat-expect'    => 200,
));

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $client->subscribe('/topic/foo', function ($frame) {
            echo "Message received: {$frame->body}\n";
        });

        $client->on('error', function ($error) {
            echo "Client error: {$error->getMessage()}\n";
        });

        $loop->addPeriodicTimer(1, function () use ($client) {
            $client->send('/topic/foo', 'le message');
        });
    });

$loop->run();
