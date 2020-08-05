<?php

namespace React\Stomp\Protocol;

class Parser
{
    public $allowedBodyCommands = array(
        'SEND',
        'MESSAGE',
        'ERROR',
        'HEART-BEAT', // fake command name for heart-beat, original command in frame is empty string
    );

    public function parse($data)
    {
        $frames = array();

        while ($this->hasFullFrame($data)) {
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

    public function extractFrameData($data)
    {
        return explode("\x00", $data, 2);
    }

    public function parseFrameData($data)
    {
        $frame = new Frame();

        list($head, $body) = explode("\n\n", $data, 2);

        $lines = explode("\n", ltrim($head, "\n"));

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

        $this->setFakeHeartbeatFrame($frame);

        if ($frame->body && !in_array($frame->command, $this->allowedBodyCommands)) {
            throw new InvalidFrameException(sprintf("Frames of command '%s' must not have a body.", $frame->command));
        }

        return $frame;
    }

    private function setFakeHeartbeatFrame($frame)
    {
        if ($frame->command === '') {
            $frame->command = 'HEART-BEAT';
        }
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
