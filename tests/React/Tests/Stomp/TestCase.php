<?php

namespace React\Tests\Stomp;

use React\Stomp\Protocol\Frame;
use React\Tests\Stomp\Constraint\FrameIsEqual;
use React\Tests\Stomp\Constraint\FrameHasHeader;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function assertFrameEquals(Frame $expected, Frame $frame)
    {
        $this->assertSame((string) $expected, (string) $frame);
    }

    protected function frameIsEqual(Frame $frame)
    {
        return new FrameIsEqual($frame);
    }

    protected function frameHasHeader($name, $value)
    {
        return new FrameHasHeader($name, $value);
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
        return $this->getMock('React\Tests\Stomp\Stub\CallableStub');
    }
}
