FROM php:${VERSION}
MAINTAINER eater <github@eaterofco.de>
# Install RabbitMQ repo
RUN echo 'deb http://www.rabbitmq.com/debian/ testing main' | tee /etc/apt/sources.list.d/rabbitmq.list
RUN curl -Ss https://www.rabbitmq.com/rabbitmq-release-signing-key.asc | apt-key add -
# Update package list
RUN apt update
# Install RabbitMQ and ActiveMQ
RUN apt install -yf rabbitmq-server activemq
# Enable STOMP in RabbitMQ
RUN rabbitmq-plugins enable rabbitmq_stomp
# Configure STOMP instance for ActiveMQ
COPY tests/utils/activemq.xml /etc/activemq/instances-available/main/
# Enable an instance for ActiveMQ
RUN ln -s /etc/activemq/instances-available/main /etc/activemq/instances-enabled/main
# Install composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php -r "if (hash_file('SHA384', 'composer-setup.php') === '669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); exit(1); } echo PHP_EOL;"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"
VOLUME /project