<?php

namespace React\Stomp\Exception;

use React\Stomp\Protocol\Frame;

class ServerErrorException extends ProcessingException
{
    public function __construct(Frame $frame)
    {
        parent::__construct($frame, sprintf('%s (%s)', $frame->getHeader('message'), trim($frame->body)));
    }
}
