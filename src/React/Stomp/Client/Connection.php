<?php

namespace React\Stomp\Client;

use Evenement\EventEmitter;
use React\Stomp\Io\Connection as TcpConnection;
use React\Stomp\Io\InputStreamInterface;
use React\Stomp\Io\OutputStreamInterface;
use React\Stomp\Client\OutgoingPackageCreator;
use React\Stomp\Protocol\Frame;
use React\Stream\Util;
use React\EventLoop\LoopInterface;
use React\Stomp\Exception\UnexpectedFrameException;
use React\Stomp\Exception\FrameNotSentException;
use React\Promise\Deferred;
use React\Stomp\Exception\ConnectionException;

/**
 * @emit frame
 * @emit error
 */
class Connection extends EventEmitter implements ConnectionInterface
{
    private $loop;
    private $conn;
    private $input;
    private $output;

    private $connectDeferred;
    private $disconnectDeferred;
    private $connectionListener;
    private $disconnectListener;

    public $vhost;
    public $login;
    public $passcode;

    public $state;
    public $packageCreator;
    public $packageProcessor;

    public function __construct(LoopInterface $loop, InputStreamInterface $intput, OutputStreamInterface $output, TcpConnection $conn)
    {
        $this->conn = $conn;
        $this->loop = $loop;
        $this->state = new State();

        $this->input = $intput;
        $this->output = $output;

        $this->packageCreator = new OutgoingPackageCreator($this->state);
        $this->setIncomingPackageProcessor(new IncomingPackageProcessor($this->state));

        $this->handleTcpConnectionEvents();

        $this->input->on('frame', array($this, 'handleFrameEvent'));
    }

    /** @api */
    public function connect($host, $port, $vhost, $login, $passcode, $timeout = 5)
    {
        if ($this->connectDeferred) {
            return $this->connectDeferred->promise();
        }

        $this->state->setStatus(State::STATUS_CONNECTING);

        $connection = $this;

        $this->conn->connect($host, $port);
        $this->connectDeferred = $promise = new Deferred();

        $timer = $this->loop->addTimer($timeout, function () use ($connection, $promise) {
            $promise->reject(new ConnectionException('Connection timed-out'));
            $connection->resetConnectDeferred();
            $connection->state->setStatus(State::STATUS_DISCONNECTED);
        });

        $this->connectionListener = $this->once('connect', function ($connection) use ($timer, $promise) {
            $timer->cancel();
            $promise->resolve($connection);
        });

        $frame = $this->packageCreator->connect($vhost, $login, $passcode);
        $this->sendFrame($frame);

        return $promise;
    }

    /** @api */
    public function disconnect($timeout = 1)
    {
        if ($this->state->isDisconnecting()) {
            return $this->disconnectDeferred;
        }

        if (!$this->state->isConnected()) {
            $this->emit('error', array(new ConnectionException('Client is not connected')));
            return;
        }

        $receipt = $this->generateReceiptId();
        $this->state->setStatus(State::STATUS_DISCONNECTING, $receipt);

        $connection = $this;
        $this->disconnectDeferred = $promise = new Deferred();

        $this->disconnectDeferred->then(function () {
            $this->conn->disconnect();
        }, function () {
            $this->conn->disconnect();
        });

        $timer = $this->loop->addTimer($timeout, function () use ($connection, $promise) {
            $promise->reject(new ConnectionException('Disconnection timed-out'));
            $connection->resetDisconnectDeferred();
            $this->state->doneDisconnecting();
        });

        $this->disconnectListener = $this->once('disconnect', function ($connection) use ($timer, $promise) {
            $timer->cancel();
            $promise->resolve($connection);
        });

        $frame = $this->packageCreator->disconnect($receipt);
        $this->sendFrame($frame);

        return $promise;
    }

    /** @api */
    public function send(Frame $frame)
    {
        if (!$this->state->isConnected()) {
            $this->emit('error', array(new FrameNotSentException($frame)));
        } else {
            $this->output->sendFrame($frame);
        }
    }

    public function setIncomingPackageProcessor(IncomingPackageProcessor $processor)
    {
        $this->packageProcessor = $processor;
        $this->handleIncomingProcessorEvents();
    }

    public function handleFrameEvent(Frame $frame)
    {
        try {
            $this->packageProcessor->receiveFrame($frame);
        } catch (UnexpectedFrameException $e) {
            $this->emit('error', array($e));

            if ($this->state->isConnecting()) {
                $this->connectDeferred->reject($e);
                $this->connectDeferred = null;
                $this->state->setStatus(State::STATUS_DISCONNECTED);
            }
        }
    }

    public function resetDisconnectDeferred()
    {
        $this->removeListener('disconnect', $this->disconnectListener);
        $this->disconnectDeferred = $this->disconnectListener = null;
    }

    public function resetConnectDeferred()
    {
        $this->removeListener('connect', $this->connectionListener);
        $this->connectDeferred = $this->connectionListener = null;
    }

    private function handleIncomingProcessorEvents()
    {
        Util::forwardEvents($this->packageProcessor, $this, array('frame', 'error'));

        $this->packageProcessor->on('connected', function ($frame) {
            $this->emit('connected', array($this));
        });
        $this->packageProcessor->on('disconnected', function ($frame) {
            $this->conn->disconnect();
        });
    }

    private function handleTcpConnectionEvents()
    {
        $output = $this->output;
        $input = $this->input;
        $connection = $this;

        // tcp conn is broken or connection did not occur
        Util::forwardEvents($this->conn, $this, array('error'));

        $this->conn->on('connected', function (TcpConnection $tcpConn) use ($connection, $output, $input) {
            $tcpConn->socket->pipe($input);
            $output->pipe($tcpConn->socket);

            $frame = $connection->packageCreator->connect($connection->vhost, $connection->login, $connection->passcode);
            $output->sendFrame($frame);
        });
    }
}
