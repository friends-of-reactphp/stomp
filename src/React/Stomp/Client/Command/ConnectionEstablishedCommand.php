<?php

namespace React\Stomp\Client\Command;

// TODO: this is weird, should be an event instead of command

class ConnectionEstablishedCommand implements CommandInterface
{
    public $heartbeatServerSettings;

    public function __construct($heartbeatServerSettings)
    {
        $this->heartbeatServerSettings = $heartbeatServerSettings;
    }
}
