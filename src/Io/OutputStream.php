<?php

namespace React\Stomp\Io;

use React\EventLoop\LoopInterface;
use React\Stomp\Protocol\Frame;
use React\Stream\ReadableStreamInterface;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;
use Evenement\EventEmitter;

// $output = new OutputStream();
// $output->pipe($conn);
// $output->sendFrame($frame);

final class OutputStream extends EventEmitter implements ReadableStreamInterface, OutputStreamInterface
{
    protected $closed = false;

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
    public function isReadable()
    {
        return !$this->closed;
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

    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        return Util::pipe($this, $dest, $options);
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('close');
        $this->removeAllListeners();
    }
}
