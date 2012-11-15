<?php

namespace React\Stomp;

use Evenement\EventEmitter;
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

// Events: ready, error
class Client extends EventEmitter
{
    private $acknowledgements;
    private $parser;
    private $packageProcessor;
    private $packageCreator;
    private $subscriptions = array();

    public function __construct(InputStreamInterface $input, OutputStreamInterface $output, array $options)
    {
        $state = new State();
        $this->packageProcessor = new IncomingPackageProcessor($state);
        $this->packageCreator = new OutgoingPackageCreator($state);

        $this->input = $input;
        $this->input->on('frame', array($this, 'handleFrameEvent'));
        $this->input->on('error', array($this, 'handleErrorEvent'));
        $this->output = $output;

        $this->sendConnectFrame($options);
    }

    public function sendConnectFrame($options)
    {
        $host = isset($options['vhost']) ? $options['vhost'] : $options['host'];
        $login = isset($options['login']) ? $options['login'] : null;
        $passcode = isset($options['passcode']) ? $options['passcode'] : null;

        $frame = $this->packageCreator->connect($host, $login, $passcode);
        $this->output->sendFrame($frame);
    }

    public function send($destination, $body, array $headers = array())
    {
        $frame = $this->packageCreator->send($destination, $body, $headers);
        $this->output->sendFrame($frame);
    }

    public function subscribe($destination, $callback, $ack = 'auto', array $headers = array())
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

    public function disconnect()
    {
        $receipt = $this->generateReceiptId();
        $frame = $this->packageCreator->disconnect($receipt);
        $this->output->sendFrame($frame);
    }

    public function handleFrameEvent(Frame $frame)
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
            $this->emit('ready');
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

    public function generateReceiptId()
    {
        return mt_rand();
    }
}
