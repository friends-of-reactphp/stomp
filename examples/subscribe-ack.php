<?php

require __DIR__.'/../vendor/autoload.php';

$conf = require __DIR__ . '/config/probe.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient($conf);

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $i = 0;

        $client->subscribeWithAck('/topic/foo', 'client', function ($frame, $ackResolver) use ($client) {

            if (0 === mt_rand() % 2) {
                echo "Message {$frame->body} received but not acknowledged\n";
                $ackResolver->nack();
            } else {
                echo "Message {$frame->body} received\n";
                $ackResolver->ack();
            }
        });

        $loop->addPeriodicTimer(1, function () use (&$i, $client) {
            $client->send('/topic/foo', "le message #$i");
            $i++;
        });
    }, function (\Exception $e) {
        echo sprintf("Could not connect: %s\n", $e->getMessage());
    });

$loop->run();
