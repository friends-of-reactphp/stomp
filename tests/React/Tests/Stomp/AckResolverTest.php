<?php

namespace React\Tests\Stomp;

use React\Stomp\AckResolver;

class AckResolverTest extends TestCase
{
    /**
     * @test
     * @dataProvider provideAckNackCombinaisons
     * @expectedException \RuntimeException
     */
    public function doubleAckIsForbidden($first, $second)
    {
        $client = $this->getMockBuilder('React\Stomp\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $ackResolver = new AckResolver($client, 12345, 54321);

        $ackResolver->{$first}();
        $ackResolver->{$second}();
    }

    public function provideAckNackCombinaisons()
    {
        return array(
            array('ack', 'ack'),
            array('nack', 'nack'),
            array('ack', 'nack'),
            array('nack', 'ack'),
        );
    }

    /** @test */
    public function ackShouldAcknowledge()
    {
        $client = $this->getMockBuilder('React\Stomp\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $capturedSubId = $capturedMsgId = $capturedHeaders = null;

        $client
            ->expects($this->exactly(1))
            ->method('ack')
            ->will($this->returnCallback(function ($subId, $msgId, $headers)
                use (&$capturedSubId, &$capturedMsgId, &$capturedHeaders) {
                $capturedHeaders = $headers;
                $capturedMsgId = $msgId;
                $capturedSubId = $subId;
            }));

        $ackResolver = new AckResolver($client, 12345, 54321);
        $ackResolver->ack(array('foo' => 'bar'));

        $this->assertEquals(array('foo' => 'bar'), $capturedHeaders);
        $this->assertEquals(54321, $capturedMsgId);
        $this->assertEquals(12345, $capturedSubId);
    }

    /** @test */
    public function nackShouldNegativeAcknowledge()
    {
        $client = $this->getMockBuilder('React\Stomp\Client')
            ->disableOriginalConstructor()
            ->getMock();

        $capturedSubId = $capturedMsgId = $capturedHeaders = null;

        $client
            ->expects($this->exactly(1))
            ->method('nack')
            ->will($this->returnCallback(function ($subId, $msgId, $headers)
                use (&$capturedSubId, &$capturedMsgId, &$capturedHeaders) {
                $capturedHeaders = $headers;
                $capturedMsgId = $msgId;
                $capturedSubId = $subId;
            }));

        $ackResolver = new AckResolver($client, 12345, 54321);
        $ackResolver->nack(array('foo' => 'bar'));

        $this->assertEquals(array('foo' => 'bar'), $capturedHeaders);
        $this->assertEquals(54321, $capturedMsgId);
        $this->assertEquals(12345, $capturedSubId);
    }
}
