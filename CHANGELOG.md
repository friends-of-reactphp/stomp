CHANGELOG
=========

* 0.1.3 (2013-01-15)

  * Support for PHP >=5.3.3, <=5.3.8
    OutputStream extends ReadableStream and implements
    OutputStreamInterface that both have the `close` method in common
    (@romainneutron)
  * Functional test suite (@romainneutron)
  * Handle connection timeouts and failures (@romainneutron)
  * BC break: Client constructor takes an event loop argument. If you use the
    factory, you are unaffected.

* 0.1.2 (2012-12-27)

  * Connection error handling (@romainneutron)
  * Support for Apollo and ActiveMQ (@romainneutron)

* 0.1.1 (2012-12-26)

  * API for ACK/NACK with `subscribe` (@romainneutron)
  * Client::isConnected method (@romainneutron)
  * Introduce promise-based `connect` API

* 0.1.0 (2012-11-14)

  * First tagged release
