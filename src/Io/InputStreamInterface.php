<?php

namespace React\Stomp\Io;

use Evenement\EventEmitterInterface;

/**
 * @event frame
 * @event error
 */
interface InputStreamInterface extends EventEmitterInterface
{
}
