<?php

namespace React\Functional\Stomp;

use React\EventLoop\Factory as LoopFactory;
use React\Stomp\Factory;

abstract class FunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    protected function getEventLoop()
    {
        return LoopFactory::create();
    }

    protected function getClient($loop, array $options = array())
    {
        $factory = new Factory($loop);

        if (false === getenv('STOMP_PROVIDER')) {
            throw new \RuntimeException('STOMP_PROVIDER environment variable is not set');
        }

        $provider = getenv('STOMP_PROVIDER');
        $configFile = sprintf('%s/%s.php', realpath(__DIR__ . '/../../../../examples/config'), $provider);

        if (!file_exists($configFile)) {
            throw new \RuntimeException(sprintf('Invalid STOMP_PROVIDER: No config file found at %s', $configFile));
        }

        $default = require $configFile;
        $options = array_merge($default, $options);

        return $factory->createClient($options);
    }
}
