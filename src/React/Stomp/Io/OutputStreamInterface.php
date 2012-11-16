<?php

namespace React\Stomp\Io;

use React\Stomp\Protocol\FrameInterface;

interface OutputStreamInterface
{
    public function sendFrame(FrameInterface $frame);
    public function close();
}
