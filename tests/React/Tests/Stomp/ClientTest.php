<?php

namespace React\Tests\Stomp;

use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use React\EventLoop\LoopInterface;
use React\Stomp\Io\InputStreamInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;

class ClientTest extends TestCase
{
    /** @test */
    public function connectShouldSendConnectFrame()
    {
        $emitted = false;
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'localhost'));
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $client = new Client($this->createLoopMock(), $input, $output, array('vhost' => 'localhost'));
        $client->connect();

        $this->assertTrue($emitted);
    }

    /** @test */
    public function connectShouldReturnPromise()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMock(), $input, $output, array('vhost' => 'localhost'));
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
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $client = new Client($this->createLoopMock(), $input, $output, array());
    }

    /** @test */
    public function connectTwiceShouldReturnTheSamePromise()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMock(), $input, $output, array('vhost' => 'localhost'));
        $promise1 = $client->connect();
        $promise2 = $client->connect();

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise1);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise2);
        $this->assertSame($promise1, $promise2);
    }

    /** @test */
    public function disconnectThenConnectShouldReturnNewPromise()
    {
        $emitted = false;
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $output->on('connect', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'localhost'));
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $client = new Client($this->createLoopMock(), $input, $output, array('vhost' => 'localhost'));
        $promise1 = $client->connect();
        $this->assertFalse($emitted); // Deferred
        $client->disconnect();
        $promise2 = $client->connect();
        $this->assertFalse($emitted); // Deferred

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise1);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise2);
        $this->assertNotSame($promise1, $promise2);
    }

    /** @test */
    public function itShouldEmitConnectAfterHandshake()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMockWithConnectionTimer(), $input, $output, array('vhost' => 'localhost'));
        $client->on('connect', $this->expectCallableOnce());
        $client->connect();

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', [$frame]);
    }

    /** @test */
    public function itShouldRejectPromiseIfConnectionFails()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMock(), $input, $output, array('vhost' => 'localhost'));
        $client->connect()
            ->then($this->expectCallableNever(), $this->expectCallableOnce());

        $frame = new Frame('ERROR', array(), 'Invalid virtual host: /');
        $input->emit('frame', array($frame));
    }

    /** @test */
    public function itShouldRejectPromiseIfConnectionTimeout()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $loop = $this->createLoopMock();
        $timeout = 30;

        $capturedInterval = $capturedCallback = null;

        $loop->expects($this->once())
            ->method('addTimer')
            ->with($this->equalTo($timeout), $this->isType('callable'))
            ->will($this->returnCallback(function ($interval, $callback) use (&$capturedInterval, &$capturedCallback) {
                $capturedInterval = $interval;
                $capturedCallback = $callback;
            }));


        $client = new Client($loop, $input, $output, array('vhost' => 'localhost'));
        $client->connect($timeout)
            ->then($this->expectCallableNever(), $this->expectCallableOnce());

        call_user_func($capturedCallback);
    }

    /** @test */
    public function timeoutThenConnectShouldReturnANewPromise()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $loop = $this->createLoopMock();
        $timeout = 30;

        $capturedInterval = $capturedCallback = null;

        $loop->expects($this->any())
            ->method('addTimer')
            ->will($this->returnCallback(function ($interval, $callback) use (&$capturedInterval, &$capturedCallback) {
                $capturedInterval = $interval;
                $capturedCallback = $callback;
            }));

        $client = new Client($loop, $input, $output, array('vhost' => 'localhost'));
        $promise1 = $client->connect($timeout);

        call_user_func($capturedCallback);

        $promise2 = $client->connect($timeout);

        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise1);
        $this->assertInstanceOf('React\Promise\PromiseInterface', $promise2);
        $this->assertNotSame($promise1, $promise2);
    }

    /** @test */
    public function itShouldCancelTimerOnConnection()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $timeout = 30;
        $signature = uniqid('signature');

        $loop = $this->createLoopMockWithConnectionTimer();

        $client = new Client($loop, $input, $output, array('vhost' => 'localhost'));
        $client->connect($timeout)
            ->then($this->expectCallableOnce());

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', array($frame));
    }

    /** @test */
    public function itShouldNotBeConnectedAfterConstructor()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMock(), $input, $output, array('vhost' => 'localhost'));

        $this->assertFalse($client->isConnected());
    }

    /** @test */
    public function itShouldThrowAnExceptionOnConnectedFrameOutsideWindow()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $loop = $this->createLoopMock();

        $errors = array();

        $client = new Client($loop, $input, $output, array('vhost' => 'localhost'));
        $client->on('error', function ($error) use (&$errors) { $errors[] = $error; });

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', array($frame));

        $this->assertCount(1, $errors);
        $this->assertInstanceOf('React\Stomp\Exception\InvalidFrameException', $errors[0]);
        $this->assertSame("Received 'CONNECTED' frame outside a connecting window.", $errors[0]->getMessage());
    }

    /** @test */
    public function itShouldBeConnectedWhenThePromiseIsResolved()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMockWithConnectionTimer(), $input, $output, array('vhost' => 'localhost'));
        $client->connect();

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', array($frame));

        $this->assertTrue($client->isConnected());
    }

    /** @test */
    public function itShouldNotBeConnectedAfterDisconnection()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMockWithConnectionTimer(), $input, $output, array('vhost' => 'localhost'));
        $client->connect();

        $frame = new Frame('CONNECTED', array('session' => '1234', 'server' => 'React/alpha'));
        $input->emit('frame', array($frame));

        $client->disconnect();
        $this->assertFalse($client->isConnected());
    }

    /** @test */
    public function itShouldResolveConnectPromiseAfterHandshake()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = new Client($this->createLoopMockWithConnectionTimer(), $input, $output, array('vhost' => 'localhost'));
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
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getConnectedClient($input, $output);
    }

    /** @test */
    public function sendShouldSend()
    {
        $emitted = false;
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $client = $this->getConnectedClient($input, $output);
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('SEND', array('destination' => '/foo', 'content-length' => '5', 'content-type' => 'text/plain'), 'hello');
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $client->send('/foo', 'hello');

        $this->assertTrue($emitted);
    }

    /**
     * @test
     * @dataProvider provideAvailableAckMethods
     */
    public function subscribeWithAckMustIncludeAValidAckMethod($ack)
    {
        $emitted = false;
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $callback = $this->createCallableMock();
        $client = $this->getConnectedClient($input, $output);
        $output->on('data', function ($frame) use (&$emitted, $ack) {
            $emitted = true;
            $expected = new Frame('SUBSCRIBE', array('id' => 0, 'destination' => '/foo', 'ack' => $ack));
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $client->subscribeWithAck('/foo', $ack, $callback);

        $this->assertTrue($emitted);
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
        $expectedId = 0;
        $emitted = false;
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $callback = $this->createCallableMock();
        $client = $this->getConnectedClient($input, $output);
        $output->on('data', function ($frame) use (&$emitted, &$expectedId) {
            $emitted = true;
            $this->assertEquals($expectedId, $frame->getHeader('id'));
        });

        $expectedId = 0;
        $client->subscribe('/foo', $callback);
        $this->assertTrue($emitted);

        $expectedId = 1;
        $emitted = false;
        $client->subscribe('/bar', $callback);
        $this->assertTrue($emitted);
    }

    /** @test */
    public function subscribeCanEmbedCustomHeader()
    {
        $emitted = false;
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $callback = $this->createCallableMock();
        $client = $this->getConnectedClient($input, $output);
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('SUBSCRIBE', array('foo' => 'bar', 'id' => 0, 'destination' => '/foo', 'ack' => 'auto'));
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $client->subscribe('/foo', $callback, array('foo' => 'bar'));

        $this->assertTrue($emitted);
    }

    /**
     * @test
     * @expectedException \LogicException
     */
    public function subscribeWithAckDoesNotWorkWithAutoAckMode()
    {
        $callback = $this->createCallableMock();

        $input = $this->createInputStream();
        $output = $this->createOutputStream();

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

        $input = $this->createInputStream();
        $output = $this->createOutputStream();

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

        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getConnectedClient($input, $output);
        $subscriptionId = $client->subscribeWithAck('/foo', 'client', $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 54321, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $emitted = false;
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('ACK', array('subscription' => 0, 'message-id' => 54321));
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $capturedResolver->ack();
        $this->assertTrue($emitted);
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

        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getConnectedClient($input, $output);
        $subscriptionId = $client->subscribeWithAck('/foo', 'client', $callback);

        $responseFrame = new Frame(
            'MESSAGE',
            array('subscription' => $subscriptionId, 'message-id' => 54321, 'destination' => '/foo', 'content-type' => 'text/plain'),
            'this is a published message'
        );
        $input->emit('frame', array($responseFrame));

        $emitted = false;
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('NACK', array('subscription' => 0, 'message-id' => 54321));
            $this->assertEquals((string) $expected, (string) $frame);
        });

        $capturedResolver->nack();
        $this->assertTrue($emitted);
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

        $input = $this->createInputStream();
        $output = $this->createOutputStream();

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
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $client = $this->getConnectedClient($input, $output);

        $emitted = false;
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('ACK', array('subscription' => 12345, 'message-id' => 54321));
            $this->assertEquals((string) $expected, (string) $frame);
        });
        $client->ack(12345, 54321);
        $this->assertTrue($emitted);
    }

    /** @test */
    public function ackShouldSendAckFrameWithCustomHeaders()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $client = $this->getConnectedClient($input, $output);

        $emitted = false;
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('ACK', array('foo' => 'bar', 'subscription' => 12345, 'message-id' => 54321));
            $this->assertEquals((string) $expected, (string) $frame);
        });
        $client->ack(12345, 54321, array('foo' => 'bar'));
        $this->assertTrue($emitted);
    }

    /** @test */
    public function nackShouldSendNackFrame()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $client = $this->getConnectedClient($input, $output);

        $emitted = false;
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('NACK', array('subscription' => 12345, 'message-id' => 54321));
            $this->assertEquals((string) $expected, (string) $frame);
        });
        $client->nack(12345, 54321);
        $this->assertTrue($emitted);
    }

    /** @test */
    public function nackShouldSendNackFrameWithCustomHeaders()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();
        $client = $this->getConnectedClient($input, $output);

        $emitted = false;
        $output->on('data', function ($frame) use (&$emitted) {
            $emitted = true;
            $expected = new Frame('NACK', array('foo' => 'bar', 'subscription' => 12345, 'message-id' => 54321));
            $this->assertEquals((string) $expected, (string) $frame);
        });
        $client->nack(12345, 54321, array('foo' => 'bar'));
        $this->assertTrue($emitted);
    }

    /** @test */
    public function disconnectShouldGracefullyDisconnect()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getMockBuilder('React\Stomp\Client')
            ->setConstructorArgs(array($this->createLoopMock(), $input, $output, array('vhost' => 'localhost')))
            ->setMethods(array('generateReceiptId'))
            ->getMock();
        $client
            ->expects($this->once())
            ->method('generateReceiptId')
            ->will($this->returnValue('1234'));
        $input->emit('frame', array(new Frame('CONNECTED')));
        $client->disconnect();

        $emitted = false;
        $output->on('close', function () use (&$emitted) {
            $emitted = true;
        });
        $input->emit('frame', array(new Frame('RECEIPT', array('receipt-id' => '1234'))));
        $this->assertTrue($emitted);
    }

    /** @test */
    public function processingErrorShouldResultInClientError()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getConnectedClient($input, $output);
        $client->on('error', $this->expectCallableOnce());

        $input->emit('frame', array(new Frame('ERROR', array('message' => 'whoops'))));
    }

    /** @test */
    public function inputErrorShouldResultInClientError()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getConnectedClient($input, $output);
        $client->on('error', $this->expectCallableOnce());

        $e = new \Exception('Input had a problem.');
        $input->emit('error', array($e));
    }

    /** @test */
    public function inputCloseShouldResultInClientClose()
    {
        $input = $this->createInputStream();
        $output = $this->createOutputStream();

        $client = $this->getConnectedClient($input, $output);
        $client->on('close', $this->expectCallableOnce());

        $input->emit('close');
    }

    private function getConnectedClient(WritableResourceStream $input, ReadableResourceStream $output)
    {
        $client = new Client($this->createLoopMockWithConnectionTimer(), $input, $output, array('vhost' => 'localhost'));
        $client->connect();
        $input->emit('frame', array(new Frame('CONNECTED')));

        return $client;
    }

    private function createInputStreamMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\InputStream')
            ->setConstructorArgs([
                fopen('php://temp', 'r+'),
                $this->createMock('React\EventLoop\StreamSelectLoop')
            ])
            ->setMethods(array('isWritable', 'write', 'end', 'close'))
            ->getMock();
    }

    private function createLoopMock()
    {
        return $this->createMock('React\EventLoop\LoopInterface');
    }

    private function createLoopMockWithConnectionTimer()
    {
        $loop = $this->createLoopMock();

        $timer = $this->createMock('React\EventLoop\TimerInterface');

        // $timer->expects($this->once())
        //     ->method('cancel');
        $loop->expects($this->once())
            ->method('cancelTimer')
            ->with($this->equalTo($timer));

        $loop->expects($this->once())
            ->method('addTimer')
            ->will($this->returnValue($timer));

        return $loop;
    }

    private function createInputStream()
    {
        return new WritableResourceStream(fopen('php://temp', 'w+'), $this->createLoopMock());
    }

    private function createOutputStream()
    {
        return new ReadableResourceStream(fopen('php://temp', 'r+'), $this->createLoopMock());
    }
}
