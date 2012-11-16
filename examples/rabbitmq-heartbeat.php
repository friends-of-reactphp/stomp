<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient(array(
    'vhost'        => '/',
    'login'        => 'guest',
    'passcode'     => 'guest',
    'heartbeat-cx' => 100,
    'heartbeat-cy' => 200,
));

$client->on('ready', function () use ($loop, $client) {
    $client->subscribe('/topic/foo', function ($frame) {
        echo "Message received: {$frame->body}\n";
    });

//    $loop->addPeriodicTimer(1, function () use ($client) {
//        $client->send('/topic/foo', 'le message');
//    });
});

$loop->run();
