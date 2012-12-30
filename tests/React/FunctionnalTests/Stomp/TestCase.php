<?php

namespace React\FunctionnalTests\Stomp;

use React\EventLoop\Factory as LoopFactory;
use React\Stomp\Factory;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function getEventLoop()
    {
        return LoopFactory::create();
    }

    protected function getClient($loop, array $options = array())
    {
        $factory = new Factory($loop);

        if (!file_exists(__DIR__ . '/../config.php')) {
            $this->markTestSkipped(sprintf('No config file found at %s, skipping', realpath(__DIR__ . '/../config.php')));
        }

        $default = require __DIR__ . '/../config.php';
        $options = array_merge($default, $options);

        return $factory->createClient($options);
    }
}
