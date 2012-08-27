<?php

namespace React\Stomp\Client;

use React\Stomp\Protocol\Frame;

class ServerErrorException extends \Exception
{
    private $frame;

    public function __construct(Frame $frame)
    {
        parent::__construct(sprintf('%s (%s)', $frame->getHeader('message'), trim($frame->body)));

        $this->frame = $frame;
    }

    public function getErrorFrame()
    {
        return $this->frame;
    }
}
