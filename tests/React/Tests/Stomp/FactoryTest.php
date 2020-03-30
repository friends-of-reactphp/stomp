<?php

namespace React\Tests\Stomp;

use React\Stomp\Factory;
use React\Stomp\Exception\ConnectionException;

class FactoryTest extends TestCase
{
    public function testCreateConnection()
    {
        $server = stream_socket_server('tcp://localhost:37234');

        $loop = $this->createMock('React\EventLoop\LoopInterface');
        $factory = new Factory($loop);
        $conn = $factory->createConnection(array('host' => 'localhost', 'port' => 37234));

        $this->assertInstanceOf('React\Socket\Connection', $conn);
    }

    /**
     * @test
     * @doesNotPerformAssertions
     */
    public function itShouldThrowAnExceptionInCaseSocketCreationFails()
    {
        $loop = $this->createMock('React\EventLoop\LoopInterface');
        $factory = new Factory($loop);

        try {
            $factory->createConnection(array('host' => 'localhost', 'port' => 37235));
            $this->fail('This should have raised an exception');
        } catch (ConnectionException $e) {

        }
    }

    public function testCreateClient()
    {
        $server = stream_socket_server('tcp://localhost:37235');

        $loop = $this->createMock('React\EventLoop\LoopInterface');
        $factory = new Factory($loop);
        $client = $factory->createClient(array('host' => 'localhost', 'port' => 37235));

        $this->assertInstanceOf('React\Stomp\Client', $client);
    }
}
