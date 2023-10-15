<?php

namespace React\Stomp\Io;

use React\Stomp\Protocol\Frame;
use React\Stomp\Protocol\Parser;
use Evenement\EventEmitter;
use React\Stream\WritableStreamInterface;

// $parser = new Parser();
// $input = new InputStream($parser);
// $input->on('frame', function ($frame) {
//     lulz
// });
// $conn->pipe($input);

final class InputStream extends EventEmitter implements WritableStreamInterface, InputStreamInterface
{
    protected $closed = false;
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

    public function end($data = null)
    {
        if (null !== $data) {
            $this->write($data);
        }

        $this->close();
    }

    public function isWritable()
    {
        return !$this->closed;
    }

    public function close()
    {
        if ($this->closed) {
            return;
        }

        $this->closed = true;
        $this->emit('close');
        $this->removeAllListeners();
    }

}
