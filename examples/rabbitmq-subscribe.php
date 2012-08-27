<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$client = new React\Stomp\Client(array('loop' => $loop, 'vhost' => '/', 'login' => 'guest', 'passcode' => 'guest'));
$client->on('ready', function () use ($loop, $client) {
    $client->subscribe('/topic/foo', function ($frame) {
        echo "Message received: {$frame->body}\n";
    });

    $loop->addPeriodicTimer(1, function () use ($client) {
        $client->send('/topic/foo', 'le message');
    });
});

$loop->run();
