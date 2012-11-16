<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient();

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
    });

$loop->run();
