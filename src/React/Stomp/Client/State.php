<?php

namespace React\Stomp\Client;

class State
{
    const STATUS_CONNECTING = 0;
    const STATUS_CONNECTED = 1;
    const STATUS_DISCONNECTING = 2;
    const STATUS_DISCONNECTED = 3;

    public $session;
    public $server;
    public $receipt;
    public $subscriptions;

    private $status;

    private $transitions = array(
        self::STATUS_DISCONNECTED => array(
            self::STATUS_CONNECTING => 'startConnecting',
        ),
        self::STATUS_CONNECTING => array(
            self::STATUS_CONNECTED => 'doneConnecting',
            self::STATUS_DISCONNECTED => 'errorConnecting',
        ),
        self::STATUS_CONNECTED => array(
            self::STATUS_DISCONNECTING => 'startDisconnecting',
            self::STATUS_DISCONNECTED => 'connectionError',
        ),
        self::STATUS_DISCONNECTING => array(
            self::STATUS_DISCONNECTED => 'doneDisconnecting',
        )
    );

    public function __construct($status = self::STATUS_DISCONNECTED)
    {
        $this->status = $status;
        $this->subscriptions = new SubscriptionBag();
    }

    public function setStatus($status)
    {
        if (!isset($this->transitions[$this->status]) || !isset($this->transitions[$this->status][$status])) {
            throw new \RuntimeException(sprintf('Unexpected STOMP connection transition from %s to %s', $this->status, $status));
        }

        $method = $this->transitions[$this->status][$status];
        $args = func_get_args();
        array_shift($args);

        $this->status = $status;

        return call_user_func_array(array($this, $method), $args);
    }

    public function doneDisconnecting()
    {
        $this->receipt = $this->session = $this->server = null;
        $this->status = self::STATUS_DISCONNECTED;
    }

    public function startConnecting()
    {
        $this->status = self::STATUS_CONNECTING;
    }

    public function doneConnecting($session, $server)
    {
        $this->status   = self::STATUS_CONNECTED;
        $this->session  = $session;
        $this->server   = $server;
    }

    public function startDisconnecting($receipt)
    {
        $this->status = self::STATUS_DISCONNECTING;
        $this->receipt = $receipt;
    }

    public function errorConnecting()
    {
        $this->status = self::STATUS_DISCONNECTED;
    }

    public function connectionError()
    {
        $this->session = $this->server = null;
        $this->status = self::STATUS_DISCONNECTED;
    }

    public function isConnecting()
    {
        return self::STATUS_CONNECTING === $this->status;
    }

    public function isConnected()
    {
        return self::STATUS_CONNECTED === $this->status;
    }

    public function isDisconnected()
    {
        return self::STATUS_DISCONNECTED === $this->status;
    }

    public function isDisconnecting()
    {
        return self::STATUS_DISCONNECTING === $this->status;
    }

    public function isDisconnectionReceipt($receipt)
    {
        return $this->receipt === $receipt;
    }
}
