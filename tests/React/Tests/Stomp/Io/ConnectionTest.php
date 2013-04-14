<?php

namespace React\Tests\Stomp\Io;

use React\Tests\Stomp\TestCase;
use React\Stomp\Io\Connection;
use React\Promise\Deferred;
use React\SocketClient\ConnectionException;

class ConnectionTest extends TestCase
{
    /** @test */
    public function connectorShouldConnectFromDisconnectedToConnecting()
    {
        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->with('localhost', 61613)
            ->will($this->returnValue($this->getDeferredPromiseMock()));

        $connection = new Connection($this->createLoopMock(), $connector);
        $this->catchConnectionEvent($connection, 'connecting', $caughtEvent);
        $connection->setState(Connection::STATE_CONNECTING);
        $this->assertTrue($caughtEvent);

        $this->assertEquals(Connection::STATE_CONNECTING, $connection->getState());
        $this->assertNull($connection->socket);
    }

    /** @test */
    public function itShouldBeConnectedOnPromiseResolution()
    {
        $deferred = new Deferred();

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($deferred->promise()));

        $connection = new Connection($this->createLoopMock(), $connector);
        $connection->setState(Connection::STATE_CONNECTING);

        $this->catchConnectionEvent($connection, 'connected', $caughtEvent);

        $deferred->resolve(fopen('php://temp', 'r'));

        $this->assertEquals(Connection::STATE_CONNECTED, $connection->getState());
        $this->assertInstanceOf('React\Socket\Connection', $connection->socket);
        $this->assertTrue($caughtEvent);
    }

    /** @test */
    public function itShouldBeDisconnectedOnPromiseReject()
    {
        $deferred = new Deferred();

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($deferred->promise()));

        $connection = new Connection($this->createLoopMock(), $connector);
        $connection->setState(Connection::STATE_CONNECTING);

        $this->catchConnectionError($connection, $caughtError);

        $error = new ConnectionException('Connection failure');
        $deferred->reject($error);

        $this->assertEquals(Connection::STATE_DISCONNECTED, $connection->getState());
        $this->assertNull($connection->socket);
        $this->assertEquals($error, $caughtError);
    }

    private function catchConnectionError($connection, &$caughtError)
    {
        $caughtError = null;
        $connection->on('error', function ($error) use (&$caughtError) {
            $caughtError = $error;
        });
    }

    private function catchConnectionEvent($connection, $event, &$caughtEvent)
    {
        $caughtEvent = false;
        $connection->on($event, function ($givenConnection) use ($connection, &$caughtEvent) {
            $this->assertEquals($connection, $givenConnection);
            $caughtEvent = true;
        });
    }

    private function createLoopMock()
    {
        return $this->getMock('React\EventLoop\LoopInterface');
    }

    private function getDeferredPromiseMock()
    {
        return $this->getMockBuilder('React\Promise\DeferredPromise')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
