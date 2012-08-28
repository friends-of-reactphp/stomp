<?php

namespace React\Stomp\Client;

class State
{
    const STATUS_INIT = 0;
    const STATUS_CONNECTING = 1;
    const STATUS_CONNECTED = 2;
    const STATUS_DISCONNECTING = 3;
    const STATUS_DISCONNECTED = 4;

    public $status = self::STATUS_INIT;
    public $session;
    public $server;
    public $subscriptions;
    public $receipt;
    public $unparsed;

    public function __construct()
    {
        $this->subscriptions = new SubscriptionBag();
    }

    public function startConnecting()
    {
        $this->status = self::STATUS_CONNECTING;
    }

    public function isConnecting()
    {
        return self::STATUS_CONNECTING === $this->status;
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

    public function isDisconnecting()
    {
        return self::STATUS_DISCONNECTING === $this->status;
    }

    public function isDisconnectionReceipt($receipt)
    {
        return $this->receipt === $receipt;
    }

    public function doneDisconnecting()
    {
        $this->status = self::STATUS_DISCONNECTED;
    }
}
