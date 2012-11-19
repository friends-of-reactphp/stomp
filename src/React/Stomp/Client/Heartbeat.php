<?php

namespace React\Stomp\Client;

use React\Stomp\Client;
use React\Stomp\Io\InputStreamInterface;
use React\Stomp\Io\OutputStreamInterface;

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

    private $client;

    public function __construct(Client $client, InputStreamInterface $input, OutputStreamInterface $output, $cx = 0, $cy = 0)
    {
        $this->client = $client;

        $this->cx = $cx;
        $this->cy = $cy;

        $this->input = $input;
        $this->output = $output;

        $this->input->on('frame', array($this, 'updateReceivedFrame'));
        $this->output->on('data', array($this, 'updateSentFrame'));
    }

    public function updateReceivedFrame($frame)
    {
        $this->lastReceivedFrame = microtime(true);
    }

    public function updateSentFrame($data)
    {
        $this->lastSentFrame = microtime(true);
    }

    public function getReceptionAcknowledgement()
    {
        $this->throwExceptionIfNoAcknowledgement();

        if ($this->sx === 0 || $this->cy === 0) {
            return 0;
        }

        return max($this->sx, $this->cy);
    }

    public function getSendingAcknowledgement()
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
