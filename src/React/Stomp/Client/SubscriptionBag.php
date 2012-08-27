<?php

namespace React\Stomp\Client;

class SubscriptionBag
{
    public $i = 0;
    public $data = array();

    public function add($destination, $ack)
    {
        $subscriptionId = $this->i;
        $this->data[$subscriptionId] = array(
            'destination'   => $destination,
            'ack'           => $ack,
        );

        $this->i++;

        return $subscriptionId;
    }
}
