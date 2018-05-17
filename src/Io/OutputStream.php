<?php

namespace React\Stomp\Io;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stomp\Protocol\Frame;
use React\Stream\Util;
use React\Stream\WritableStreamInterface;

// $output = new OutputStream();
// $output->pipe($conn);
// $output->sendFrame($frame);

class OutputStream extends EventEmitter implements OutputStreamInterface
{
    private $loop;
    private $paused = false;
    private $bufferedFrames = array();
    protected $closed = false;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }
    
    public function isReadable()
    {
        return !$this->closed;
    }
    
    public function pipe(WritableStreamInterface $dest, array $options = array())
    {
        Util::pipe($this, $dest, $options);
        return $dest;
    }
    public function close()
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->emit('end', array($this));
        $this->emit('close', array($this));
        $this->removeAllListeners();
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
