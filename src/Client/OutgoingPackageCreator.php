<?php

namespace React\Stomp\Client;

use React\Stomp\Protocol\Frame;

class OutgoingPackageCreator
{
    private $state;

    public function __construct(State $state)
    {
        $this->state = $state;
    }

    public function connect($host, $login = null, $passcode = null, $heartbeat = null)
    {
        $this->state->startConnecting();

        $headers = array('accept-version' => '1.1', 'host' => $host);
        if (null !== $login || null !== $passcode) {
            $headers = array_merge($headers, array(
                'login'     => (string) $login,
                'passcode'  => (string) $passcode,
            ));
        }
        if (null !== $heartbeat) {
            $headers = array_merge($headers, array(
                'heart-beat' => $heartbeat,
            ));
        }

        return new Frame('CONNECT', $headers);
    }

    public function send($destination, $body, array $headers = array())
    {
        $headers['destination'] = $destination;
        $headers['content-length'] = strlen($body);
        if (!isset($headers['content-type'])) {
            $headers['content-type'] = 'text/plain';
        }
        return new Frame('SEND', $headers, $body);
    }

    public function subscribe($destination, $ack = 'auto', array $headers = array())
    {
        $headers['id'] = $this->state->subscriptions->add($destination, $ack);
        $headers['destination'] = $destination;
        $headers['ack'] = $ack;
        return new Frame('SUBSCRIBE', $headers);
    }

    public function unsubscribe($subscriptionId, array $headers = array())
    {
        $headers['id'] = $subscriptionId;
        return new Frame('UNSUBSCRIBE', $headers);
    }

    public function ack($subscriptionId, $messageId, array $headers = array())
    {
        $headers['subscription'] = $subscriptionId;
        $headers['message-id'] = $messageId;
        return new Frame('ACK', $headers);
    }

    public function nack($subscriptionId, $messageId, array $headers = array())
    {
        $headers['subscription'] = $subscriptionId;
        $headers['message-id'] = $messageId;
        return new Frame('NACK', $headers);
    }

    public function begin($transactionId, array $headers = array())
    {
        $headers['transaction'] = $transactionId;
        return new Frame('BEGIN', $headers);
    }

    public function commit($transactionId, array $headers = array())
    {
        $headers['transaction'] = $transactionId;
        return new Frame('COMMIT', $headers);
    }

    public function abort($transactionId, array $headers = array())
    {
        $headers['transaction'] = $transactionId;
        return new Frame('ABORT', $headers);
    }

    public function disconnect($receipt, array $headers = array())
    {
        $this->state->startDisconnecting($receipt);

        $headers['receipt'] = $receipt;
        return new Frame('DISCONNECT', $headers);
    }
}
