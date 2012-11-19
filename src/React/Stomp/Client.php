<?php

namespace React\Stomp;

use Evenement\EventEmitter;
use React\Promise\Deferred;
use React\EventLoop\LoopInterface;
use React\Stomp\Client\Heartbeat;
use React\Stomp\Protocol\HeartbeatFrame;
use React\Stomp\Client\IncomingPackageProcessor;
use React\Stomp\Client\OutgoingPackageCreator;
use React\Stomp\Client\State;
use React\Stomp\Client\Command\CommandInterface;
use React\Stomp\Client\Command\CloseCommand;
use React\Stomp\Client\Command\ConnectionEstablishedCommand;
use React\Stomp\Client\Command\NullCommand;
use React\Stomp\Exception\ProcessingException;
use React\Stomp\Io\InputStreamInterface;
use React\Stomp\Io\OutputStreamInterface;
use React\Stomp\Protocol\Frame;
use React\Stomp\Protocol\FrameInterface;

// Events: connect, error
class Client extends EventEmitter
{
    private $loop;
    private $heartbeat;
    private $packageProcessor;
    private $packageCreator;
    private $subscriptions = array();
    private $acknowledgements = array();
    private $options = array();
    private $connectDeferred;

    public function __construct(LoopInterface $loop, InputStreamInterface $input, OutputStreamInterface $output, array $options)
    {
        $this->loop = $loop;
        $this->heartbeat = new Heartbeat($this, $input, $output);

        $state = new State();
        $this->packageProcessor = new IncomingPackageProcessor($state);
        $this->packageCreator = new OutgoingPackageCreator($state);

        $this->input = $input;
        $this->input->on('frame', array($this, 'handleFrameEvent'));
        $this->input->on('error', array($this, 'handleErrorEvent'));
        $this->output = $output;

        $this->options = $this->sanitizeOptions($options);

        $this->heartbeat->cx = $this->options['heartbeat-cx'];
        $this->heartbeat->cy = $this->options['heartbeat-cy'];
    }

    public function connect()
    {
        if ($this->connectDeferred) {
            return $this->connectDeferred->promise();
        }

        $this->connectDeferred = new Deferred();
        $this->on('connect', array($this->connectDeferred, 'resolve'));

        $frame = $this->packageCreator->connect(
            $this->options['vhost'],
            $this->options['login'],
            $this->options['passcode'],
            $this->heartbeat->cx,
            $this->heartbeat->cy
        );
        $this->output->sendFrame($frame);

        return $this->connectDeferred->promise();
    }

    public function send($destination, $body, array $headers = array())
    {
        $frame = $this->packageCreator->send($destination, $body, $headers);
        $this->output->sendFrame($frame);
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
        $this->output->sendFrame($frame);

        $subscriptionId = $frame->getHeader('id');

        $this->acknowledgements[$subscriptionId] = $ack;
        $this->subscriptions[$subscriptionId] = $callback;

        return $subscriptionId;
    }

    public function unsubscribe($subscriptionId, array $headers = array())
    {
        $frame = $this->packageCreator->unsubscribe($subscriptionId, $headers);
        $this->output->sendFrame($frame);

        unset($this->acknowledgements[$subscriptionId]);
        unset($this->subscriptions[$subscriptionId]);
    }

    public function ack($subscriptionId, $messageId, array $headers = array())
    {
        $frame = $this->packageCreator->ack($subscriptionId, $messageId, $headers);
        $this->output->sendFrame($frame);
    }

    public function nack($subscriptionId, $messageId, array $headers = array())
    {
        $frame = $this->packageCreator->nack($subscriptionId, $messageId, $headers);
        $this->output->sendFrame($frame);
    }

    public function sendHeartbeat()
    {
        $frame = $this->packageCreator->heartbeat();
        $this->output->sendFrame($frame);
    }

    public function disconnect()
    {
        $receipt = $this->generateReceiptId();
        $frame = $this->packageCreator->disconnect($receipt);
        $this->output->sendFrame($frame);

        $this->connectDeferred = null;
    }

    public function handleFrameEvent(FrameInterface $frame)
    {
        try {
            $this->processFrame($frame);
        } catch (ProcessingException $e) {
            $this->emit('error', array($e));
        }
    }

    public function handleErrorEvent(\Exception $e)
    {
        $this->emit('error', array($e));
    }

    public function processFrame(FrameInterface $frame)
    {
        if ($frame instanceof HeartbeatFrame) {
            return;
        }

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

            $settings = explode(',', $command->heartbeatServerSettings);
            $this->heartbeat->sx = (int) $settings[0];
            $this->heartbeat->sy = (int) $settings[1];

            if(0 !== $interval = $this->heartbeat->getSendingAcknowledgement()) {
                // client must send message at least evry x ms
                $client = $this;
                $this->loop->addPeriodicTimer(0.9 * $interval / 1000, function () use ($client) {
                    $client->sendHeartbeat();
                });
            }

            if(0 !== $interval = $this->heartbeat->getReceptionAcknowledgement()) {
                // client must receive message at least every x ms
                $heartbeat = $this->heartbeat;
                $client = $this;
                $this->loop->addPeriodicTimer(1.1 * $interval / 1000, function () use ($client, $heartbeat, $interval) {
                    if (microtime(true) > ($heartbeat->lastReceivedFrame + ($interval / 1000))) {
                        $client->emit('error', array(
                            new \RuntimeException(
                                sprintf('No heart beat received since %s', $heartbeat->lastReceivedFrame)
                            )
                        ));
                    }
                });
            }


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

    private function sanitizeOptions($options)
    {
        if (!isset($options['host']) && !isset($options['vhost'])) {
            throw new \InvalidArgumentException('Either host or vhost options must be provided.');
        }

        return array_merge(array(
            'vhost'         => isset($options['host']) ? $options['host'] : null,
            'login'         => null,
            'passcode'      => null,
            'heartbeat-cx'  => 0,
            'heartbeat-cy'  => 0,
        ), $options);
    }

    public function generateReceiptId()
    {
        return mt_rand();
    }
}
