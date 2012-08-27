<?php

namespace React\Tests\Stomp;

use React\Socket\ConnectionInterface;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function itShouldCallConnectionFactoryOnCreation()
    {
        $connFactory = $this->getMockBuilder('React\Stomp\ConnectionFactory')
            ->disableOriginalConstructor()
            ->getMock();
        $connFactory
            ->expects($this->once())
            ->method('create')
            ->will($this->returnValue($this->getMock('React\Socket\ConnectionInterface')));

        $client = new Client(array('connection_factory' => $connFactory));
    }

    /** @test */
    public function itShouldCreateConnectionFactoryWhenLoopGiven()
    {
        $server = stream_socket_server('tcp://localhost:37234');

        $loop = $this->getMock('React\EventLoop\LoopInterface');

        $client = new Client(array('loop' => $loop, 'host' => 'localhost', 'port' => 37234));
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Invalid configuration, must container either of: loop, connection_factory, connection.
     */
    public function itShouldThrowExceptionWhenNoConfigGiven()
    {
        $client = new Client(array());
    }

    /** @test */
    public function itShouldSendConnectFrameOnCreation()
    {
        $conn = $this->getMock('React\Socket\ConnectionInterface');
        $conn
            ->expects($this->once())
            ->method('write')
            ->with("CONNECT\naccept-version:1.1\nhost:localhost\n\n\x00");

        $client = new Client(array('connection' => $conn, 'vhost' => 'localhost'));
    }

    /** @test */
    public function itShouldChangeToConnectedStateWhenReceivingConnectedResponse()
    {
        $conn = $this->getMock('React\Socket\ConnectionInterface');

        $client = $this->getConnectedClient($conn);
    }

    /** @test */
    public function sendShouldSend()
    {
        $conn = $this->getMock('React\Socket\ConnectionInterface');
        $conn
            ->expects($this->at(2))
            ->method('write')
            ->with("SEND\ndestination:/foo\ncontent-length:5\ncontent-type:text/plain\n\nhello\x00");

        $client = $this->getConnectedClient($conn);
        $client->send('/foo', 'hello');
    }

    /** @test */
    public function messagesShouldGetRoutedToSubscriptions()
    {
        $capturedFrame = null;

        $callback = $this->createCallableMock();
        $callback
            ->expects($this->exactly(2))
            ->method('__invoke')
            ->will($this->returnCallback(function ($frame) use (&$capturedFrame) {
                $capturedFrame = $frame;
            }));

        $conn = $this->getMock('React\Socket\ConnectionInterface');

        $client = $this->getConnectedClient($conn);
        $subscriptionId = $client->subscribe('/foo', $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 42, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $client->handleData((string) $responseFrame);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 43, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $client->handleData((string) $responseFrame);

        $this->assertInstanceOf('React\Stomp\Protocol\Frame', $capturedFrame);
        $this->assertFrameEquals($responseFrame, $capturedFrame);

        return array($client, $capturedFrame);
    }

    /**
    * @test
    * @depends messagesShouldGetRoutedToSubscriptions
    */
    public function callbackShouldNotBeCalledAfterUnsubscribe($data)
    {
        list($client, $capturedFrame) = $data;

        $client->unsubscribe($capturedFrame->getHeader('subscription'));

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => 0, 'message-id' => 42, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $client->handleData((string) $responseFrame);
    }

    /** @test */
    public function disconnectShouldGracefullyDisconnect()
    {
        $conn = $this->getMock('React\Socket\ConnectionInterface');

        $client = $this->getMockBuilder('React\Stomp\Client')
            ->setConstructorArgs(array(array('connection' => $conn)))
            ->setMethods(array('generateReceiptId'))
            ->getMock();
        $client
            ->expects($this->once())
            ->method('generateReceiptId')
            ->will($this->returnValue('1234'));
        $client->handleData("CONNECTED\n\n\x00");
        $client->disconnect();

        $conn
            ->expects($this->once())
            ->method('close');

        $client->handleData("RECEIPT\nreceipt-id:1234\n\n\x00");
    }

    private function getConnectedClient(ConnectionInterface $conn)
    {
        $client = new Client(array('connection' => $conn, 'vhost' => 'localhost'));
        $client->handleData("CONNECTED\n\n\x00");

        return $client;
    }

    private function assertFrameEquals(Frame $expected, Frame $frame)
    {
        $this->assertSame((string) $expected, (string) $frame);
    }

    private function createCallableMock()
    {
        return $this->getMock('React\Tests\Stomp\Stub\CallableStub');
    }
}
