#!/usr/bin/env bash
set -e;

# echo "Setting up test environment";
# cp -vr /project /testenv;

# echo "Re-installing dependencies";
# cd /testenv;
# rm -rf vendor;
# composer update;
cd /project;

vendor/bin/phpunit --coverage-text;

echo "Running RabbitMQ tests";
service rabbitmq-server start;
STOMP_PROVIDER=rabbitmq vendor/bin/phpunit -c phpunit-functional.xml.dist;
service rabbitmq-server stop;

echo "Running ActiveMQ tests";
service activemq start;
SKIP_AUTH_CHECKS=1 STOMP_PROVIDER=activemq vendor/bin/phpunit -c phpunit-functional.xml.dist;
service activemq stop;
