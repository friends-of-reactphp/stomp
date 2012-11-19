<?php

namespace React\Stomp\Client;

use React\EventLoop\LoopInterface;
use React\Stomp\Client;
use React\Stomp\Protocol\Frame;
use React\Stomp\Io\InputStreamInterface;
use React\Stomp\Io\OutputStreamInterface;

class Heartbeat
{
    public $clientGuarantee;
    public $clientExpect;
    public $serverGuarantee;
    public $serverExpect;

    public $lastReceivedFrame;
    public $lastSentFrame;

    private $clientTimerSignature;
    private $serverTimerSignature;

    private $client;
    private $loop;

    public function __construct(Client $client, LoopInterface $loop, InputStreamInterface $input, OutputStreamInterface $output, $cx = 0, $cy = 0)
    {
        $this->client = $client;
        $this->loop = $loop;

        $this->clientGuarantee = $cx;
        $this->clientExpect = $cy;

        $this->input = $input;
        $this->output = $output;

        $this->client->on('connect', array($this, 'clientConnected'));
        $this->client->on('disconnect', array($this, 'clientDisconnected'));
        $this->input->on('frame', array($this, 'updateReceivedFrame'));
        $this->output->on('data', array($this, 'updateSentFrame'));
    }

    public function clientConnected(Client $client, Frame $frame)
    {
        $settings = explode(',', $frame->getHeader('heart-beat') ?: '0,0');
        $this->serverGuarantee = (int) $settings[0];
        $this->serverExpect = (int) $settings[1];

        if(0 !== $interval = $this->getSendingAcknowledgement()) {
            // client must send message at least evry x ms
            $client = $this->client;
            $this->clientTimerSignature = $this->loop->addPeriodicTimer(0.9 * $interval / 1000, function () use ($client) {
                $client->sendHeartbeat();
            });
        }

        if(0 !== $interval = $this->getReceptionAcknowledgement()) {
            // client must receive message at least every x ms
            $heartbeat = $this;
            $client = $this->client;
            $this->serverTimerSignature = $this->loop->addPeriodicTimer(1.1 * $interval / 1000, function () use ($client, $heartbeat, $interval) {
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
    public function clientDisconnected(Client $client)
    {
        $this->loop->cancelTimer($this->clientTimerSignature);
        $this->loop->cancelTimer($this->serverTimerSignature);
        $this->lastReceivedFrame = $this->lastSentFrame = null;
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

        if ($this->serverGuarantee === 0 || $this->clientExpect === 0) {
            return 0;
        }

        return max($this->serverGuarantee, $this->clientExpect);
    }

    public function getSendingAcknowledgement()
    {
        $this->throwExceptionIfNoAcknowledgement();

        if ($this->clientGuarantee === 0 || $this->serverExpect === 0) {
            return 0;
        }

        return max($this->clientGuarantee, $this->serverExpect);
    }

    private function throwExceptionIfNoAcknowledgement()
    {
        if(null === $this->serverGuarantee || null === $this->serverExpect) {
            throw new \RuntimeException('Heart-beating acknowledgement is not ready');
        }
    }
}
