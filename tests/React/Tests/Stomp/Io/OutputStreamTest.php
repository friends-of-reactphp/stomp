<?php

namespace React\Tests\Stomp;

use React\Stomp\Io\OutputStream;
use React\Stomp\Protocol\Frame;

class OutputStreamTest extends TestCase
{
    public function setUp(): void
    {
        $this->loop = $this->getMockBuilder('React\EventLoop\LoopInterface')->getMock();
        $this->loop
            ->expects($this->any())
            ->method('addTimer')
            ->will($this->returnCallback(function ($seconds, $callback) {
                call_user_func($callback);
            }));
    }

    /** @test */
    public function itShouldBeReadableByDefault()
    {
        $output = new OutputStream($this->loop);

        $this->assertTrue($output->isReadable());
    }

    /** @test */
    public function sendFrameShouldDumpAndEmitFrameData()
    {
        $frame = new Frame('CONNECT');

        $callback = $this->createCallableMock();
        $callback
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->frameIsEqual($frame));

        $output = new OutputStream($this->loop);
        $output->on('data', $callback);
        $output->sendFrame($frame);
    }

    /** @test */
    public function pausedStreamShouldQueueFrames()
    {
        $frame = new Frame('CONNECT');

        $output = new OutputStream($this->loop);
        $output->pause();

        $output->on('data', $this->expectCallableNever());
        $output->sendFrame($frame);
        $output->removeAllListeners();

        $output->on('data', $this->expectCallableOnce());
        $output->resume();
    }

    /** @test */
    public function closeShouldMakeStreamUnreadable()
    {
        $output = new OutputStream($this->loop);
        $output->close();

        $this->assertFalse($output->isReadable());
    }
}
