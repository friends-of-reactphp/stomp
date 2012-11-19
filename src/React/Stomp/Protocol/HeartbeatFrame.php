<?php

namespace React\Stomp\Protocol;

class HeartbeatFrame implements FrameInterface
{
    public function dump()
    {
        return "\x0A";
    }

    public function __toString()
    {
        return $this->dump();
    }
}
