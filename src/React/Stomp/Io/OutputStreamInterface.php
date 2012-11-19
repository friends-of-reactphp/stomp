<?php

namespace React\Stomp\Io;

use Evenement\EventEmitterInterface;
use React\Stomp\Protocol\FrameInterface;

interface OutputStreamInterface extends EventEmitterInterface
{
    public function sendFrame(FrameInterface $frame);
    public function close();
}
