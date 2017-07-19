<?php

namespace React\Tests\Stomp\Client;

use PHPUnit\Framework\TestCase;
use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Client\State;
use React\Stomp\Protocol\Frame;

class IncomingPackageProcessorTest extends TestCase
{
    /**
     * @test
     * @expectedException React\Stomp\Exception\ServerErrorException
     * @expectedExceptionMessage whoops
     */
    public function receiveFrameShouldConvertErrorFrameToServerErrorException()
    {
        $state = new State();
        $packageProcessor = new IncomingPackageProcessor($state);

        $frame = new Frame('ERROR', array('message' => 'whoops'));
        $command = $packageProcessor->receiveFrame($frame);
    }

    /**
     * @test
     * @expectedException React\Stomp\Exception\InvalidFrameException
     * @expectedExceptionMessage Received frame with command 'FOO', expected 'CONNECTED'.
     */
    public function nonConnectedFrameAfterConnectingShouldResultInError()
    {
        $state = new State();
        $state->startConnecting();
        $packageProcessor = new IncomingPackageProcessor($state);

        $frame = new Frame('FOO');
        $command = $packageProcessor->receiveFrame($frame);
    }

    /** @test */
    public function receiveFrameShouldReturnNoCommandsForMessageFrame()
    {
        $state = new State();
        $packageProcessor = new IncomingPackageProcessor($state);

        $frame = new Frame('MESSAGE', array('message' => 'whoops'));
        $command = $packageProcessor->receiveFrame($frame);

        $this->assertInstanceOf('React\Stomp\Client\Command\NullCommand', $command);
    }
}
