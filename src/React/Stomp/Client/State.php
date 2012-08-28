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
}
