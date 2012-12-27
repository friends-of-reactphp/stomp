<?php

require __DIR__.'/../vendor/autoload.php';

$conf = require __DIR__ . '/config/probe.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient($conf);

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $client->subscribe('/topic/foo', function ($frame) {
            echo "Message received: {$frame->body}\n";
        });

        $loop->addPeriodicTimer(1, function () use ($client) {
            $client->send('/topic/foo', 'le message');
        });
    }, function (\Exception $e) {
        echo sprintf("Could not connect : %s\n", $e->getMessage());
    });

$loop->run();
