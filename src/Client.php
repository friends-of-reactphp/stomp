<?php

namespace React\Stomp;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\Stomp\Client\State;
use React\Stomp\Protocol\Frame;
use React\EventLoop\LoopInterface;
use React\Stream\ReadableStreamInterface;
use React\Stream\WritableStreamInterface;
use React\Stomp\Client\Command\NullCommand;
use React\Stomp\Client\Command\CloseCommand;
use React\Stomp\Client\OutgoingPackageCreator;
use React\Stomp\Exception\ConnectionException;
use React\Stomp\Exception\ProcessingException;
use React\Stomp\Client\Command\CommandInterface;
use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Client\Command\ConnectionEstablishedCommand;

/**
 * @event connect
 * @event error
 */
class Client extends EventEmitter
{
    private $loop;
    private $connectionStatus = 'not-connected';
    private $packageProcessor;
    private $packageCreator;
    private $subscriptions = array();
    private $acknowledgements = array();
    private $options = array();

    /** @var Deferred */
    private $connectDeferred;

    /** @var PromiseInterface */
    private $connectPromise;

    public function __construct(LoopInterface $loop, WritableStreamInterface $input, ReadableStreamInterface $output, array $options)
    {
        $this->loop = $loop;
        $state = new State();
        $this->packageProcessor = new IncomingPackageProcessor($state);
        $this->packageCreator = new OutgoingPackageCreator($state);

        $this->input = $input;
        $this->input->on('frame', array($this, 'handleFrameEvent'));
        $this->input->on('error', array($this, 'handleErrorEvent'));
        $this->input->on('close', array($this, 'handleCloseEvent'));
        $this->output = $output;

        $this->options = $this->sanatizeOptions($options);
    }

    public function connect($timeout = 5)
    {
        if ($this->connectPromise) {
            return $this->connectPromise;
        }

        $this->setConnectionStatus('connecting');

        $deferred = $this->connectDeferred = new Deferred();
        $client = $this;

        $timer = $this->loop->addTimer($timeout, function () use ($client, $deferred) {
            $deferred->reject(new ConnectionException('Connection timeout'));
            $client->resetConnectDeferred();
            $client->setConnectionStatus('not-connected');
        });

        $this->on('connect', function ($client) use ($timer, $deferred) {
            $this->loop->cancelTimer($timer);
            $deferred->resolve($client);
        });

        $frame = $this->packageCreator->connect(
            $this->options['vhost'],
            $this->options['login'],
            $this->options['passcode']
        );
        $this->sendFrameToOutput($frame);

        return $this->connectPromise = $deferred->promise()->then(function () use ($client) {
            $client->setConnectionStatus('connected');
            return $client;
        });
    }

    private function sendFrameToOutput(Frame $frame)
    {
        $this->output->emit('data', array($frame));
    }

    public function send($destination, $body, array $headers = array())
    {
        $frame = $this->packageCreator->send($destination, $body, $headers);
        $this->sendFrameToOutput($frame);
    }

    public function subscribe($destination, $callback, array $headers = array())
    {
        return $this->doSubscription($destination, $callback, 'auto', $headers);
    }

    public function subscribeWithAck($destination, $ack, $callback, array $headers = array())
    {
        if ('auto' === $ack) {
            throw new \LogicException("ack 'auto' is not compatible with acknowledgeable subscription");
        }
        return $this->doSubscription($destination, $callback, $ack, $headers);
    }

    private function doSubscription($destination, $callback, $ack, array $headers)
    {
        $frame = $this->packageCreator->subscribe($destination, $ack, $headers);
        $this->sendFrameToOutput($frame);

        $subscriptionId = $frame->getHeader('id');

        $this->acknowledgements[$subscriptionId] = $ack;
        $this->subscriptions[$subscriptionId] = $callback;

        return $subscriptionId;
    }

    public function unsubscribe($subscriptionId, array $headers = array())
    {
        $frame = $this->packageCreator->unsubscribe($subscriptionId, $headers);
        $this->sendFrameToOutput($frame);

        unset($this->acknowledgements[$subscriptionId]);
        unset($this->subscriptions[$subscriptionId]);
    }

    public function ack($subscriptionId, $messageId, array $headers = array())
    {
        $frame = $this->packageCreator->ack($subscriptionId, $messageId, $headers);
        $this->sendFrameToOutput($frame);
    }

    public function nack($subscriptionId, $messageId, array $headers = array())
    {
        $frame = $this->packageCreator->nack($subscriptionId, $messageId, $headers);
        $this->sendFrameToOutput($frame);
    }

    public function disconnect()
    {
        $receipt = $this->generateReceiptId();
        $frame = $this->packageCreator->disconnect($receipt);
        $this->sendFrameToOutput($frame);

        $this->connectDeferred = null;
        $this->connectPromise = null;
        $this->setConnectionStatus('not-connected');
    }

    public function resetConnectDeferred()
    {
        $this->connectDeferred = null;
        $this->connectPromise = null;
    }

    public function handleFrameEvent(Frame $frame)
    {
        try {
            $this->emit('frame', array($frame));
            $this->processFrame($frame);
        } catch (ProcessingException $e) {
            $this->emit('error', array($e));

            if ($this->connectionStatus === 'connecting') {
                $this->connectDeferred->reject($e);
                $this->connectDeferred = null;
                $this->connectPromise = null;
                $this->setConnectionStatus('not-connected');
            }
        }
    }

    public function handleErrorEvent(\Exception $e)
    {
        $this->emit('error', array($e));
    }

    public function handleCloseEvent()
    {
        $this->connectDeferred = null;
        $this->connectPromise = null;
        $this->setConnectionStatus('not-connected');

        $this->emit('close');
    }

    public function processFrame(Frame $frame)
    {
        $command = $this->packageProcessor->receiveFrame($frame);
        $this->executeCommand($command);

        if ('MESSAGE' === $frame->command) {
            $this->notifySubscribers($frame);
            return;
        }
    }

    public function executeCommand(CommandInterface $command)
    {
        if ($command instanceof CloseCommand) {
            $this->output->close();
            return;
        }

        if ($command instanceof ConnectionEstablishedCommand) {
            $this->emit('connect', array($this));
            return;
        }

        if ($command instanceof NullCommand) {
            return;
        }

        throw new \Exception(sprintf("Unknown command '%s'", get_class($command)));
    }

    public function notifySubscribers(Frame $frame)
    {
        $subscriptionId = $frame->getHeader('subscription');

        if (!isset($this->subscriptions[$subscriptionId])) {
            return;
        }

        $callback = $this->subscriptions[$subscriptionId];

        if ('auto' !== $this->acknowledgements[$subscriptionId]) {
            $resolver = new AckResolver($this, $subscriptionId, $frame->getHeader('message-id'));
            $parameters = array($frame, $resolver);
        } else {
            $parameters = array($frame);
        }

        call_user_func_array($callback, $parameters);
    }

    private function sanatizeOptions($options)
    {
        if (!isset($options['host']) && !isset($options['vhost'])) {
            throw new \InvalidArgumentException('Either host or vhost options must be provided.');
        }

        return array_merge(array(
            'vhost'     => isset($options['host']) ? $options['host'] : null,
            'login'     => null,
            'passcode'  => null,
        ), $options);
    }

    public function isConnected()
    {
        return $this->connectionStatus === 'connected';
    }

    public function setConnectionStatus($status)
    {
        $this->connectionStatus = $status;

        $this->emit('connection-status', array($this->connectionStatus));
    }

    public function generateReceiptId()
    {
        return mt_rand();
    }
}
