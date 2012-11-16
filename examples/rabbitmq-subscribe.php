<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient();

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $prevMessageCount = 0;
        $messageCount = 0;

        $client->subscribe('/topic/foo', function ($frame) use (&$messageCount) {
            $messageCount++;
        });

        $loop->addPeriodicTimer(1, function () use (&$prevMessageCount, &$messageCount) {
            $diff = $messageCount - $prevMessageCount;
            echo "Received this second: $diff\n";
            $prevMessageCount = $messageCount;
        });
    });

$loop->run();
