<?php

namespace React\Functional\Stomp;

use React\Promise\When;

class AckTest extends FunctionalTestCase
{
    /** @test */
    public function itShouldReceiveAgainNackedMessages()
    {
        $loop = $this->getEventLoop();
        $client1 = $this->getClient($loop);
        $client2 = $this->getClient($loop);

        $counter = 0;

        When::all(array(
                $client1->connect(),
                $client2->connect(),
            ),
            function () use ($client1, $client2, $loop, &$counter) {
                $callback = function ($frame, $resolver) use ($loop, &$counter) {
                    if (0 === $counter) {
                        $resolver->nack();
                    } else {
                        $resolver->ack();
                        $loop->stop();
                    }
                    $counter++;
                };

                $client1->subscribeWithAck('/topic/foo', 'client-individual', $callback);
                $client2->subscribeWithAck('/topic/foo', 'client-individual', $callback);

                $client1->send('/topic/foo', 'le message à la papa');
            }
        );

        $loop->run();
        $this->assertEquals(2, $counter);
    }
}
