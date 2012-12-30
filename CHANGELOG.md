CHANGELOG
=========

* 0.1.3 (201x-xx-xx)

  * Support for PHP >=5.3.3, <=5.3.8
    (OutputStream is extending ReadableStream and implements
    OutputStreamInterface that both have the `close` method in common.)

* 0.1.2 (2012-12-27)

  * Connection error handling (@romainneutron)
  * Support for Apollo and ActiveMQ (@romainneutron)

* 0.1.1 (2012-12-26)

  * API for ACK/NACK with `subscribe` (@romainneutron)
  * Client::isConnected method (@romainneutron)
  * Introduce promise-based `connect` API

* 0.1.0 (2012-11-14)

  * First tagged release
