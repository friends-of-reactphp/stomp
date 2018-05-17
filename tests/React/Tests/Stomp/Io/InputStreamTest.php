<?php

namespace React\Tests\Stomp;

use React\Stomp\Io\InputStream;
use React\Stomp\Protocol\Frame;
use React\Stomp\Protocol\Parser;
use React\Tests\Stomp\Constraint\FrameIsEqual;
use React\Tests\Stomp\TestCase;

class InputStreamTest extends TestCase
{
    /** @test */
    public function streamShouldBeWritableByDefault()
    {
        $input = new InputStream(new Parser());

        $this->assertTrue($input->isWritable());
    }

    /** @test */
    public function incompleteWriteShouldNotEmitFrame()
    {
        $input = new InputStream(new Parser());
        $input->on('frame', $this->expectCallableNever());

        $input->write("FOO\n\n");
    }

    /** @test */
    public function singleFrameWriteShouldEmitExactlyOneFrame()
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->once())
            ->method('__invoke')
            ->with($this->frameIsEqual(new Frame('CONNECTED')));

        $input = new InputStream(new Parser());
        $input->on('frame', $callback);

        $input->write("CONNECTED\n\n\x00");
    }

    /** @test */
    public function manySegmentedFramesWrittenShouldEmitThoseFrames()
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->at(0))
            ->method('__invoke')
            ->with($this->frameIsEqual(new Frame('CONNECTED')));
        $callback
            ->expects($this->at(1))
            ->method('__invoke')
            ->with($this->frameIsEqual(new Frame('MESSAGE', array(), 'Body')));

        $input = new InputStream(new Parser());
        $input->on('frame', $callback);

        $input->write("CONNECTED\n\n");
        $input->write("\x00");
        $input->write("MESSAGE\n\n");
        $input->write("Body\x00");
        $input->write("MESSAGE");
    }

    /** @test */
    public function endShouldWriteGivenDataThenClose()
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->at(0))
            ->method('__invoke')
            ->with($this->frameIsEqual(new Frame('CONNECTED')));

        $input = new InputStream(new Parser());
        $input->on('frame', $callback);
        $input->on('close', $this->expectCallableOnce());

        $input->write("CONNECTED\n\n");
        $input->end("\x00");

        $this->assertFalse($input->isWritable());
    }

    /** @test */
    public function closeShouldClose()
    {
        $input = new InputStream(new Parser());
        $input->on('frame', $this->expectCallableNever());
        $input->on('close', $this->expectCallableOnce());

        $input->close();

        $this->assertFalse($input->isWritable());
    }

    /** @test */
    public function writingAfterCloseShouldDoNothing()
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->never())
            ->method('__invoke');
        
        $input = new InputStream(new Parser());
        $input->on('frame', $callback);
        $input->close();

        $input->write('whoops');
    }
}
