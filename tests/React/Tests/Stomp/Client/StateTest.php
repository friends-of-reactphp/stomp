<?php

namespace React\Tests\Stomp\Client;

use React\Stomp\Client\State;
use React\Tests\Stomp\TestCase;

class StateTest extends TestCase
{
    /** @test */
    public function itShouldBeDisconnectedOnConstruction()
    {
        $state = new State();
        $this->assertTrue($state->isDisconnected());
    }

    /**
     * @dataProvider provideStatusesAndMethod
     * @test
     */
    public function itShouldProvideSomeMethodsToKnwoTheStatus($status, $method)
    {
        $state = new State($status);
        $this->assertTrue(call_user_func(array($state, $method)));

        $methods = array(
            'isConnecting',
            'isConnected',
            'isDisconnected',
            'isDisconnecting',
        );

        unset($methods[array_search($method, $methods)]);

        foreach($methods as $method) {
            $this->assertFalse(call_user_func(array($state, $method)));
        }
    }

    /**
     * @dataProvider provideInvalidTransitions
     * @test
     */
    public function itShouldThrowAnExceptionOnInvalidTransitions($from, $to)
    {
        $state = new State($from);

        try {
            $state->setStatus($to);
            $this->fail('A runtime exception should have been raised');
        } catch (\RuntimeException $e) {
            $this->assertEquals(sprintf('Unexpected STOMP connection transition from %s to %s', $from, $to), $e->getMessage());
        }
    }

    /** @test */
    public function itShouldBeConnectingFromDisconnectedToConnecting()
    {
        $state = new State(State::STATUS_DISCONNECTED);
        $state->setStatus(State::STATUS_CONNECTING);

        $this->assertTrue($state->isConnecting());

        $this->assertNull($state->server);
        $this->assertNull($state->session);
        $this->assertNull($state->receipt);
    }

    /** @test */
    public function itShouldBeConnectedFromConnectingToConnected()
    {
        $server = 'react/stomp-server';
        $session = 12345;

        $state = new State(State::STATUS_CONNECTING);
        $state->setStatus(State::STATUS_CONNECTED, $session, $server);

        $this->assertTrue($state->isConnected());

        $this->assertEquals($server, $state->server);
        $this->assertEquals($session, $state->session);
        $this->assertNull($state->receipt);
    }

    /** @test */
    public function itShouldNotBeConnectedFromConnectingToDisconnected()
    {
        $state = new State(State::STATUS_CONNECTING);
        $state->setStatus(State::STATUS_DISCONNECTED);

        $this->assertTrue($state->isDisconnected());

        $this->assertNull($state->server);
        $this->assertNull($state->session);
        $this->assertNull($state->receipt);
    }

    /** @test */
    public function itShouldBeDisconnectingFromConnectedToDisconnecting()
    {
        $state = new State(State::STATUS_CONNECTED);
        $state->server = 'react/stomp-server';
        $state->session = 12345;

        $receipt = mt_rand(1000000, 9999999);

        $state->setStatus(State::STATUS_DISCONNECTING, $receipt);

        $this->assertTrue($state->isDisconnecting());

        $this->assertEquals('react/stomp-server', $state->server);
        $this->assertEquals(12345, $state->session);
        $this->assertEquals($receipt, $state->receipt);
    }

    /** @test */
    public function itShouldBeDisconnectedFromConnectedToDisconnected()
    {
        $state = new State(State::STATUS_CONNECTED);
        $state->server = 'react/stomp-server';
        $state->session = 12345;

        $state->setStatus(State::STATUS_DISCONNECTED);

        $this->assertTrue($state->isDisconnected());

        $this->assertNull($state->server);
        $this->assertNull($state->session);
        $this->assertNull($state->receipt);
    }

    /** @test */
    public function itShouldBeDisconnectedFromDisconnectingToDisconnected()
    {
        $state = new State(State::STATUS_DISCONNECTING);
        $state->server = 'react/stomp-server';
        $state->session = 12345;
        $state->receipt = mt_rand(1000000, 9999999);

        $state->setStatus(State::STATUS_DISCONNECTED);

        $this->assertTrue($state->isDisconnected());

        $this->assertNull($state->server);
        $this->assertNull($state->session);
        $this->assertNull($state->receipt);
    }

    public function provideInvalidTransitions()
    {
        return array(
            array(State::STATUS_DISCONNECTED, State::STATUS_CONNECTED),
            array(State::STATUS_DISCONNECTED, State::STATUS_DISCONNECTING),
            array(State::STATUS_CONNECTING, State::STATUS_DISCONNECTING),
            array(State::STATUS_CONNECTED, State::STATUS_CONNECTING),
            array(State::STATUS_DISCONNECTING, State::STATUS_CONNECTING),
            array(State::STATUS_DISCONNECTING, State::STATUS_CONNECTED),
        );
    }

    public function provideStatusesAndMethod()
    {
        return array(
            array(State::STATUS_CONNECTED, 'isConnected'),
            array(State::STATUS_CONNECTING, 'isConnecting'),
            array(State::STATUS_DISCONNECTED, 'isDisconnected'),
            array(State::STATUS_DISCONNECTING, 'isDisconnecting'),
        );
    }
}
