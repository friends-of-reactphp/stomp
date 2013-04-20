<?php

namespace React\Stomp\Io;

use Evenement\EventEmitter;
use React\Socket\Connection as Socket;
use React\SocketClient\ConnectorInterface;
use React\EventLoop\LoopInterface;
use React\Stomp\Exception\IoException;

/**
 * @emit disconnected
 * @emit connecting
 * @emit connected
 * @emit disconnecting
 */
class Connection extends EventEmitter
{
    const STATE_DISCONNECTED = 0;
    const STATE_CONNECTING = 1;
    const STATE_CONNECTED = 2;
    const STATE_DISCONNECTING = 3;

    public $socket;

    private $state = self::STATE_DISCONNECTED;
    private $connector;
    private $loop;
    private $disconnectionListener;

    private $transitions = array(
        self::STATE_DISCONNECTED => array(
            self::STATE_CONNECTING => 'doSocketConnection',
        ),
        self::STATE_CONNECTING    => array(
            self::STATE_CONNECTED    => 'doneSocketConnection',
            self::STATE_DISCONNECTED => 'errorSocketConnection',
        ),
        self::STATE_CONNECTED     => array(
            self::STATE_DISCONNECTING       => 'closeSocketConnection',
            self::STATE_DISCONNECTED => 'errorSocketConnection',
        ),
        self::STATE_DISCONNECTING => array(
            self::STATE_DISCONNECTED => 'doneSocketDisconnection',
        ),
    );

    public function __construct(LoopInterface $loop, ConnectorInterface $connector)//, InputStreamInterface $input, OutputStreamInterface $output)
    {
        $this->loop = $loop;
        $this->connector = $connector;
        $this->state = self::STATE_DISCONNECTED;
    }

    // short cut to setState(self::STATE_CONNECTING)
    public function connect($host = 'localhost', $port = 61613)
    {
        return $this->setState(self::STATE_CONNECTING, $host, $port);
    }

    // short cut to setState(self::STATE_CONNECTING)
    public function disconnect()
    {
        return $this->setState(self::STATE_DISCONNECTING);
    }

    private function setState($state)
    {
        if ($this->state === $state) {
            return;
        }

        if (!isset($this->transitions[$this->state]) || !isset($this->transitions[$this->state][$state])) {
            $error = new IoException(sprintf(
                'Connection transition from %s to %s is not handled', $this->state, $state
            ));
            $this->emit('error', array($error));
        }


        $method = $this->transitions[$this->state][$state];
        $args = func_get_args();
        array_shift($args);

        $this->state = $state;

        return call_user_func_array(array($this, $method), $args);
    }

    public function isConnected()
    {
        return self::STATE_CONNECTED === $this->state;
    }

    public function getState()
    {
        return $this->state;
    }

    // when developer ask for disconnection
    private function closeSocketConnection()
    {
        $this->emit('disconnecting', array($this));
        $this->socket->removeListener('end', $this->disconnectionListener);
        $this->disconnectionListener = null;
        $this->socket->close();
        $this->setState(self::STATE_DISCONNECTED);
    }

    private function doneSocketDisconnection()
    {
        $this->emit('disconnected', array($this));
        $this->socket = null;
    }

    // unable to connect / connection broken
    private function errorSocketConnection(\Exception $error)
    {
        $this->emit('error', array($error));
        $this->socket = null;
    }

    private function doneSocketConnection($stream)
    {
        $this->socket = new Socket($stream, $this->loop);
        $this->emit('connected', array($this));

        $connection = $this;
        // remove this event on manual diconnection
        $this->disconnectionListener = $this->socket->on('end', function () use ($connection) {
            $connection->setState($connection::STATE_DISCONNECTED, new IoException('Connection broken'));
        });
    }

    // connection
    private function doSocketConnection($host, $port)
    {
        $connection = $this;

        $this->emit('connecting', array($this));

        return $this->connector
            ->create($host, $port)
            ->then(function ($stream) use ($connection) {
                $connection->setState($connection::STATE_CONNECTED, $stream);
            }, function ($error) use ($connection) {
                $connection->setState($connection::STATE_DISCONNECTED, $error);
            });
    }
}
