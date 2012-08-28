<?php

namespace React\Stomp\Client;

use React\Socket\ConnectionInterface;
use React\Stomp\Protocol\Frame;
use React\Stomp\Client\Command\CloseCommand;
use React\Stomp\Client\Command\ConnectionEstablishedCommand;
use React\Stomp\Client\Command\NullCommand;

class OutgoingPackageCreator
{
    private $state;

    public function __construct(State $state)
    {
        $this->state = $state;
    }

    public function connect($host, $login = null, $passcode = null)
    {
        $this->state->startConnecting();

        $headers = array('accept-version' => '1.1', 'host' => $host);
        if (null !== $login || null !== $passcode) {
            $headers = array_merge($headers, array(
                'login'     => (string) $login,
                'passcode'  => (string) $passcode,
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

    /**
     * Feed frame from the server
     *
     * @return An array of commands to be executed by the caller.
     */
    public function receiveFrame(Frame $frame)
    {
        if ('ERROR' === $frame->command) {
            throw new ServerErrorException($frame);
        }

        if ($this->state->isConnecting()) {
            if ('CONNECTED' !== $frame->command) {
                throw new InvalidFrameException(sprintf("Received frame with command '%s', expected 'CONNECTED'."));
            }

            $this->state->doneConnecting(
                $frame->getHeader('session'),
                $frame->getHeader('server')
            );

            return new ConnectionEstablishedCommand();
        }

        if (State::STATUS_DISCONNECTING === $this->state->status) {
            if ('RECEIPT' === $frame->command && $this->state->isDisconnectionReceipt($frame->getHeader('receipt-id'))) {
                $this->state->doneDisconnecting();

                return new CloseCommand();
            }
        }

        return new NullCommand();
    }
}
