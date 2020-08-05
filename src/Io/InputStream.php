<?php

namespace React\Stomp\Io;

use React\Stomp\Protocol\Frame;
use React\Stomp\Protocol\Parser;
use React\Stream\WritableStream;

// $parser = new Parser();
// $input = new InputStream($parser);
// $input->on('frame', function ($frame) {
//     lulz
// });
// $conn->pipe($input);

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
        if ($data === "\x0a") {
            $this->emit('heart-beat', [new Frame('MESSAGE\nHEART-BEAT')]);
            $data = '';
        }

        $data = $this->buffer.$data;
        list($frames, $data) = $this->parser->parse($data);
        $this->buffer = $data;

        foreach ($frames as $frame) {
            $this->emit('frame', array($frame));
        }
    }
}
