<?php

namespace React\Tests\Stomp\Client;

use React\Stomp\Io\InputStream;
use React\Stomp\Io\OutputStream;
use React\Stomp\Client\Heartbeat;

class HeartbeatTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function noSendingAcknowledgementIfServerDidNotAnswerd()
    {
        $heartbeat = $this->createHeartBeat();
        $heartbeat->getSendingAcknowledgement();
    }

    /**
     * @test
     * @expectedException \RuntimeException
     */
    public function noReceptionAcknowledgementIfServerDidNotAnswerd()
    {
        $heartbeat = $this->createHeartBeat();
        $heartbeat->getReceptionAcknowledgement();
    }

    /**
     * @test
     * @dataProvider provideDataForReceptionAcknowledgement
     */
    public function receptionAndSendingAcknowledgementBasedOnRequestAndResponse($clientRequested, $clientProposed, $serverRequested, $serverProposed, $sendingAcknowledgmeent, $receptionAcknowledgmeent)
    {
        $heartbeat = $this->createHeartBeat();
        $heartbeat->cx = $clientRequested;
        $heartbeat->cy = $clientProposed;
        $heartbeat->sx = $serverRequested;
        $heartbeat->sy = $serverProposed;

        $this->assertEquals($sendingAcknowledgmeent, $heartbeat->getSendingAcknowledgement());
        $this->assertEquals($receptionAcknowledgmeent, $heartbeat->getReceptionAcknowledgement());
    }

    /** @test */
    public function shouldIncreaseReceivedTimestampOnFrameReception()
    {
        $input = new InputStream($this->getMock('React\Stomp\Protocol\Parser'));
        $heartbeat = new Heartbeat($this->createClientMock(), $this->createEventLoopInterfaceMock(), $input, $this->createOutputStreamMock());

        $this->assertNull($heartbeat->lastReceivedFrame);

        $input->emit('frame', array('data'));
        $this->assertInternalType('float', $heartbeat->lastReceivedFrame);

        $lastValue = $heartbeat->lastReceivedFrame;

        $input->emit('frame', array('data'));
        $this->assertGreaterThan($lastValue, $heartbeat->lastReceivedFrame);
    }

    /** @test */
    public function shouldIncreaseReceivedTimestampOnFrameSending()
    {
        $output = new OutputStream($this->createEventLoopInterfaceMock());
        $heartbeat = new Heartbeat($this->createClientMock(), $this->createEventLoopInterfaceMock(), $this->createInputStreamMock(), $output);

        $this->assertNull($heartbeat->lastSentFrame);

        $output->emit('data', array('data'));
        $this->assertInternalType('float', $heartbeat->lastSentFrame);

        $lastValue = $heartbeat->lastSentFrame;

        $output->emit('data', array('data'));
        $this->assertGreaterThan($lastValue, $heartbeat->lastSentFrame);
    }

    public function provideDataForReceptionAcknowledgement()
    {
        return array(
            array(100,   0,   0, 200, 200,   0),
            array(0,     0, 100, 200,   0,   0),
            array(100, 200,   0,   0,   0,   0),
            array(300, 300, 400, 100, 300, 400),
            array(100, 200, 300,   0,   0, 300),
            array(300, 300, 400, 100, 300, 400),
        );
    }

    private function createClientMock()
    {
        return $this->getMockBuilder('React\Stomp\Client')
                ->disableOriginalConstructor()
                ->getMock();
    }

    private function createHeartBeat()
    {
        return new Heartbeat($this->createClientMock(), $this->createEventLoopInterfaceMock(), $this->createInputStreamMock(), $this->createOutputStreamMock());
    }

    private function createInputStreamMock()
    {
        return $this->getMockBuilder('React\Stomp\Io\InputStream')
                ->setConstructorArgs(array($this->getMock('React\Stomp\Protocol\Parser')))
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
}
