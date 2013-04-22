<?php

namespace React\Stomp\Exception;

class FrameNotSentException extends \RuntimeException
{
    private $frame;

    public function __construct($frame, $previous = null)
    {
        parent::__construct('Unable to send frame', null, $previous);
        $this->frame = $frame;
    }

    public function getFrame()
    {
        return $this->frame;
    }
}
