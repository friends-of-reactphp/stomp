<?php

namespace React\Stomp\Io;

use React\Stomp\Protocol\Parser;
use React\Stream\WritableStream;

/**
 * @event frame
 */
class InputStream extends WritableStream implements InputStreamInterface
{
    private $buffer = '';
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function write($data)
    {
        $data = $this->buffer.$data;
        list($frames, $data) = $this->parser->parse($data);
        $this->buffer = $data;

        foreach ($frames as $frame) {
            $this->emit('frame', array($frame));
        }
    }
}
