<?php

namespace React\Stomp;

use React\EventLoop\LoopInterface;
use React\Socket\Connection;

class ConnectionFactory
{
    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function create($options)
    {
        $address = 'tcp://'.$options['host'].':'.$options['port'];

        $fd = stream_socket_client($address);
        $conn = new Connection($fd, $this->loop);

        return $conn;
    }
}
