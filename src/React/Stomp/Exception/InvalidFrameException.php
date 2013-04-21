<?php

namespace React\Stomp\Exception;

use React\Stomp\Protocol\Frame;

class InvalidFrameException extends ProcessingException
{
    public function __construct(Frame $frame, $message)
    {
        parent::__construct($frame, $message);
    }
}
