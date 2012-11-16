<?php

require __DIR__.'/../vendor/autoload.php';

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
