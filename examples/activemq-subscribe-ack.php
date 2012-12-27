<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient(array(
    'host'      => 'localhost',
    'login'     => 'system',
    'passcode'  => 'manager',
));

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $i = 0;

        $client->subscribeWithAck('/topic/foo', 'client', function ($frame, $ackResolver) use (&$i, $client) {
            $i++;
            echo "Message $i received: {$frame->body}\n";

            if ($i < 10) {
                $ackResolver->nack();
            } else {
                $ackResolver->ack();
                $client->disconnect();
            }
        });

        $loop->addPeriodicTimer(1, function () use ($client) {
            $client->send('/topic/foo', 'le message');
        });
    });

$loop->run();
