<?php

namespace React\Stomp\Io;

use Evenement\EventEmitter;
use React\Stomp\Protocol\Parser;
use React\Stream\WritableStreamInterface;

// $parser = new Parser();
// $input = new InputStream($parser);
// $input->on('frame', function ($frame) {
//     lulz
// });
// $conn->pipe($input);

class InputStream extends EventEmitter implements WritableStreamInterface
{
    private $writable = true;
    private $unparsed = '';
    private $parser;

    public function __construct(Parser $parser)
    {
        $this->parser = $parser;
    }

    public function isWritable()
    {
        return $this->writable;
    }

    public function write($data)
    {
        if (!$this->writable) {
            return;
        }

        $data = $this->unparsed.$data;
        list($frames, $data) = $this->parser->parse($data);
        $this->unparsed = $data;

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

    public function close()
    {
        $this->writable = false;

        $this->emit('close', array($this));
        $this->removeAllListeners();
    }
}
