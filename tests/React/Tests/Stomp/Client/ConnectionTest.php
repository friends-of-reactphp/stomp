<?php

namespace React\Tests\Stomp\Client;

use React\Tests\Stomp\TestCase;
use React\Stomp\Client\Connection;
use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Io\InputStream;
use React\Stomp\Protocol\Frame;
use React\Stomp\Io\Connection as TcpConnection;
use React\Stomp\Client\State;
use React\Stomp\Exception\UnexpectedFrameException;

class ConnectionTest extends TestCase
{
    /** @test */
    public function itShouldProvideStateAndPackageCreatorAndPackageProcessorAfterConstruction()
    {
        $connection = $this->createConnection();

        $this->assertInstanceOf('React\Stomp\Client\State', $connection->state);

        $this->asserTTrue($connection->state->isDisconnected());

        $this->assertInstanceOf('React\Stomp\Client\OutgoingPackageCreator', $connection->packageCreator);
        $this->assertInstanceOf('React\Stomp\Client\IncomingPackageProcessor', $connection->packageProcessor);
    }


    /**
     * @dataProvider provideIncomingProcessorFrameAndError
     * @test
     */
    public function itShouldForwardIncomingProcessorEvents($eventName, $data)
    {
        $connection = $this->createConnection();
        $processor = new IncomingPackageProcessor($connection->state);
        $connection->setIncomingPackageProcessor($processor);

        $caughtData = null;
        $n = 0;

        $connection->on($eventName, function ($data) use(&$n, &$caughtData) {
            $caughtData = $data;
            $n++;
        });

        $processor->emit($eventName, array($data));

        $this->assertEquals(1, $n);
        $this->assertEquals($data, $caughtData);
    }

    /** @test */
    public function itShouldEmitConnectedOnIncomingProcessorConnectedEvent()
    {
        $connection = $this->createConnection();
        $processor = new IncomingPackageProcessor($connection->state);
        $connection->setIncomingPackageProcessor($processor);

        $caughtConnection = null;

        $connection->on('connected', function ($connection) use(&$caughtConnection) {
            $caughtConnection = $connection;
        });

        $processor->emit('connected', array(new Frame('CONNECTED')));

        $this->assertEquals($connection, $caughtConnection);
    }

    /** @test */
    public function itShouldCallTcpDisconnectionOnIncomingProcessorDisconnectEvent()
    {
        $conn = $this->createTcpConnectionMock();
        $conn->expects($this->once())
            ->method('disconnect');

        $connection = $this->createConnection($conn);
        $processor = new IncomingPackageProcessor($connection->state);
        $connection->setIncomingPackageProcessor($processor);

        $processor->emit('disconnected', array(new Frame('RECEIPT')));
    }

    /** @test */
    public function itShouldForwardTcpConnectionErrors()
    {
        $conn = $this->createTcpConnection();
        $connection = $this->createConnection($conn);

        $caughtError = null;
        $n = 0;

        $connection->on('error', function ($error) use(&$n, &$caughtError) {
            $caughtError = $error;
            $n++;
        });

        $error = new \RuntimeException('woops');

        $conn->emit('error', array($error));

        $this->assertEquals(1, $n);
        $this->assertEquals($error, $caughtError);
    }

    /** @test */
    public function itShouldWireInputAndOutputWithSocketOnTcpConnection()
    {
        $conn = $this->createTcpConnection();
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $connection = $this->createConnection($conn, $input, $output);

        $connection->vhost = 'react-vhost';
        $connection->login = 'react-login';
        $connection->passcode = 'react-passcode';

        $conn->socket = $this->getMock('React\Socket\ConnectionInterface');

        $conn->socket
            ->expects($this->once())
            ->method('pipe')
            ->with($input);

        $output->expects($this->once())
            ->method('pipe')
            ->with($conn->socket);

        $capturedFrame = null;

        $output->expects($this->once())
            ->method('sendFrame')
            ->will($this->returnCallback(function ($frame) use (&$capturedFrame) {
                $capturedFrame = $frame;
            }));

        $conn->emit('connected', array($conn));

        $frame = new Frame('CONNECT', array(
            'accept-version' => '1.1',
            'host' => 'react-vhost',
            'login' => 'react-login',
            'passcode' => 'react-passcode',
        ));

        $this->assertFrameEquals($frame, $capturedFrame);
    }

