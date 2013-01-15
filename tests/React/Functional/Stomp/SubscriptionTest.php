<?php

namespace React\Functional\Stomp;

class SubscriptionTest extends FunctionalTestCase
{
    /** @test */
    public function itShouldReceiveOnSubscribedTopicsWhatItSends()
    {
        $loop = $this->getEventLoop();
        $client = $this->getClient($loop);

        $phpunit = $this;
        $received = false;

        $client
            ->connect()
            ->then(function ($client) use ($loop, $phpunit, &$received) {
                $client->subscribe('/topic/foo', function ($frame) use ($phpunit, &$received, $loop) {
                    $phpunit->assertEquals('le message à la papa', $frame->body);
                    $received = true;
                    $loop->stop();
                });

                $client->send('/topic/foo', 'le message à la papa');
            });

        $loop->run();
        $this->assertTrue($received);
    }
}
