<?php

namespace React\Tests\Stomp\Client;

use React\Stomp\Client\Interactor;
use React\Stomp\Client\State;
use React\Stomp\Protocol\Frame;
use React\Tests\Stomp\Constraint\FrameEquals;

class InteractorTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function connectShouldEmitConnectFrame()
    {
        $expectedFrame = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'stomp.github.org'));

        $state = new State();
        $interactor = new Interactor($state);

        $this->assertSame(State::STATUS_INIT, $state->status);

        $frame = $interactor->connect('stomp.github.org');

        $this->assertFrameEquals($expectedFrame, $frame);
        $this->assertSame(State::STATUS_CONNECTING, $state->status);
    }

    /** @test */
    public function connectShouldSetLoginAndPasscodeHeadersIfGiven()
    {
        $expectedFrame = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'stomp.github.org', 'login' => 'foo', 'passcode' => 'bar'));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->connect('stomp.github.org', 'foo', 'bar');

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function sendShouldEmitSendFrame()
    {
        $expectedFrame = new Frame('SEND', array('destination' => '/queue/a', 'content-length' => '13', 'content-type' => 'text/plain'), 'hello queue a');

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->send('/queue/a', 'hello queue a');

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function subscribeShouldEmitSubscribeFrame()
    {
        $expectedFrame = new Frame('SUBSCRIBE', array('id' => 0, 'destination' => '/queue/a'));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->subscribe('/queue/a');

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function subscribeShouldIncrementId()
    {

        $state = new State();
        $interactor = new Interactor($state);

        $expectedFrame = new Frame('SUBSCRIBE', array('id' => 0, 'destination' => '/queue/a'));
        $frame = $interactor->subscribe('/queue/a');
        $this->assertFrameEquals($expectedFrame, $frame);

        $expectedFrame = new Frame('SUBSCRIBE', array('id' => 1, 'destination' => '/queue/a'));
        $frame = $interactor->subscribe('/queue/a');
        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function unsubscribeShouldEmitUnsubscribeFrame()
    {
        $expectedFrame = new Frame('UNSUBSCRIBE', array('id' => 0));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->unsubscribe(0);

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function ackShouldEmitAckFrame()
    {
        $expectedFrame = new Frame('ACK', array('subscription' => 0, 'message-id' => 5));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->ack(0, 5);

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function nackShouldEmitNackFrame()
    {
        $expectedFrame = new Frame('NACK', array('subscription' => 0, 'message-id' => 5));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->nack(0, 5);

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function beginShouldEmitBeginFrame()
    {
        $expectedFrame = new Frame('BEGIN', array('transaction' => 'tx1'));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->begin('tx1');

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function commitShouldEmitCommitFrame()
    {
        $expectedFrame = new Frame('COMMIT', array('transaction' => 'tx1'));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->commit('tx1');

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function abortShouldEmitAbortFrame()
    {
        $expectedFrame = new Frame('ABORT', array('transaction' => 'tx1'));

        $state = new State();
        $interactor = new Interactor($state);

        $frame = $interactor->abort('tx1');

        $this->assertFrameEquals($expectedFrame, $frame);
    }

    /** @test */
    public function disconnectShouldEmitDisconnectFrame()
    {
        $expectedFrame = new Frame('DISCONNECT', array('receipt' => 'foo'));

        $state = new State();
        $interactor = new Interactor($state);

        $this->assertSame(State::STATUS_INIT, $state->status);

        $frame = $interactor->disconnect('foo');

        $this->assertFrameEquals($expectedFrame, $frame);
        $this->assertSame(State::STATUS_DISCONNECTING, $state->status);
    }

    /**
     * @test
     * @expectedException React\Stomp\Client\ServerErrorException
     * @expectedExceptionMessage whoops
     */
    public function receiveFrameShouldConvertErrorFrameToServerErrorException()
    {
        $state = new State();
        $interactor = new Interactor($state);

        $frame = new Frame('ERROR', array('message' => 'whoops'));
        $command = $interactor->receiveFrame($frame);
    }

    /** @test */
    public function receiveFrameShouldReturnNoCommandsForMessageFrame()
    {
        $state = new State();
        $interactor = new Interactor($state);

        $frame = new Frame('MESSAGE', array('message' => 'whoops'));
        $command = $interactor->receiveFrame($frame);

        $this->assertInstanceOf('React\Stomp\Client\Command\NullCommand', $command);
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
