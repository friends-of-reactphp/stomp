<?php

namespace React\Stomp;

use React\EventLoop\LoopInterface;
use React\Stomp\Client\Heartbeat;
use React\Stomp\Protocol\Parser;
use React\Stomp\Io\InputStream;
use React\Stomp\Io\OutputStream;
use React\Socket\Connection;

class Factory
{
    private $defaultOptions = array(
        'host'         => '127.0.0.1',
        'port'         => 61613,
        'vhost'        => '/',
        'login'        => 'guest',
        'passcode'     => 'guest',
        'heartbeat-cx' => 0,
        'heartbeat-cy' => 0,
    );

    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function createClient(array $options = array())
    {
        $options = array_merge($this->defaultOptions, $options);

        $conn = $this->createConnection($options);

        $parser = new Parser();
        $input = new InputStream($parser);
        $conn->pipe($input);

        $output = new OutputStream($this->loop);
        $output->pipe($conn);

        $conn->on('error', function ($e) use ($input) {
            $input->emit('error', array($e));
        });

        return new Client($this->loop, $input, $output, $options);
    }

    public function createConnection($options)
    {
        $address = 'tcp://'.$options['host'].':'.$options['port'];

        $fd = stream_socket_client($address);
        $conn = new Connection($fd, $this->loop);

        return $conn;
    }
}
