<?php

namespace React\Stomp\Protocol;

class Parser
{
    public $allowedBodyCommands = array('SEND', 'MESSAGE', 'ERROR');

    public function parse($data)
    {
        $frames = array();

        while (($beat = $this->hasHeartbeat($data)) || $this->hasFullFrame($data)) {
            if ($beat) {
                $frames[] = new HeartbeatFrame();
                $data = (string) substr($data, 1);
                continue;
            }
            list($frameData, $data) = $this->extractFrameData($data);
            $frame = $this->parseFrameData($frameData);
            $frames[] = $frame;
        }

        return array($frames, $data);
    }

    public function hasFullFrame($data)
    {
        return false !== strpos($data, "\x00");
    }

    public function hasHeartbeat($data)
    {
        return "\x0A" === substr($data, 0, 1);
    }

    public function extractFrameData($data)
    {
        return explode("\x00", $data, 2);
    }

    public function parseFrameData($data)
    {
        $frame = new Frame();

        list($head, $body) = explode("\n\n", $data, 2);

        $lines = explode("\n", $head);

        $frame->command = array_shift($lines);

        while ($line = array_shift($lines)) {
            if ($this->hasUndefinedEscapeSequences($line)) {
                throw new InvalidFrameException(sprintf("Provided header '%s' contains undefined escape sequences.", $line));
            }

            list($name, $value) = explode(':', $line, 2);
            $name   = $this->unescapeHeaderValue($name);
            $value  = $this->unescapeHeaderValue($value);

            if (isset($frame->headers[$name])) {
                continue;
            }

            $frame->headers[$name] = $value;
        }

        $frame->body = $body;

        if ($frame->body && !in_array($frame->command, $this->allowedBodyCommands)) {
            throw new InvalidFrameException(sprintf("Frames of command '%s' must not have a body.", $frame->command));
        }

        return $frame;
    }

    public function hasUndefinedEscapeSequences($line)
    {
        return (bool) preg_match('/\\\\[^nc\\\\]/s', $line);
    }

    public function unescapeHeaderValue($value)
    {
        return strtr($value, array(
            '\\n' => "\n",
            '\\c' => ':',
            '\\\\'  => '\\',
        ));
    }
}
