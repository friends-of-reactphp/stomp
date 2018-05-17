<?php

namespace React\Tests\Stomp;

use PHPUnit\Framework\TestCase as PHPUnitCase;
use React\Stomp\Protocol\Frame;
use React\Tests\Stomp\Constraint\FrameIsEqual;
use React\Tests\Stomp\Constraint\FrameHasHeader;

class TestCase extends PHPUnitCase
{
    protected function assertFrameEquals(Frame $expected, Frame $frame)
    {
        $this->assertSame((string) $expected, (string) $frame);
    }
    
    protected function assertFrameNotEquals(Frame $expected, Frame $frame)
    {
        $this->assertNotSame((string) $expected, (string) $frame);
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
        return $this->createMock('React\Tests\Stomp\Stub\CallableStub');
    }
}
