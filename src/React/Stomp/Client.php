<?php

namespace React\Stomp;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Client\OutgoingPackageCreator;
use React\Stomp\Client\State;
use React\Stomp\Client\Command\CommandInterface;
use React\Stomp\Client\Command\CloseCommand;
use React\Stomp\Client\Command\ConnectionEstablishedCommand;
use React\Stomp\Client\Command\NullCommand;
use React\Stomp\Protocol\Frame;
use React\Stomp\Protocol\Parser;

class Client extends EventEmitter
{
    private $defaultOptions = array(
        'host' => '127.0.0.1',
        'port' => 61613,
    );

    private $parser;
    private $packageProcessor;
    private $packageCreator;
    private $subscriptions = array();

    public function __construct(array $options)
    {
        $options = array_merge($this->defaultOptions, $options);

        $this->conn = $this->getConnection($options);
        $this->conn->stomp = new State();

        $this->parser = new Parser();
        $this->packageProcessor = isset($options['package_processor']) ? $options['package_processor'] : new IncomingPackageProcessor($this->conn->stomp);
        $this->packageCreator = isset($options['package_creator']) ? $options['package_creator'] : new OutgoingPackageCreator($this->conn->stomp);

        $this->conn->on('data', array($this, 'handleData'));

        $host = isset($options['vhost']) ? $options['vhost'] : $options['host'];
        $login = isset($options['login']) ? $options['login'] : null;
        $passcode = isset($options['passcode']) ? $options['passcode'] : null;

        $frame = $this->packageCreator->connect($host, $login, $passcode);
        $this->conn->write((string) $frame);
    }

    public function getConnection($options)
    {
        if (isset($options['connection'])) {
            return $options['connection'];
        }

        $connFactory = $this->getConnectionFactory($options);

        return $connFactory->create($options);
    }

    public function getConnectionFactory(array $options)
    {
        if (isset($options['connection_factory'])) {
            return $options['connection_factory'];
        }

        if (isset($options['loop'])) {
            return new ConnectionFactory($options['loop']);
        }

        throw new \InvalidArgumentException('Invalid configuration, must container either of: loop, connection_factory, connection.');
    }

    public function send($destination, $body, array $headers = array())
    {
        $frame = $this->packageCreator->send($destination, $body, $headers);
        $this->conn->write((string) $frame);
    }

    public function subscribe($destination, $callback, $ack = 'auto', array $headers = array())
    {
        $frame = $this->packageCreator->subscribe($destination, $headers);
        $this->conn->write((string) $frame);

        $subscriptionId = $frame->getHeader('id');
        $this->subscriptions[$subscriptionId] = $callback;

        return $subscriptionId;
    }

    public function unsubscribe($subscriptionId, array $headers = array())
    {
        $frame = $this->packageCreator->unsubscribe($subscriptionId, $headers);
        $this->conn->write((string) $frame);

        unset($this->subscriptions[$subscriptionId]);
    }

    public function disconnect()
    {
        $receipt = $this->generateReceiptId();
        $frame = $this->packageCreator->disconnect($receipt);
        $this->conn->write((string) $frame);
    }

    public function handleData($data)
    {
        $frames = $this->parseFramesFromConnectionAndData($data);

        foreach ($frames as $frame) {
            $command = $this->packageProcessor->receiveFrame($frame);
            $this->executeCommand($command);

            $this->handleFrame($frame);
        }
    }

    public function parseFramesFromConnectionAndData($data)
    {
        $data = $this->conn->stomp->unparsed.$data;
        list($frames, $data) = $this->parser->parse($data);
        $this->conn->stomp->unparsed = $data;

        return $frames;
    }

    public function handleFrame(Frame $frame)
    {
        if ('MESSAGE' === $frame->command) {
            $this->notifySubscribers($frame);
            return;
        }
    }

    public function notifySubscribers(Frame $frame)
    {
        $subscriptionId = $frame->getHeader('subscription');

        if (!isset($this->subscriptions[$subscriptionId])) {
            return;
        }

        $callback = $this->subscriptions[$subscriptionId];
        call_user_func($callback, $frame);
    }

    public function executeCommand(CommandInterface $command)
    {
        if ($command instanceof CloseCommand) {
            $this->conn->close();
            return;
        }

        if ($command instanceof ConnectionEstablishedCommand) {
            $this->emit('ready');
            return;
        }

        if ($command instanceof NullCommand) {
            return;
        }

        throw new \Exception(sprintf("Unknown command '%s'", get_class($command)));
    }

    public function generateReceiptId()
    {
        return mt_rand();
    }
}
