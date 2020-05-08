<?php

namespace React\Tests\Stomp;

use PHPUnit\Framework\TestCase as PHPUnitCase;
use React\Stomp\Protocol\Frame;

class TestCase extends PHPUnitCase
{
    protected function assertFrameEquals(Frame $expected, Frame $frame)
    {
        $this->assertSame((string) $expected, (string) $frame);
    }

    protected function frameIsEqual(Frame $frame)
    {
        return $this->equalTo($frame);
    }

    protected function frameHasHeader($name, $value)
    {
        return $this->callback(function ($frame) use ($name, $value) {
            $this->assertInstanceOf('React\Stomp\Protocol\Frame', $frame);
            $this->assertEquals($value, $frame->getHeader($name));

            return true;
        });
    }

    protected function expectCallableOnce()
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->once())
            ->method('__invoke');

        return $callback;
    }

    protected function expectCallableNever()
    {
        $callback = $this->createCallableMock();
        $callback
            ->expects($this->never())
            ->method('__invoke');

        return $callback;
    }

    protected function createCallableMock()
    {
        return $this->getMockBuilder('stdClass')->setMethods(array('__invoke'))->getMock();
    }
}
