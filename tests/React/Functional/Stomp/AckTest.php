<?php

namespace React\Functional\Stomp;

use React\Promise;

class AckTest extends FunctionalTestCase
{
    /** @test */
    public function itShouldReceiveAgainNackedMessages()
    {
        $loop = $this->getEventLoop();
        $client1 = $this->getClient($loop);
        $client2 = $this->getClient($loop);
        $phpunit = $this;

        $counter = 0;

        Promise\all(array(
            $client1->connect(1),
            $client2->connect(1),
        ))->then(
            function () use ($client1, $client2, $loop, &$counter, $phpunit) {
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
                # Give some space to actually subscribe server side
                $loop->addTimer(5, function () use ($loop, $client1) {
                    $client1->send('/topic/foo', 'le message à la papa');
                });


                $loop->addTimer(10, function () use ($loop, $phpunit) {
                    $loop->stop();
                    $phpunit->fail("Didn't receive ack'd message in time");
                });
            }
        );

        $loop->run();
        $this->assertEquals(2, $counter);
    }
}
