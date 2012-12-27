<?php

require __DIR__.'/../vendor/autoload.php';

$conf = require __DIR__ . '/config/probe.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient($conf);

$client
    ->connect()
    ->then(function ($client) use ($loop) {
        $prevMessageCount = 0;
        $messageCount = 0;

        $loop->addPeriodicTimer(1, function () use ($client, &$messageCount) {
            for ($i = 0; $i < 2000; $i++) {
                $client->send('/topic/foo', 'le message');
                $messageCount++;
            }
        });

        $loop->addPeriodicTimer(1, function () use (&$prevMessageCount, &$messageCount) {
            $diff = $messageCount - $prevMessageCount;
            echo "Sent this second: $diff\n";
            $prevMessageCount = $messageCount;
        });
    }, function (\Exception $e) {
        echo sprintf("Could not connect: %s\n", $e->getMessage());
    });

$loop->run();
