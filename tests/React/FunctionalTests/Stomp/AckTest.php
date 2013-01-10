<?php

namespace React\FunctionalTests\Stomp;

class AckTest extends FunctionalTestCase
{
    /** @test */
    public function itShouldReceiveAgainNackedMessages()
    {
        $this->markTestSkipped('Temporary disabling this test as Apollo hang on it');

        $loop = $this->getEventLoop();
        $client = $this->getClient($loop);

        $counter = 0;

        $client
            ->connect()
            ->then(function ($client) use ($loop, &$counter) {
                $client->subscribeWithAck('/topic/foo', 'client-individual', function ($frame, $resolver) use ($loop, &$counter) {
                    if (0 === $counter) {
                        $resolver->nack();
                    } else {
                        $resolver->ack();
                        $loop->stop();
                    }
                    $counter++;
                });

                $client->send('/topic/foo', 'le message à la papa');
            });

        $loop->run();
        $this->assertEquals(2, $counter);
    }
}
