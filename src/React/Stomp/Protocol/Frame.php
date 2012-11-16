<?php

namespace React\Stomp\Protocol;

class Frame implements FrameInterface
{
    public $command;
    public $headers = array();
    public $body;

    public function __construct($command = null, array $headers = array(), $body = '')
    {
        $this->command = $command;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getHeader($name, $defaultValue = null)
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : $defaultValue;
    }

    public function dump()
    {
        return  $this->command."\n".
                $this->dumpHeaders()."\n".
                $this->body.
                "\x00";
    }

    private function dumpHeaders()
    {
        $dumped = '';

        foreach ($this->headers as $name => $value) {
            $name   = $this->escapeHeaderValue($name);
            $value  = $this->escapeHeaderValue($value);

            $dumped .= "$name:$value\n";
        }

        return $dumped;
    }

    private function escapeHeaderValue($value)
    {
        return strtr($value, array(
            "\n"    => '\n',
            ':'     => '\c',
            '\\'    => '\\\\',
        ));
    }

    public function __toString()
    {
        return $this->dump();
    }
}
