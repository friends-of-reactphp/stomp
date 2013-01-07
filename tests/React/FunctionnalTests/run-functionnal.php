#!/usr/bin/env php
<?php

require __DIR__ . '/../../../examples/config/probe.php';

copy(__DIR__ . '/../../../examples/config/' . $argv[1] . '.php', __DIR__ . '/config.php');

$before = $after = array();

switch ($argv[1]) {
    case 'rabbitmq':
        $before[] = 'sudo rabbitmq-plugins enable rabbitmq_stomp';
        $before[] = 'sudo service rabbitmq-server start';
        $after[] = 'sudo service rabbitmq-server stop';
        break;
    case 'apollo':
        $before[] = __DIR__ . '/../../../apache-apollo-1.5/apollo/bin/apollo-broker-service start';
        $before[] = 'sleep 6';
        $after[] = __DIR__ . '/../../../apache-apollo-1.5/apollo/bin/apollo-broker-service stop';
        break;
}

foreach($before as $command) {
    exec($command);
}

passthru('phpunit -c ' . __DIR__ . '/../../../phpunit-functionnal.xml.dist', $return_var);

foreach($after as $command) {
    exec($command);
}

unlink(__DIR__ . '/config.php');

exit($return_var);
