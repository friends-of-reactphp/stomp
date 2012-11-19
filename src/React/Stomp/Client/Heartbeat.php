<?php

namespace React\Stomp\Client;

use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
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
    private $loop;

    public function __construct(Client $client, LoopInterface $loop, InputStreamInterface $input, OutputStreamInterface $output, $cx = 0, $cy = 0)
    {
        $this->client = $client;
        $this->loop = $loop;

        $this->cx = $cx;
        $this->cy = $cy;

        $this->input = $input;
        $this->output = $output;

        $this->client->on('connect', array($this, 'clientConnected'));
        $this->input->on('frame', array($this, 'updateReceivedFrame'));
        $this->output->on('data', array($this, 'updateSentFrame'));
    }

    public function clientConnected(Client $client, Frame $frame)
    {
        $settings = explode(',', $frame->getHeader('heart-beat', '0,0'));
        $this->sx = (int) $settings[0];
        $this->sy = (int) $settings[1];

        if(0 !== $interval = $this->getSendingAcknowledgement()) {
            // client must send message at least evry x ms
            $client = $this->client;
            $this->loop->addPeriodicTimer(0.9 * $interval / 1000, function () use ($client) {
                $client->sendHeartbeat();
            });
        }

        if(0 !== $interval = $this->getReceptionAcknowledgement()) {
            // client must receive message at least every x ms
            $heartbeat = $this;
            $client = $this->client;
            $this->loop->addPeriodicTimer(1.1 * $interval / 1000, function () use ($client, $heartbeat, $interval) {
                if (microtime(true) > ($heartbeat->lastReceivedFrame + ($interval / 1000))) {
                    $client->emit('error', array(
                        new \RuntimeException(
                            sprintf('No heart beat received since %s', $heartbeat->lastReceivedFrame)
                        )
                    ));
                }
            });
        }
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
