<?php

namespace React\Tests\Stomp\Client;

use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Client\State;
use React\Stomp\Protocol\Frame;

class IncomingPackageProcessorTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function itShouldEmitAnErrorOnErrorFrame()
    {
        $processor = new IncomingPackageProcessor(new State());

        $n = 0;
        $caughtError = null;
        $processor->on('error', function ($error) use (&$n, &$caughtError) {
            $caughtError = $error;
            $n++;
        });

        $frame = new Frame('ERROR', array('message' => 'whoops'));
        $processor->receiveFrame($frame);

        $this->assertEquals(1, $n);
        $this->assertInstanceOf('React\Stomp\Exception\ServerErrorException', $caughtError);
        $this->assertEquals('whoops ()', $caughtError->getMessage());
        $this->assertEquals($frame, $caughtError->getErrorFrame());
    }

    /**
     * @test
     * @expectedException React\Stomp\Exception\InvalidFrameException
     * @expectedExceptionMessage Received frame with command 'MESSAGE', expected 'CONNECTED'.
     */
    public function itCanOnlyReceiveAConnectedFrameWhenConnecting()
    {
        $state = new State(State::STATUS_CONNECTING);

        $processor = new IncomingPackageProcessor($state);
        $processor->receiveFrame(new Frame('MESSAGE'));
    }

    /** @test */
    public function itShouldAcknowledgeConnectionWhenConnectingOnConnectFrame()
    {
        $state = new State(State::STATUS_CONNECTING);

        $frame = new Frame('CONNECTED', array('session' => '12345', 'server' => 'react/stomp-server'));
        $processor = new IncomingPackageProcessor($state);

        $n = 0;
        $caughtFrame = null;
        $processor->on('connected', function ($frame) use (&$n, &$caughtFrame) {
            $caughtFrame = $frame;
            $n++;
        });

        $processor->receiveFrame($frame);

        $this->assertEquals(1, $n);
        $this->assertEquals($caughtFrame, $frame);

        $this->assertEquals('react/stomp-server', $state->server);
        $this->assertEquals('12345', $state->session);
    }

    /**
     * @test
     * @dataProvider provideNotConnectingStatus
     * @expectedException React\Stomp\Exception\InvalidFrameException
     * @expectedExceptionMessage Received 'CONNECTED' frame outside a connecting window.
     */
    public function itShouldThrowAnExceptionWhenNotConnectingOnConnectedFrame($status)
    {
        $state = new State($status);

        $frame = new Frame('CONNECTED', array('session' => '12345', 'server' => 'react/stomp-server'));
        $processor = new IncomingPackageProcessor($state);

        $processor->receiveFrame($frame);
    }

    /**
     * @dataProvider provideMessageForwardingStatuses
     * @test
     */
    public function itShouldForwardMessageFramesWhenConnected($status)
    {
        $state = new State($status);

        $processor = new IncomingPackageProcessor($state);
        $caughtFrames = $sentFrames = array();
        $n = mt_rand(4, 8);

        $processor->on('frame', function ($frame) use (&$caughtFrames) {
            $caughtFrames[] = $frame;
        });

        for ($i = 0; $i < $n; $i++) {
            $processor->receiveFrame($sentFrames[] = new Frame('MESSAGE'));
        }

        $this->assertCount($n, $caughtFrames);
        $this->assertEquals($sentFrames, $caughtFrames);
    }

    /** @test */
    public function itShouldDisconnectOnReceiptWhenDisconnecting()
    {

    }

    /** @test */
    public function itShouldEmitErrorsOnMessageFramesWhenNotConnected()
    {
        $state = new State(State::STATUS_DISCONNECTED);

        $processor = new IncomingPackageProcessor($state);
        $caughtFrames = $sentFrames = array();
        $n = mt_rand(4, 8);

        $processor->on('error', function ($error) use (&$caughtFrames) {
            $this->assertInstanceOf('React\Stomp\Exception\InvalidFrameException', $error);
            $caughtFrames[] = $error->getErrorFrame();
        });

        for ($i = 0; $i < $n; $i++) {
            $processor->receiveFrame($sentFrames[] = new Frame('MESSAGE'));
        }

        $this->assertCount($n, $caughtFrames);
        $this->assertEquals($sentFrames, $caughtFrames);
    }

    public function provideMessageForwardingStatuses()
    {
        return array(
            array(State::STATUS_CONNECTED),
            array(State::STATUS_DISCONNECTING),
        );
    }

    public function provideNotConnectingStatus()
    {
        return array(
            array(State::STATUS_CONNECTED),
            array(State::STATUS_DISCONNECTED),
            array(State::STATUS_DISCONNECTING),
        );
    }
}
