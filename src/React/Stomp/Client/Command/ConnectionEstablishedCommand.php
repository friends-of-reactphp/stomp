<?php

namespace React\Stomp\Client\Command;

use React\Stomp\Protocol\Frame;

// TODO: this is weird, should be an event instead of command

class ConnectionEstablishedCommand implements CommandInterface
{
    public $frame;

    public function __construct(Frame $frame)
    {
        $this->frame = $frame;
    }
}
