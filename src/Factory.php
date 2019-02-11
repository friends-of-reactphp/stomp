<?php

namespace React\Stomp;

use React\Socket\Connection;
use React\Stomp\FrameBuffer;
use React\Stream\ThroughStream;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableResourceStream;
use React\Stream\WritableResourceStream;
use React\Stomp\Exception\ConnectionException;

class Factory
{
    private $defaultOptions = array(
        'host'      => '127.0.0.1',
        'port'      => 61613,
        'vhost'     => '/',
        'login'     => 'guest',
        'passcode'  => 'guest',
    );

    private $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function createClient(array $options = array(), bool $silent = false)
    {
        $options = array_merge($this->defaultOptions, $options);

        $connection = $this->createConnection($options);

        $frameBuffer = new FrameBuffer;
        $input = new WritableResourceStream(STDOUT, $this->loop);
        $output = new ReadableResourceStream(STDIN, $this->loop);

        $output->pipe($connection);
        if ($silent === false) {
            $connection->pipe($input);
        }

        $connection->pipe(new ThroughStream(function ($data) use ($input, $frameBuffer) {
            $frames = $frameBuffer->addToBuffer($data)->pullFrames();

            foreach ($frames as $frame) {
                $input->emit('frame', [$frame]);
            }
        }));

        $connection->on('error', function ($error) use ($input) {
            $input->emit('error', [$error]);
        });

        $connection->on('close', function () use ($input) {
            $input->emit('close');
        });

        return new Client($this->loop, $input, $output, $options);
    }

    public function createConnection($options)
    {
        $address = 'tcp://'.$options['host'].':'.$options['port'];

        if (false === $fd = @stream_socket_client($address, $errno, $errstr)) {
            $message = "Could not bind to $address: $errstr";
            throw new ConnectionException($message, $errno);
        }

        $connection = new Connection($fd, $this->loop);

        return $connection;
    }
}
