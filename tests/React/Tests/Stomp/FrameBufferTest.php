<?php

namespace React\Tests\Stomp;

use React\Stomp\FrameBuffer;
use React\Stomp\Protocol\Frame;

class FrameBufferTest extends TestCase
{
    /** @test */
    public function incompleteBufferPullsNoFrames()
    {
        $frameBuffer = new FrameBuffer;
        $frame = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'localhost'));
        $incompleteFrame = str_replace("\x00", '', $frame);

        $frameBuffer->addToBuffer($incompleteFrame);

        $this->assertEquals(0, count($frameBuffer->pullFrames()));
    }

    /** @test */
    public function onlyCompleteFramesArePulled()
    {
        $frameBuffer = new FrameBuffer;
        $frame = new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'localhost'));
        $incompleteFrame = str_replace("\x00", '', $frame);

        $frameBuffer->addToBuffer((string) $frame);
        $frameBuffer->addToBuffer($incompleteFrame);

        $this->assertEquals(1, count($frameBuffer->pullFrames()));
        $this->assertEquals(0, count($frameBuffer->pullFrames()));
        $frameBuffer->addToBuffer("\x00");
        $this->assertEquals(1, count($frameBuffer->pullFrames()));
        $this->assertEquals(0, count($frameBuffer->pullFrames()));
    }
}
