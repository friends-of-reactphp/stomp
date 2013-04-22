<?php

namespace React\Stomp\Exception;

use React\Stomp\Protocol\Frame;

class UnexpectedFrameException extends ProcessingException
{
    public function __construct(Frame $frame, $message)
    {
        parent::__construct($frame, $message);
    }
}
