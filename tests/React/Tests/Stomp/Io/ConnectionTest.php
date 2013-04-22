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
        $this->catchNoConnectionError($connection);
        $connection->connect();

        $this->assertEquals(Connection::STATE_CONNECTING, $connection->getState());
        $this->assertNull($connection->socket);
    }

    /** @test */
    public function connectorShouldConnectToSpecifiedHostAndPort()
    {
        $host = "stomp.neutron";
        $port = 12345;

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->with($host, $port)
            ->will($this->returnValue($this->getDeferredPromiseMock()));

        $connection = new Connection($this->createLoopMock(), $connector);
        $this->catchNoConnectionError($connection);
        $connection->connect($host, $port);

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
        $connection->connect();

        $this->catchNoConnectionError($connection);
        $this->catchConnectionEvent($connection, 'connect', $caughtEvent);

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
        $connection->connect();

        $this->catchDisconnected($connection, $caughtError);

        $error = new ConnectionException('Connection failure');
        $deferred->reject($error);

        $this->assertEquals(Connection::STATE_DISCONNECTED, $connection->getState());
        $this->assertNull($connection->socket);
        $this->assertEquals($error, $caughtError);
    }

    /** @test */
    public function thePromiseReturnedByConnectOnResolveShouldProvideTheConnectionAsArgument()
    {
        $deferred = new Deferred();

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($deferred->promise()));

        $caughtConnection = null;

        $connection = new Connection($this->createLoopMock(), $connector);
        $connection
            ->connect()
            ->then(function ($connection) use (&$caughtConnection) {
                $caughtConnection = $connection;
            }, $this->expectCallableNever());

        $deferred->resolve(fopen('php://temp', 'r'));

        $this->assertEquals($connection, $caughtConnection);
    }

    /** @test */
    public function thePromiseReturnedByConnectOnRejectShouldProvideTheErrorAsArgument()
    {
        $deferred = new Deferred();

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($deferred->promise()));

        $caughtError = null;
        $error = new \Exception('woops');

        $connection = new Connection($this->createLoopMock(), $connector);
        $connection
            ->connect()
            ->then(
                $this->expectCallableNever(),
                function ($error) use (&$caughtError) {
                    $caughtError = $error;
                }
            );

        $deferred->reject($error);

        $this->assertEquals($error, $caughtError);
    }

    /** @test */
    public function itShouldEmitErrorOnSocketDisconnection()
    {
        $connection = $this->getConnectedConnection();

        $this->catchDisconnected($connection, $caughtError);
        $connection->socket->emit('end');
        $this->assertInstanceOf('React\Stomp\Exception\IoException', $caughtError);
        $this->assertEquals('Connection broken', $caughtError->getMessage());

        $this->assertNull($connection->socket);
        $this->assertEquals(Connection::STATE_DISCONNECTED, $connection->getState());
    }


    /** @test */
    public function itShouldDisconnectSocketOnDisconnection()
    {
        $connection = $this->getConnectedConnection();

        $mock = $this->getMockBuilder('React\Socket\Connection')
            ->disableOriginalConstructor()
            ->getMock();
        $mock->expects($this->once())
            ->method('close');

        $connection->socket = $mock;

        $this->catchNoConnectionError($connection);
        $this->catchConnectionEvent($connection, 'disconnect', $caughtDisconnected);

        $connection->disconnect();

        $this->assertTrue($caughtDisconnected);

        $this->assertNull($connection->socket);
        $this->assertEquals(Connection::STATE_DISCONNECTED, $connection->getState());
    }

    private function getConnectedConnection()
    {
        $deferred = new Deferred();

        $connector = $this->getMock('React\SocketClient\ConnectorInterface');
        $connector
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($deferred->promise()));

        $connection = new Connection($this->createLoopMock(), $connector);
        $connection->connect();

        $deferred->resolve(fopen('php://temp', 'r'));

        return $connection;
    }

    private function catchDisconnected($connection, &$caughtError)
    {
        $caughtError = null;
        $connection->on('disconnected', function ($conn, $error) use ($connection, &$caughtError) {
            $this->assertEquals($connection, $conn);
            $caughtError = $error;
        });
    }

    private function catchNoConnectionError($connection)
    {
        $connection->on('error', function ($error) {
            $this->fail(sprintf('Unexpected error caught : %s', $error->getMessage()));
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
