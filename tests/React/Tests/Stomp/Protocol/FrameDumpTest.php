<?php

namespace React\Tests\Stomp\Protocol;

use React\Stomp\Protocol\Frame;

class FrameDumpTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @test
     * @dataProvider provideFramesAndTheirWireData
     */
    public function toStringShouldDumpMessageToWireProtocol($expected, Frame $frame)
    {
        $this->assertSame($expected, (string) $frame);
    }

    public function provideFramesAndTheirWireData()
    {
        return array(
            array(
                "CONNECT\naccept-version:1.1\nhost:stomp.github.org\n\n\x00",
                new Frame('CONNECT', array('accept-version' => '1.1', 'host' => 'stomp.github.org')),
            ),
            array(
                "MESSAGE\nheader1:value1\nheader2:value2\n\nBody\x00",
                new Frame('MESSAGE', array('header1' => 'value1', 'header2' => 'value2'), 'Body'),
            ),
            array(
                "MESSAGE\nfoo:bar\\nbaz\nbaz:baz\\cin\\\\ga\n\n\x00",
                new Frame('MESSAGE', array('foo' => "bar\nbaz", 'baz' => 'baz:in\\ga')),
            ),
        );
    }
}
