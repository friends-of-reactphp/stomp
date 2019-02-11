<?php

namespace React\Stomp;

use React\Stomp\Protocol\Parser;

class FrameBuffer
{
    private $parser;
    private $buffer = '';

    public function __construct()
    {
        $this->parser = new Parser();
    }

    public function addToBuffer($data)
    {
        $this->buffer .= $data;

        return $this;
    }

    public function pullFrames()
    {
        $data = $this->buffer;

        list($frames, $data) = $this->parser->parse($data);

        $this->buffer = $data;

        return $frames;
    }
}