    /** @test */
    public function itShouldHandleIncomingFramesWithIncomingProcessor()
    {
        $input = new InputStream(
            $this->getMockBuilder('React\Stomp\Protocol\Parser')
            ->disableOriginalConstructor()
            ->getMock()
        );

        $connection = $this->createConnection(null, $input);

        $processor = $this->getMockBuilder('React\Stomp\Client\IncomingPackageProcessor')
            ->disableOriginalConstructor()
            ->getMock();

        $connection->setIncomingPackageProcessor($processor);

        $frame = new Frame('MESSAGE');

        $processor->expects($this->once())
            ->method('receiveframe')
            ->with($frame);

        $input->emit('frame', array($frame));
    }

    /** @test */
    public function itShouldEmitErrorWhenIncomingProcessorThrowsException()
    {
        $input = $this->createInputStreamMock();

        $connection = $this->createConnection(null, $input);
        $processor = $this->getMockBuilder('React\Stomp\Client\IncomingPackageProcessor')
            ->disableOriginalConstructor()
            ->getMock();

        $connection->setIncomingPackageProcessor($processor);

        $n = 0;
        $caughtError = null;

        $connection->on('error', function ($error) use (&$n, &$caughtError) {
           $n++;
           $caughtError = $error;
        });

        $frame = new Frame('MESSAGE');
        $error = new UnexpectedFrameException($frame, 'Unexpected frame');

        $processor->expects($this->once())
            ->method('receiveframe')
            ->will($this->throwException($error));

        $input->emit('frame', array($frame));

        $this->assertEquals(1, $n);
        $this->assertEquals($error, $caughtError);
    }

    /** @test */
    public function itShouldSendFrameIfItIsConnected()
    {
        $output = $this->createOutputStreamMock();

        $connection = $this->createConnection(null, null, $output);
        $connection->state = $this->createStateMock();

        $connection->state->expects($this->once())
            ->method('isConnected')
            ->will($this->returnValue(true));

        $frame = new Frame('MESSAGE');

        $output->expects($this->once())
            ->method('sendFrame')
            ->with($frame);

        $connection->send($frame);
    }

    /** @test */
    public function itShouldNotSendFrameIfItIsNotConnected()
    {
        $output = $this->createOutputStreamMock();

        $connection = $this->createConnection(null, null, $output);
        $connection->state = $this->createStateMock();

        $connection->state->expects($this->once())
            ->method('isConnected')
            ->will($this->returnValue(false));

        $frame = new Frame('MESSAGE');

        $output->expects($this->never())
            ->method('sendFrame');

        $n = 0;
        $caughtError = null;

        $connection->on('error', function ($error) use (&$n, &$caughtError) {
           $n++;
           $caughtError = $error;
        });

        $connection->send($frame);

        $this->assertEquals(1, $n);
        $this->assertInstanceOf('React\Stomp\Exception\FrameNotSentException', $caughtError);
        $this->assertEquals($frame, $caughtError->getFrame());
    }

    public function provideIncomingProcessorFrameAndError()
    {
        return array(
            array('frame', new Frame('MESSAGE')),
            array('error', new \Exception('woops !')),
        );
    }

    private function createStateMock()
    {
        return $this->getMockBuilder('React\Stomp\Client\State')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createConnection($conn = null, $input = null, $output = null, $loop = null)
    {
        $conn = $conn ?: $this->createTcpConnectionMock();

        $loop = $loop ?: $this->createLoopMock();
        $input = $input ?: $this->createInputStreamMock();
        $output = $output ?: $this->createOutputStreamMock();

        return new Connection($loop, $input, $output, $conn);
    }

    private function createTcpConnection()
    {
        return new TcpConnection($this->createLoopMock(), $this->getMock('React\SocketClient\ConnectorInterface'));
    }

    private function createTcpConnectionMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\Connection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function getTcpConnectionMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\Connection')
            ->disableOriginalConstructor()
            ->getMock();
    }

    private function createLoopMock()
    {
        return $this->getMock('React\EventLoop\LoopInterface');
    }

    private function createInputStreamMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\InputStream')
            ->setConstructorArgs(array($this->getMock('React\Stomp\Protocol\Parser')))
            ->setMethods(array('isWritable', 'write', 'end', 'close'))
            ->getMock();
    }
//    private function createInputStreamMock()
//    {
//        return $this->getMockBuilder('React\Stomp\Io\InputStream')
//            ->disableOriginalConstructor()
//            ->getMock();
//    }

    private function createOutputStreamMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\OutputStream')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
