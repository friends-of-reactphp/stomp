<?php

namespace React\Tests\Stomp\Client;

use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Client\State;
use React\Stomp\Protocol\Frame;
use React\Tests\Stomp\Constraint\FrameEquals;

class IncomingPackageProcessorTest extends \PHPUnit_Framework_TestCase
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

    /** @test */
    public function receiveFrameShouldReturnNoCommandsForMessageFrame()
    {
        $state = new State();
        $packageProcessor = new IncomingPackageProcessor($state);

        $frame = new Frame('MESSAGE', array('message' => 'whoops'));
        $command = $packageProcessor->receiveFrame($frame);

        $this->assertInstanceOf('React\Stomp\Client\Command\NullCommand', $command);
    }

    /** @test */
    public function receiveConnectedFrameShouldReturnConnectionEstablishedCommand()
    {
        $state = new State();
        $state->startConnecting();

        $packageProcessor = new IncomingPackageProcessor($state);

        $frame = new Frame('CONNECTED', array(
            'session' => 'session-ServerRandom',
            'server'  => 'Neutronicus'
        ));
        $command = $packageProcessor->receiveFrame($frame);

        $this->assertEquals('Neutronicus', $state->server);
        $this->assertEquals('session-ServerRandom', $state->session);

        $this->assertInstanceOf('React\Stomp\Client\Command\ConnectionEstablishedCommand', $command);
        $this->assertEquals('0,0', $command->heartbeatServerSettings);
    }

    /** @test */
    public function receiveConnectedFrameShouldReturnConnectionEstablishedCommandWithServerSettings()
    {
        $state = new State();
        $state->startConnecting();

        $packageProcessor = new IncomingPackageProcessor($state);

        $frame = new Frame('CONNECTED', array(
            'session'    => 'session-ServerRandom',
            'server'     => 'Neutronicus',
            'heart-beat' => '1000,500'
        ));
        $command = $packageProcessor->receiveFrame($frame);

        $this->assertEquals('Neutronicus', $state->server);
        $this->assertEquals('session-ServerRandom', $state->session);

        $this->assertInstanceOf('React\Stomp\Client\Command\ConnectionEstablishedCommand', $command);
        $this->assertEquals('1000,500', $command->heartbeatServerSettings);
    }
}
