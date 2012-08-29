<?php

namespace React\Tests\Stomp;

use React\Stomp\Io\OutputStream;
use React\Stomp\Protocol\Frame;
use React\Tests\Stomp\Constraint\FrameIsEqual;
use React\Tests\Stomp\TestCase;

class OutputStreamTest extends TestCase
{
    /** @test */
    public function itShouldBeReadableByDefault()
    {
        $output = new OutputStream();

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

        $output = new OutputStream();
        $output->on('data', $callback);
        $output->sendFrame($frame);
    }

    /** @test */
    public function pausedStreamShouldQueueFrames()
    {
        $frame = new Frame('CONNECT');

        $output = new OutputStream();
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
        $output = new OutputStream();
        $output->close();

        $this->assertFalse($output->isReadable());
    }
}
