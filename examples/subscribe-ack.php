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

        $client->send('/topic/foo', 'le message');
    }, function (\Exception $e) {
        echo sprintf("Could not connect: %s\n", $e->getMessage());
    });

$loop->run();
