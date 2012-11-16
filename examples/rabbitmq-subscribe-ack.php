<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient(array('vhost' => '/', 'login' => 'guest', 'passcode' => 'guest'));

$client->on('ready', function () use ($loop, $client) {
    $i = 0;

    $client->subscribeWithAck('/topic/foo', 'client', function ($frame, $ackResolver) use (&$i, $client) {
        $i++;
        echo "Message $i received: {$frame->body}\n";

        if ($i < 5) {
            // do nothing
        } else if ($i < 10) {
            $ackResolver->nack();
        } else {
            $ackResolver->ack();
            $client->disconnect();
        }
    });

    $client->send('/topic/foo', 'le message');
});

$loop->run();
