<?php

namespace React\Stomp;

use React\Stomp\Client;

class AckResolver
{
    private $result;

    private $client;
    private $subscriptionId;
    private $messageId;

    public function __construct(Client $client, $subscriptionId, $messageId)
    {
        $this->client = $client;
        $this->subscriptionId = $subscriptionId;
        $this->messageId = $messageId;
    }

    public function ack(array $headers = array())
    {
        $this->throwExceptionIfResolved();

        $this->result = true;
        $this->client->ack($this->subscriptionId, $this->messageId, $headers);
    }

    public function nack(array $headers = array())
    {
        $this->throwExceptionIfResolved();

        $this->result = false;
        $this->client->nack($this->subscriptionId, $this->messageId, $headers);
    }

    private function throwExceptionIfResolved()
    {
        if (null !== $this->result) {
            throw new \RuntimeException('You must not try to resolve an acknowledgement more than once.');
        }
    }
}
