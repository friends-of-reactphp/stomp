<?php

namespace React\Stomp\Protocol;

interface FrameInterface
{
    public function dump();
    public function __toString();
}
