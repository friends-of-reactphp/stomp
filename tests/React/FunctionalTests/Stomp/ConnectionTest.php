<?php

namespace React\FunctionalTests\Stomp;

class ConnectionTest extends FunctionalTestCase
{
    /** @test */
    public function itShouldConnect()
    {
        $loop = $this->getEventLoop();
        $client = $this->getClient($loop);

        $phpunit = $this;
        $connected = false;

        $client
            ->connect()
            ->then(function () use ($loop, &$connected) {
                $connected = true;
                $loop->stop();
            }, function (\Exception $e) use ($phpunit) {
                $phpunit->fail('Connection should occur');
            });

        $loop->run();

        $this->assertTrue($connected);
    }

    /** @test */
    public function itShouldFailOnConnect()
    {
        $loop = $this->getEventLoop();
        $client = $this->getClient($loop, array(
            'login' => 'badidealogin',
            'passcode' => 'thereisnoprobabilitythatyouusethispassword'
        ));

        $phpunit = $this;
        $error = null;

        $client
            ->connect()
            ->then(function () use ($phpunit) {
                $phpunit->fail('Connection should occur');
            }, function ($e) use ($loop, &$error) {
                $error = $e;
                $loop->stop();
            });

        $loop->run();

        $this->assertInstanceOf('Exception', $error);
    }
}
