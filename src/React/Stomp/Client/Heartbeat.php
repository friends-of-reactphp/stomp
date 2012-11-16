<?php

namespace React\Stomp\Client;

class Heartbeat
{
    //what the client can do
    public $cx;
    //what the client would like to get
    public $cy;
    //what the server can do
    public $sx;
    //what the server would like to get
    public $sy;

    public $lastReceivedFrame;
    public $lastSentFrame;

    public function __construct($cx = 0, $cy = 0)
    {
        $this->cx = $cx;
        $this->cy = $cy;
    }

    public function receptionAcknowledgement()
    {
        $this->throwExceptionIfNoAcknowledgement();

        if ($this->sx === 0 || $this->cy === 0) {
            return 0;
        }

        return max($this->sx, $this->cy);
    }

    public function sendAcknowledgement()
    {
        $this->throwExceptionIfNoAcknowledgement();

        if ($this->cx === 0 || $this->sy === 0) {
            return 0;
        }

        return max($this->cx, $this->sy);
    }

    private function throwExceptionIfNoAcknowledgement()
    {
        if(null === $this->sx || null === $this->sy) {
            throw new \RuntimeException('Hheart-beating acknowledgement is not ready');
        }
    }
}
