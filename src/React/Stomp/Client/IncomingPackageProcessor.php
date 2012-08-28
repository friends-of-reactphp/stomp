<?php

namespace React\Stomp\Client;

use React\Stomp\Protocol\Frame;
use React\Stomp\Client\Command\CloseCommand;
use React\Stomp\Client\Command\ConnectionEstablishedCommand;
use React\Stomp\Client\Command\NullCommand;

class IncomingPackageProcessor
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
     */
    public function receiveFrame(Frame $frame)
    {
        if ('ERROR' === $frame->command) {
            throw new ServerErrorException($frame);
        }

        if ($this->state->isConnecting()) {
            if ('CONNECTED' !== $frame->command) {
                throw new InvalidFrameException(sprintf("Received frame with command '%s', expected 'CONNECTED'."));
            }

            $this->state->doneConnecting(
                $frame->getHeader('session'),
                $frame->getHeader('server')
            );

            return new ConnectionEstablishedCommand();
        }

        if (State::STATUS_DISCONNECTING === $this->state->status) {
            if ('RECEIPT' === $frame->command && $this->state->isDisconnectionReceipt($frame->getHeader('receipt-id'))) {
                $this->state->doneDisconnecting();

                return new CloseCommand();
            }
        }

        return new NullCommand();
    }
}
