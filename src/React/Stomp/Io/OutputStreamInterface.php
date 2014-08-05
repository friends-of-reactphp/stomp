<?php

namespace React\Stomp\Io;

use React\Stomp\Protocol\Frame;

/**
 * PHP <= 5.3.8 does not support a close method in this interface.
 *
 * OutputStream is extending ReadableStream and implements OutputStreamInterface
 * that both have the `close` method in common.
 */
interface OutputStreamInterface extends \React\Stream\ReadableStreamInterface
{
    public function sendFrame(Frame $frame);
}
