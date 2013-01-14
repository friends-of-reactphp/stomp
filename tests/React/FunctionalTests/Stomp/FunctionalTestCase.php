<?php

namespace React\FunctionalTests\Stomp;

use React\EventLoop\Factory as LoopFactory;
use React\Stomp\Factory;

abstract class FunctionalTestCase extends \PHPUnit_Framework_TestCase
{
    private static $loaded = false;

    public static function setUpBeforeClass()
    {
        parent::setUpBeforeClass();

        if (!self::$loaded) {
            self::load();
        }
    }

    public static function tearDownAfterClass()
    {
        if (self::$loaded) {
            self::unload();
        }

        parent::tearDownAfterClass();
    }

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

    private static function load()
    {
        if (false === $provider = getenv('STOMP_PROVIDER')) {
            throw new \RuntimeException('STOMP_PROVIDER environment variable is not set');
        }

        $source = __DIR__ . '/../../../../examples/config/' . $provider . '.php';

        if (!file_exists($source)) {
            throw new \RuntimeException(sprintf('%s is not an available provider', $provider));
        }

        copy($source, __DIR__ . '/../config.php');

        self::$loaded = true;
    }

    private static function unload()
    {
        if (file_exists(__DIR__ . '/../config.php')) {
            unlink(__DIR__ . '/../config.php');
        }

        self::$loaded = false;
    }
}
