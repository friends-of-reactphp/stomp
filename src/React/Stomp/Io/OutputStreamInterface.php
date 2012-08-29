<?php

namespace React\Stomp\Io;

use React\Stomp\Protocol\Frame;

interface OutputStreamInterface
{
    public function sendFrame(Frame $frame);
    public function close();
}
