<?php

namespace React\Tests\Stomp;

use React\Stomp\Protocol\FrameInterface;
use React\Tests\Stomp\Constraint\FrameIsEqual;
use React\Tests\Stomp\Constraint\FrameHasHeader;

class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function assertFrameEquals(FrameInterface $expected, FrameInterface $frame)
    {
        $this->assertSame((string) $expected, (string) $frame);
    }

    protected function frameIsEqual(FrameInterface $frame)
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
