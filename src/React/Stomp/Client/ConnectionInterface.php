<?php

namespace React\Stomp\Client;

use Evenement\EventEmitterInterface;
use React\Stomp\Protocol\Frame;

interface ConnectionInterface extends EventEmitterInterface
{
    public function connect($host, $port, $vhost, $login, $passcode, $timeout = 5);
    public function disconnect();
    public function send(Frame $frame);
}
