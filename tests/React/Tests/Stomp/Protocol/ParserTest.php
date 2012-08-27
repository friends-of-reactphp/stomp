<?php

namespace React\Tests\Stomp\Protocol;

use React\Stomp\Protocol\Parser;
use React\Stomp\Protocol\InvalidFrameException;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    /** @test */
    public function itShouldParseASingleFrame()
    {
        $data = "MESSAGE
header1:value1
header2:value2

Body\x00";

        $parser = new Parser();
        list($frames, $data) = $parser->parse($data);

        $this->assertHasSingleFrame(
            'MESSAGE',
            array('header1' => 'value1', 'header2' => 'value2'),
            'Body',
            $data,
            $frames
        );
    }

    /** @test */
    public function itShouldAllowUtf8InHeaders()
    {
        $data = "MESSAGE
äöü:~

\x00";

        $parser = new Parser();
        list($frames, $data) = $parser->parse($data);

        $this->assertHasSingleFrame(
            'MESSAGE',
            array('äöü' => '~'),
            '',
            $data,
            $frames
        );
    }

    /** @test */
    public function itShouldUnescapeSpecialCharactersInHeaders()
    {
        $data = "MESSAGE
foo:bar\\nbaz
bazin\\nga:bar\\c\\\\

\x00";

        $parser = new Parser();
        list($frames, $data) = $parser->parse($data);

        $this->assertHasSingleFrame(
            'MESSAGE',
            array('foo' => "bar\nbaz", "bazin\nga" => 'bar:\\'),
            '',
            $data,
            $frames
        );
    }

    /**
    * @test
    * @expectedException React\Stomp\Protocol\InvalidFrameException
    * @expectedExceptionMessage Provided header 'foo:bar\r' contains undefined escape sequences.
    */
    public function itShouldRejectUndefinedEscapeSequences()
    {
        $data = "MESSAGE
foo:bar\\r

\x00";

        $parser = new Parser();
        $parser->parse($data);
    }

    /**
    * @test
    * @dataProvider provideFramesThatCanHaveABody
    */
    public function itShouldAllowCertainFramesToHaveABody($body, $data)
    {
        $parser = new Parser();
        list($frames, $data) = $parser->parse($data);

        $this->assertSame($body, $frames[0]->body);
    }

    public function provideFramesThatCanHaveABody()
    {
        return array(
            array('Body', "SEND\nfoo:bar\n\nBody\x00"),
            array('Body', "MESSAGE\nfoo:bar\n\nBody\x00"),
            array('Body', "ERROR\nfoo:bar\n\nBody\x00"),
        );
    }

    /**
    * @test
    * @dataProvider provideFrameCommandsThatMustNotHaveABody
    */
    public function itShouldRejectOtherFramesWithBody($command)
    {
        $data = "$command\nfoo:bar\n\nBody\x00";

        $parser = new Parser();

        try {
            $parser->parse($data);
            $this->fail('Expected exception of type React\Stomp\Protocol\InvalidFrameException.');
        } catch (InvalidFrameException $e) {
            $expected = sprintf("Frames of command '%s' must not have a body.", $command);
            $this->assertSame($expected, $e->getMessage());
        }
    }

    /**
    * @test
    * @dataProvider provideFrameCommandsThatMustNotHaveABody
    */
    public function itShouldAcceptOtherFramesWithoutBody($command)
    {
        $data = "$command\nfoo:bar\n\n\x00";

        $parser = new Parser();
        $parser->parse($data);
    }

    public function provideFrameCommandsThatMustNotHaveABody()
    {
        return array(
            array('CONNECT'),
            array('CONNECTED'),
            array('BEGIN'),
            array('DISCONNECT'),
            array('FOOBAR'),
        );
    }

    /** @test */
    public function itShouldNotTrimHeaders()
    {
        $parser = new Parser();
        $data = "MESSAGE\nfoo   :   bar baz   \n\n\x00";

        $parser = new Parser();
        list($frames, $data) = $parser->parse($data);

        $this->assertHasSingleFrame(
            'MESSAGE',
            array('foo   ' => '   bar baz   '),
            '',
            $data,
            $frames
        );
    }

    /** @test */
    public function itShouldPickTheFirstHeaderValueOfRepeatedHeaderNames()
    {
        $parser = new Parser();
        $data = "MESSAGE
foo:bar
foo:baz

\x00";

        $parser = new Parser();
        list($frames, $data) = $parser->parse($data);

        $this->assertHasSingleFrame(
            'MESSAGE',
            array('foo' => 'bar'),
            '',
            $data,
            $frames
        );
    }

    public function assertHasSingleFrame($command, $headers, $body, $data, $frames)
    {
        $this->assertCount(1, $frames);
        $this->assertInstanceOf('React\Stomp\Protocol\Frame', $frames[0]);
        $this->assertSame($command, $frames[0]->command);
        $this->assertSame($headers, $frames[0]->headers);
        $this->assertSame($body, $frames[0]->body);

        $this->assertSame('', $data);
    }
}
