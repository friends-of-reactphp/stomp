<?php

namespace React\Stomp\Io;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stomp\Protocol\FrameInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stream\Util;

// $output = new OutputStream();
// $output->pipe($conn);
// $output->sendFrame($frame);

class OutputStream extends EventEmitter implements OutputStreamInterface, ReadableStreamInterface
{
    private $loop;

    private $readable = true;
    private $paused = false;
    private $bufferedFrames = array();

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function sendFrame(FrameInterface $frame)
    {
        if ($this->paused) {
            $this->bufferedFrames[] = $frame;
            return;
        }

        $data = (string) $frame;
        $this->emit('data', array($data));
    }

    public function isReadable()
    {
        return $this->readable;
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

    public function close()
    {
        $this->readable = false;

        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
    }

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);

        return $dest;
    }
}
