<?php

namespace React\Stomp\Client;

use React\Stomp\Protocol\Frame;
use React\Stomp\Exception\ServerErrorException;
use React\Stomp\Exception\UnexpectedFrameException;
use Evenement\EventEmitter;

/**
 * @event frame
 * @event error
 * @event connected
 * @event disconnected
 */
class IncomingPackageProcessor extends EventEmitter
{
    private $state;

    public function __construct(State $state)
    {
        $this->state = $state;
    }

    /**
     * Feed frame from the server
     *
     * @return An array of commands to be executed by the caller.
     *
     * @throws ServerErrorException|UnexpectedFrameException In case of an exception, the STOMP connection should be considered close
     */
    public function receiveFrame(Frame $frame)
    {
        if ('ERROR' === $frame->command) {
            $this->emit('error', array(new ServerErrorException($frame)));
            return;
        }

        if ($this->state->isConnecting()) {
            if ('CONNECTED' !== $frame->command) {
                throw new UnexpectedFrameException($frame, sprintf("Received frame with command '%s', expected 'CONNECTED'.", $frame->command));
            }

            $this->state->doneConnecting(
                $frame->getHeader('session'),
                $frame->getHeader('server')
            );

            $this->emit('connected', array($frame));

            return;
        }

        if ('CONNECTED' === $frame->command) {
            throw new UnexpectedFrameException($frame, sprintf("Received 'CONNECTED' frame outside a connecting window."));
        }

        if ($this->state->isDisconnecting()) {
            if ('RECEIPT' === $frame->command && $this->state->isDisconnectionReceipt($frame->getHeader('receipt-id'))) {
                $this->state->doneDisconnecting();
                $this->emit('disconnected', array($frame));
                return;
            }
        }

        if (!$this->state->isDisconnected()) {
            $this->emit('frame', array($frame));
        } else {
            $this->emit('error', array(new UnexpectedFrameException($frame, sprintf('Unexpected frame %s received, STOMP connection is disconnected', $frame->command))));
        }
    }
}
