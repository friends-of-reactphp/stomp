<?php

require __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$factory = new React\Stomp\Factory($loop);
$client = $factory->createClient(array(
    'login'     => 'admin',
    'passcode'  => 'password',
    'vhost'     => 'apollo',
));

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
    });

$loop->run();
