<?php

namespace React\Stomp\Exception;

use React\Stomp\Protocol\Frame;

class ProcessingException extends \Exception
{
    private $frame;

    public function __construct(Frame $frame, $message = null, $previous = null)
    {
        parent::__construct($message, null, $previous);

        $this->frame = $frame;
    }

    public function getErrorFrame()
    {
        return $this->frame;
    }
}
