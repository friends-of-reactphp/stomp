<?php

namespace React\Tests\Stomp;

use React\Stomp\Client;
use React\Stomp\Client\Heartbeat;
use React\Stomp\Io\OutputStream;
use React\Stomp\Io\InputStreamInterface;
use React\Stomp\Io\OutputStreamInterface;
use React\Stomp\Protocol\Frame;
use React\Stomp\Protocol\HeartbeatFrame;
use React\EventLoop\Factory as EventLoopFactory;

class ClientTest extends TestCase
{
    /** @test */
    public function connectShouldSendConnectFrame()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->once())
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'localhost'))
            ));

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $client->connect();
    }

    /** @test */
    public function connectShouldReturnPromise()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $promise = $client->connect();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise);
        $promise->then($this->expectCallableNever());
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function connectShouldRejectMissingHostOrVhost()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();
        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array());
    }

    /** @test */
    public function connectTwiceShouldReturnTheSamePromise()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $promise1 = $client->connect();
        $promise2 = $client->connect();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise1);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise2);
        $this->assertSame($promise1, $promise2);
    }

    /** @test */
    public function disconnectThenConnectShouldReturnNewPromise()
    {
        $input = $this->createInputStreamMock();

        $connectFrame = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'localhost'));

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(1))
            ->method('sendFrame')
            ->with($this->frameIsEqual($connectFrame));
        $output
            ->expects($this->at(3))
            ->method('sendFrame')
            ->with($this->frameIsEqual($connectFrame));

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $promise1 = $client->connect();
        $client->disconnect();
        $promise2 = $client->connect();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise1);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise2);
        $this->assertNotSame($promise1, $promise2);
    }

    /** @test */
    public function itShouldEmitConnectAfterHandshake()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $client->on('connect', $this->expectCallableOnce());
        $client->connect();

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', array($frame));
    }

    /** @test */
    public function itShouldResolveConnectPromiseAfterHandshake()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $client->on('connect', $this->expectCallableOnce());
        $client
            ->connect()
            ->then($this->expectCallableOnce());

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', array($frame));
    }

    /** @test */
    public function itShouldChangeToConnectedStateWhenReceivingConnectedResponse()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getConnectedClient($input, $output);
    }

    /** @test */
    public function itShouldAskHeartbeatIfRequired()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->once())
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('CONNECT', array(
                    'accept-version' => '1.1',
                    'host'           => 'localhost',
                    'heart-beat'     => '200,300',
                ))
            ));

        $options = array('vhost' => 'localhost', 'heartbeat-guarantee' => 200, 'heartbeat-expect' => 300);

        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, $options);
        $client->connect();
    }

    /** @test */
    public function sendShouldSend()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('SEND', array('destination' => '/foo', 'content-length' => '5', 'content-type' => 'text/plain'), 'hello')
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->send('/foo', 'hello');
    }

    /** @test */
    public function sendHeartbeatShouldSendHeartbeat()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new HeartbeatFrame()
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->sendHeartbeat();
    }

    /**
     * @test
     * @dataProvider provideAvailableAckMethods
     */
    public function subscribeWithAckMustIncludeAValidAckMethod($ack)
    {
        $callback = $this->createCallableMock();

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('SUBSCRIBE', array('id' => 0, 'destination' => '/foo', 'ack' => $ack))
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->subscribeWithAck('/foo', $ack, $callback);
    }

    public function provideAvailableAckMethods()
    {
        return array(
            array('client'),
            array('client-individual'),
        );
    }

    /** @test */
    public function subscribeHasUniqueIdHeader()
    {
        $callback = $this->createCallableMock();

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameHasHeader('id', 0));

        $output
            ->expects($this->at(3))
            ->method('sendFrame')
            ->with($this->frameHasHeader('id', 1));

        $client = $this->getConnectedClient($input, $output);
        $client->subscribe('/foo', $callback);
        $client->subscribe('/bar', $callback);
    }

    /** @test */
    public function subscribeCanEmbedCustomHeader()
    {
        $callback = $this->createCallableMock();

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('SUBSCRIBE', array('foo' => 'bar', 'id' => 0, 'destination' => '/foo', 'ack' => 'auto'))
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->subscribe('/foo', $callback, array('foo' => 'bar'));
    }

    /**
     * @test
     * @expectedException \LogicException
     */
    public function subscribeWithAckDoesNotWorkWithAutoAckMode()
    {
        $callback = $this->createCallableMock();

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getConnectedClient($input, $output);
        $client->subscribeWithAck('/foo', 'auto', $callback);
    }

    /**
     * @test
     * @dataProvider provideAcknowledgeableAckModes
     */
    public function subscribeWithAckCallbackShouldHaveAckResolverArgument($ack)
    {
        $capturedResolver = null;

        $callback = $this->createCallableMock();
        $callback
            ->expects($this->exactly(1))
            ->method('__invoke')
            ->will($this->returnCallback(function ($frame, $resolver) use (&$capturedResolver) {
                $capturedResolver = $resolver;
            }));

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getConnectedClient($input, $output);
        $subscriptionId = $client->subscribeWithAck('/foo', $ack, $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 42, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $this->assertInstanceOf('React\Stomp\AckResolver', $capturedResolver);
    }

    public function provideAcknowledgeableAckModes()
    {
        return array(
            array('client'),
            array('client-individual'),
        );
    }

    /** @test */
    public function acknowledgeWithAckResolverArgumentShouldSendAckFrame()
    {
        $capturedResolver = null;

        $callback = $this->createCallableMock();
        $callback
            ->expects($this->exactly(1))
            ->method('__invoke')
            ->will($this->returnCallback(function ($frame, $resolver) use (&$capturedResolver) {
                $capturedResolver = $resolver;
            }));

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $output
            ->expects($this->at(3))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('ACK', array('subscription' => 0, 'message-id' => 54321))
            ));

        $client = $this->getConnectedClient($input, $output);
        $subscriptionId = $client->subscribeWithAck('/foo', 'client', $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 54321, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $capturedResolver->ack();
    }

    /** @test */
    public function negativeAcknowledgeWithAckResolverArgumentShouldSendNackFrame()
    {
        $capturedResolver = null;

        $callback = $this->createCallableMock();
        $callback
            ->expects($this->exactly(1))
            ->method('__invoke')
            ->will($this->returnCallback(function ($frame, $resolver) use (&$capturedResolver) {
                $capturedResolver = $resolver;
            }));

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $output
            ->expects($this->at(3))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('NACK', array('subscription' => 0, 'message-id' => 54321))
            ));

        $client = $this->getConnectedClient($input, $output);
        $subscriptionId = $client->subscribeWithAck('/foo', 'client', $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 54321, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $capturedResolver->nack();
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

        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getConnectedClient($input, $output);
        $subscriptionId = $client->subscribe('/foo', $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 42, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 43, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $this->assertInstanceOf('React\Stomp\Protocol\Frame', $capturedFrame);
        $this->assertFrameEquals($responseFrame, $capturedFrame);

        return array($input, $output, $client, $capturedFrame);
    }

    /**
    * @test
    * @depends messagesShouldGetRoutedToSubscriptions
    */
    public function callbackShouldNotBeCalledAfterUnsubscribe($data)
    {
        list($input, $output, $client, $capturedFrame) = $data;

        $client->unsubscribe($capturedFrame->getHeader('subscription'));

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => 0, 'message-id' => 42, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));
    }

    /** @test */
    public function ackShouldSendAckFrame()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('ACK', array('subscription' => 12345, 'message-id' => 54321))
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->ack(12345, 54321);
    }

    /** @test */
    public function ackShouldSendAckFrameWithCustomHeaders()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('ACK', array('foo' => 'bar', 'subscription' => 12345, 'message-id' => 54321))
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->ack(12345, 54321, array('foo' => 'bar'));
    }

    /** @test */
    public function nackShouldSendNackFrame()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('NACK', array('subscription' => 12345, 'message-id' => 54321))
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->nack(12345, 54321);
    }

    /** @test */
    public function nackShouldSendNackFrameWithCustomHeaders()
    {
        $input = $this->createInputStreamMock();

        $output = $this->createOutputStreamMock();
        $output
            ->expects($this->at(2))
            ->method('sendFrame')
            ->with($this->frameIsEqual(
                new Frame('NACK', array('foo' => 'bar', 'subscription' => 12345, 'message-id' => 54321))
            ));

        $client = $this->getConnectedClient($input, $output);
        $client->nack(12345, 54321, array('foo' => 'bar'));
    }

    /** @test */
    public function disconnectShouldGracefullyDisconnect()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getMockBuilder('React\Stomp\Client')
            ->setConstructorArgs(array($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost')))
            ->setMethods(array('generateReceiptId'))
            ->getMock();
        $client
            ->expects($this->once())
            ->method('generateReceiptId')
            ->will($this->returnValue('1234'));
        $input->emit('frame', array(new Frame('CONNECTED')));
        $client->disconnect();

        $output
            ->expects($this->once())
            ->method('close');

        $input->emit('frame', array(new Frame('RECEIPT', array('receipt-id' => '1234'))));
    }

    /** @test */
    public function processingErrorShouldResultInClientError()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getConnectedClient($input, $output);
        $client->on('error', $this->expectCallableOnce());

        $input->emit('frame', array(new Frame('ERROR', array('message' => 'whoops'))));
    }

    /** @test */
    public function inputErrorShouldResultInClientError()
    {
        $input = $this->createInputStreamMock();
        $output = $this->createOutputStreamMock();

        $client = $this->getConnectedClient($input, $output);
        $client->on('error', $this->expectCallableOnce());

        $e = new \Exception('Input had a problem.');
        $input->emit('error', array($e));
    }

    /** @test */
    public function sendingAFrameShouldIncreaseHeartbeatLatestSentFrame()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());

        $client = $this->getConnectedClient($input, $output);
        $heartbeat = $this->createHeartbeat($client, $this->createEventLoopInterfaceMock(), $input, $output);

        $timeRef = $heartbeat->lastSentFrame;
        $client->send('/foo', 'hello');
        $this->assertGreaterThan($timeRef, $heartbeat->lastSentFrame);
    }

    /** @test */
    public function sendingAHearbeatFrameShouldIncreaseHeartbeatLatestSentFrame()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());

        $client = $this->getConnectedClient($input, $output);
        $heartbeat = $this->createHeartbeat($client, $this->createEventLoopInterfaceMock(), $input, $output);

        $timeRef = $heartbeat->lastSentFrame;
        $client->sendHeartbeat();
        $this->assertGreaterThan($timeRef, $heartbeat->lastSentFrame);
    }

    /** @test */
    public function receivingAFrameShouldIncreaseHeartbeatLatestReceivedFrame()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());

        $client = $this->getConnectedClient($input, $output);
        $heartbeat = $this->createHeartbeat($client, $this->createEventLoopInterfaceMock(), $input, $output);

        $frame = $this->getMockBuilder('React\Stomp\Protocol\Frame')
            ->getMock();

        $timeRef = $heartbeat->lastReceivedFrame;
        $input->emit('frame', array($frame));
        $this->assertGreaterThan($timeRef, $heartbeat->lastReceivedFrame);
    }

    /** @test */
    public function receivingAHeartbeatFrameShouldIncreaseHeartbeatLatestReceivedFrame()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());

        $client = $this->getConnectedClient($input, $output);
        $heartbeat = $this->createHeartbeat($client, $this->createEventLoopInterfaceMock(), $input, $output);

        $timeRef = $heartbeat->lastReceivedFrame;
        $input->emit('frame', array(new HeartbeatFrame()));
        $this->assertGreaterThan($timeRef, $heartbeat->lastReceivedFrame);
    }

    /** @test */
    public function enablingHeartbeatWillPeriodicallySendHeartbeatFrames()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());

        $collector = array();

        $output->on('data', function($data) use (&$collector) {
            if($data === "\x0A") {
                $collector[] = $data;
            }
        });

        $loop = EventLoopFactory::create();

        $client = new Client($loop, $input, $output, array('vhost' => 'localhost', 'heartbeat-guarantee' => 100, 'heartbeat-expect' => 100));
        $client->connect();
        $input->emit('frame', array(new Frame('CONNECTED', array('heart-beat'=>'0,100'))));

        $loop->addPeriodicTimer(0.4, function() use ($loop) {
            $loop->stop();
        });

        $loop->run();

        $this->assertGreaterThanOrEqual(3, count($collector));
    }

    /** @test */
    public function shouldEmitErrorIfHeartbeatEnabledAndNoFrameReceived()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());
        $loop = EventLoopFactory::create();
        $client = new Client($loop, $input, $output, array('vhost' => 'localhost', 'heartbeat-guarantee' => 100, 'heartbeat-expect' => 100));
        $client->connect();

        $collector = array();
        $client->on('error', function($data) use (&$collector) {
            if($data instanceof \RuntimeException) {
                $collector[] = $data;
            }
        });

        $input->emit('frame', array(new Frame('CONNECTED', array('heart-beat'=>'100,0'))));

        $loop->addPeriodicTimer(0.4, function() use ($loop) {
            $loop->stop();
        });

        $loop->run();

        $this->assertGreaterThanOrEqual(3, count($collector));
    }

    /** @test */
    public function shouldStopHeartbeatEmittingWhenDisconnected()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());

        $collector = array();

        $output->on('data', function($data) use (&$collector) {
            if($data === "\x0A") {
                $collector[] = $data;
            }
        });

        $loop = EventLoopFactory::create();

        $client = new Client($loop, $input, $output, array('vhost' => 'localhost', 'heartbeat-guarantee' => 100, 'heartbeat-expect' => 100));
        $client->connect();
        $input->emit('frame', array(new Frame('CONNECTED', array('heart-beat'=>'0,100'))));
        $client->disconnect();

        $loop->addPeriodicTimer(0.4, function() use ($loop) {
            $loop->stop();
        });

        $loop->run();

        $this->assertEquals(0, count($collector));
    }

    /** @test */
    public function shouldNotEmitErrorIfHeartbeatFramesReceived()
    {
        $input = $this->createInputStreamMock();
        $output = new OutputStream($this->createEventLoopInterfaceMock());
        $loop = EventLoopFactory::create();
        $client = new Client($loop, $input, $output, array('vhost' => 'localhost', 'heartbeat-guarantee' => 100, 'heartbeat-expect' => 100));

        $collector = array();
        $client->on('error', function($data) use (&$collector) {
            if($data instanceof \RuntimeException) {
                $collector[] = $data;
            }
        });

        $input->emit('frame', array(new Frame('CONNECTED', array('heart-beat'=>'100,0'))));

        $loop->addPeriodicTimer(0.05, function() use ($loop, $input) {
            $input->emit('frame', array(new HeartbeatFrame()));
        });
        $loop->addPeriodicTimer(0.4, function() use ($loop) {
            $loop->stop();
        });

        $loop->run();

        $this->assertCount(0, $collector);
    }

    private function getConnectedClient(InputStreamInterface $input, OutputStreamInterface $output)
    {
        $client = new Client($this->createEventLoopInterfaceMock(), $input, $output, array('vhost' => 'localhost'));
        $client->connect();
        $input->emit('frame', array(new Frame('CONNECTED')));

        return $client;
    }

    private function createInputStreamMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\InputStream')
            ->setConstructorArgs(array($this->getMock('React\Stomp\Protocol\Parser')))
            ->setMethods(array('isWritable', 'write', 'end', 'close'))
            ->getMock();
    }

    private function createOutputStreamMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\OutputStream')
            ->setConstructorArgs(array($this->createEventLoopInterfaceMock()))
            ->getMock();
    }

    private function createEventLoopInterfaceMock()
    {
        return $this->getMockBuilder('React\EventLoop\LoopInterface')
            ->getMock();
    }

    private function createHeartbeat($client, $loop, $input, $output)
    {
        return new Heartbeat($client, $loop, $input, $output);
    }
}
