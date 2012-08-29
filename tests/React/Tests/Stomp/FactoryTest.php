<?php

namespace React\Tests\Stomp;

use React\Stomp\Factory;

class FactoryTest extends TestCase
{
    public function testCreateConnection()
    {
        $server = stream_socket_server('tcp://localhost:37234');

        $loop = $this->getMock('React\EventLoop\LoopInterface');
        $factory = new Factory($loop);
        $conn = $factory->createConnection(array('host' => 'localhost', 'port' => 37234));

        $this->assertInstanceOf('React\Socket\Connection', $conn);
    }

    public function testCreateClient()
    {
        $server = stream_socket_server('tcp://localhost:37235');

        $loop = $this->getMock('React\EventLoop\LoopInterface');
        $factory = new Factory($loop);
        $client = $factory->createClient(array('host' => 'localhost', 'port' => 37235));

        $this->assertInstanceOf('React\Stomp\Client', $client);
    }
}
