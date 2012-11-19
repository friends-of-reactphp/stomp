<?php

namespace React\Stomp\Io;

use React\EventLoop\LoopInterface;
use React\Stomp\Protocol\Frame;
use React\Stream\ReadableStream;

// $output = new OutputStream();
// $output->pipe($conn);
// $output->sendFrame($frame);

class OutputStream extends ReadableStream implements OutputStreamInterface
{
    private $loop;
    private $paused = false;
    private $bufferedFrames = array();

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function sendFrame(Frame $frame)
    {
        if ($this->paused) {
            $this->bufferedFrames[] = $frame;
            return;
        }

        $data = (string) $frame;
        $this->emit('data', array($data));
    }

    public function pause()
    {
        $this->paused = true;
    }

    public function resume()
    {
        $this->paused = false;

        $this->loop->addTimer(0.001, array($this, 'sendBufferedFrames'));
    }

    public function sendBufferedFrames()
    {
        if ($this->paused) {
            return;
        }

        while ($frame = array_shift($this->bufferedFrames)) {
            $this->sendFrame($frame);

            if ($this->paused) {
                return;
            }
        }
    }
}
