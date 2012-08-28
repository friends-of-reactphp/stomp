# STOMP Protocol Specification, Version 1.1

{:toc:2-5}

## Abstract

STOMP is a simple interoperable protocol designed for asynchronous message
passing between clients via mediating servers. It defines a text based
wire-format for messages passed between these clients and servers.

STOMP has been in active use for several years and is supported by many
message brokers and client libraries. This specification defines the STOMP 1.1
protocol and is an update to [STOMP 1.0](stomp-specification-1.0.html).

Please send feedback to the stomp-spec@googlegroups.com mailing list.

## Overview

### Background

STOMP arose from a need to connect to enterprise message brokers from
scripting languages such as Ruby, Python and Perl.  In such an
environment it is typically logically simple operations that are
carried out such as 'reliably send a single message and disconnect'
or 'consume all messages on a given destination'.

It is an alternative to other open messaging protocols such as AMQP
and  implementation specific wire protocols used in JMS brokers such
as OpenWire.  It distinguishes itself by covering a small subset of
commonly used messaging operations rather than providing a
comprehensive messaging API.

More recently STOMP has matured into a protocol which can be used past
these simple use cases in terms of the wire-level features it now
offers, but still maintains its core design principles of simplicity
and interoperability.

### Protocol Overview

STOMP is a frame based protocol, with frames modelled on HTTP. A frame
consists of a command, a set of optional headers and an optional body. STOMP
is text based but also allows for the transmission of binary messages. The
default encoding for STOMP is UTF-8, but it supports the specification of
alternative encodings for message bodies.

A STOMP server is modelled as a set of destinations to which messages can be
sent. The STOMP protocol treats destinations as opaque string and their syntax
is server implementation specific. Additionally STOMP does not define what the
delivery semantics of destinations should be. The delivery, or "message
exchange", semantics of destinations can vary from server to server and even
from destination to destination. This allows servers to be creative with the
semantics that they can support with STOMP.

A STOMP client is a user-agent which can act in two (possibly simultaneous)
modes:

* As a producer, sending messages to a destination on the server via a `SEND`
  frame

* As a consumer, sending a `SUBSCRIBE` frame for a given destination and
  receiving messages from the server as `MESSAGE` frames.

### Changes in the Protocol

STOMP 1.1 is designed to be backwards compatible with STOMP 1.0 while
introducing several new features not present in STOMP 1.0:

* protocol negotiation to allow for interoperability between clients and
  servers supporting successive versions of STOMP

* heartbeats to allow for reliable detection of disconnecting clients and
  servers

* `NACK` frames for negative acknowledgment of message receipt

* Support for virtual hosting

### Design Philosophy

The main philosophies driving the design of STOMP are simplicity and
interoperability.

STOMP is designed to be a lightweight protocol that is easy to implement both
on the client and server side in a wide range of languages. This implies, in
particular, that there are not many constraints on the architecture of servers
and many features such as destination naming and reliability semantics are
implementation specific.

In this specification we will note features of servers which are not
explicitly defined by STOMP 1.1. You should consult your STOMP server's
documentation for the implementation specific details of these features.

## Conformance

The key words "MUST", "MUST NOT", "REQUIRED", "SHALL", "SHALL NOT", "SHOULD",
"SHOULD NOT", "RECOMMENDED", "MAY", and "OPTIONAL" in this document are to be
interpreted as described in RFC 2119.

Implementations may impose implementation-specific limits on unconstrained
inputs, e.g. to prevent denial of service attacks, to guard against running
out of memory, or to work around platform-specific limitations.

The conformance classes defined by this specification are STOMP clients and
STOMP servers.

## STOMP Frames

STOMP is a frame based protocol which assumes a reliable 2-way streaming
network protocol (such as TCP) underneath. The client and server will
communicate using STOMP frames sent over the stream. A frame's structure
looks like:

    COMMAND
    header1:value1
    header2:value2

    Body^@

The frame starts with a command string terminated by a newline. Following the
command are one or more header entries in `<key>:<value>` format. Each header
entry is terminated by a newline. A blank line indicates the end of the
headers and the beginning of the body. The body is then followed by the null
byte (0x00). The examples in this document will use `^@`, control-@ in ASCII,
to represent the null byte. The null byte can be optionally followed by
multiple newlines. For more details, on how to parse STOMP frames, see the
[Augmented BNF](#Augmented_BNF) section of this document.

All commands and header names referenced in this document are case sensitive.

### Value Encoding

The commands and headers are encoded in UTF-8. All frames except the `CONNECT`
and `CONNECTED` frames will also escape any colon or newline octets found in
the resulting UTF-8 encoded headers.

Escaping is needed to allow header keys and values to contain those frame
header delimiting octets as values. The `CONNECT` and `CONNECTED` frames do not
escape the colon or newline octets in order to remain backward compatible with
STOMP 1.0.

C style string literal escapes are used to encode any colons and newlines that
are found within the UTF-8 encoded headers. When decoding frame headers, the
following transformations MUST be applied:

* `\n` (octet 92 and 110) translates to newline (octet 10)
* `\c` (octet 92 and 99) translates to `:` (octet 58)
* `\\` (octet 92 and 92) translates to `\` (octet 92)

Undefined escape sequences such as `\r` (octet 92 and 114) MUST be treated as
a fatal protocol error. Conversely when encoding frame headers, the reverse
transformation MUST be applied.

Only the `SEND`, `MESSAGE`, and `ERROR` frames can have a body. All other
frames MUST NOT have a body.

The STOMP 1.0 specification included many example frames with padding in the
headers and many servers and clients were implemented to trim or pad header
values. This causes problems if applications want to send headers that SHOULD
not get trimmed. In STOMP 1.1, clients and servers MUST never trim or pad
headers with spaces.

### Size Limits

To prevent malicious clients from exploiting memory allocation in a
server, servers MAY place maximum limits on:

* the number of frame headers allowed in a single frame
* the maximum length of header lines
* the maximum size of a frame body

If these limits are exceeded the server SHOULD send the client an `ERROR`
frame and disconnect the client.

### Repeated Header Entries

Since messaging systems can be organized in store and forward topologies,
similar to SMTP, a message may traverse several messaging servers before
reaching a consumer. The intermediate server MAY 'update' header values by
either prepending headers to the message or modifying a header in-place in
the message.

If the client receives repeated frame header entries, only the first header
entry SHOULD be used as the value of header entry. Subsequent values are only
used to maintain a history of state changes of the header. For example, if the
client receives:

    MESSAGE
    foo:World
    foo:Hello

    ^@

The value of the `foo` header is just `World`.

## Connecting

A STOMP client initiates the stream or TCP connection to the server by sending
the `CONNECT` frame:

    CONNECT
    accept-version:1.1
    host:stomp.github.org

    ^@

If the server accepts the connection attempt it will respond with a
`CONNECTED` frame:

    CONNECTED
    version:1.1

    ^@

The server can reject any connection attempt. The server SHOULD respond back
with an `ERROR` frame listing why the connection was rejected and then close
the connection. STOMP servers MUST support clients which rapidly connect and
disconnect. This implies a server will likely only allow closed connections
to linger for short time before the connection is reset. This means that a
client may not receive the `ERROR` frame before the socket is reset.

### CONNECT or STOMP Frame

STOMP servers SHOULD handle a `STOMP` frame in the same manner as a `CONNECT`
frame. STOMP 1.1 clients SHOULD continue to use the `CONNECT` command to
remain backward compatible with STOMP 1.0 servers.

Clients that use the `STOMP` frame instead of the `CONNECT` frame will only
be able to connect to STOMP 1.1 servers but the advantage is that a protocol
sniffer/discriminator will be able to differentiate the STOMP connection from
an HTTP connection.

STOMP 1.1 clients MUST set the following headers:

* `accept-version` : The versions of the STOMP protocol the client supports.
  See [Protocol Negotiation](#protocol_negotiation) for more details.

* `host` : The name of a virtual host that the client wishes to connect to.
  It is recommended clients set this to the host name that the socket
  was established against, or to any name of their choosing. If this
  header does not match a known virtual host, servers supporting virtual
  hosting MAY select a default virtual host or reject the connection.

STOMP 1.1 clients MAY set the following headers:

* `login` : The user id used to authenticate against a secured STOMP server.

* `passcode` : The password used to authenticate against a secured STOMP
  server.

### CONNECTED Frame

STOMP 1.1 servers MUST set the following headers:

* `version` : The version of the STOMP protocol the session will be using.
  See [Protocol Negotiation](#protocol_negotiation) for more details.

STOMP 1.1 servers MAY set the following headers:

* `session` : A session id that uniquely identifies the session.

* `server`  : A field that contains information about the STOMP server.
  The field MUST contain a server-name field and MAY be followed by optional 
  comment feilds delimited by a space character.

  The server-name field consists of a name token followed by an optional version
  number token.

    `server      = name ["/" version] *(comment)`

  Example:

    `server:Apache/1.3.9`

## Protocol Negotiation

From STOMP 1.1 and onwards, the `CONNECT` frame MUST include the
`accept-version` header. It SHOULD be set to a comma separated list of
incrementing STOMP protocol versions that the client supports. If the
`accept-version` header is missing, it means that the client only supports
version 1.0 of the protocol.

The protocol that will be used for the rest of the session will be the
highest protocol version that both the client and server have in common.

For example, if the client sends:

    CONNECT
    accept-version:1.0,1.1,2.0
    host:stomp.github.org

    ^@

The server will respond back with the highest version of the protocol that
it has in common with the client:

    CONNECTED
    version:1.1

    ^@

If the client and server do not share any common protocol versions, then the
sever SHOULD respond with an `ERROR` frame similar to:

    ERROR
    version:1.2,2.1
    content-type:text/plain

    Supported protocol versions are 1.2 2.1^@

## Once Connected

A client MAY send a frame not in this list, but for such a frame a
STOMP 1.1 server MAY respond with an `ERROR` frame.

* [`SEND`](#SEND)
* [`SUBSCRIBE`](#SUBSCRIBE)
* [`UNSUBSCRIBE`](#UNSUBSCRIBE)
* [`BEGIN`](#BEGIN)
* [`COMMIT`](#COMMIT)
* [`ABORT`](#ABORT)
* [`ACK`](#ACK)
* [`NACK`](#NACK)
* [`DISCONNECT`](#DISCONNECT)

## Client Frames

### SEND

The `SEND` frame sends a message to a destination in the messaging system. It
has one REQUIRED header, `destination`, which indicates where to send the
message. The body of the `SEND` frame is the message to be sent. For example:

    SEND
    destination:/queue/a
    content-type:text/plain

    hello queue a
    ^@

This sends a message to a destination named `/queue/a`. Note that STOMP treats
this destination as an opaque string and no delivery semantics are assumed by
the name of a destination. You should consult your STOMP server's
documentation to find out how to construct a destination name which gives you
the delivery semantics that your application needs.

The reliability semantics of the message are also server specific and will
depend on the destination value being used and the other message headers
such as the `transaction` header or other server specific message headers.

`SEND` supports a `transaction` header which allows for transactional sends.

`SEND` frames SHOULD include a
[`content-length`](#Header_content-length) header and a
[`content-type`](#Header_content-type) header if a body is present.

An application MAY add any arbitrary user defined headers to the `SEND` frame.
User defined headers are typically used to allow consumers to filter
messages based on the application defined headers using a selector
on a `SUBSCRIBE` frame. The user defined headers MUST be passed through
in the `MESSAGE` frame.

If the sever cannot successfully process the `SEND` frame frame for any reason,
the server MUST send the client an `ERROR` frame and disconnect the client.

### SUBSCRIBE

The `SUBSCRIBE` frame is used to register to listen to a given destination.
Like the `SEND` frame, the `SUBSCRIBE` frame requires a `destination` header
indicating the destination to which the client wants to subscribe. Any
messages received on the subscribed destination will henceforth be delivered
as `MESSAGE` frames from the server to the client. The `ack` header controls
the message acknowledgement mode.

Example:

    SUBSCRIBE
    id:0
    destination:/queue/foo
    ack:client

    ^@

If the sever cannot successfully create the subscription,
the server MUST send the client an `ERROR` frame and disconnect the client.

STOMP servers MAY support additional server specific headers to customize the
delivery semantics of the subscription. Consult your server's documentation for
details.

#### SUBSCRIBE id Header

An `id` header MUST be included in the frame to uniquely identify the subscription within the
STOMP connection session. Since a single connection can have multiple open
subscriptions with a server, the `id` header allows the client and server to
relate subsequent `ACK`, `NACK` or `UNSUBSCRIBE` frames to the original
subscription.

#### SUBSCRIBE ack Header

The valid values for the `ack` header are `auto`, `client`, or
`client-individual`. If the header is not set, it defaults to `auto`.

When the the `ack` mode is `auto`, then the client does not need to send the
server `ACK` frames for the messages it receives. The server will assume the
client has received the message as soon as it sends it to the the client.
This acknowledgment mode can cause messages being transmitted to the client
to get dropped.

When the the `ack` mode is `client`, then the client MUST send the server
`ACK` frames for the messages it processes. If the connection fails before a
client sends an `ACK` for the message the server will assume the message has
not been processed and MAY redeliver the message to another client. The `ACK`
frames sent by the client will be treated as a cumulative `ACK`. This means the `ACK` operates on the message specified in the `ACK` frame
and all messages sent to the subscription before the `ACK`-ed message.

When the the `ack` mode is `client-individual`, the ack mode operates just
like the `client` ack mode except that the `ACK` or `NACK` frames sent by the
client are not cumulative. This means that an `ACK` or `NACK` for a
subsequent message MUST NOT cause a previous message to get acknowledged.

### UNSUBSCRIBE

The `UNSUBSCRIBE` frame is used to remove an existing subscription. Once the
subscription is removed the STOMP connections will no longer receive messages
from that destination. It requires that the `id` header matches the `id`
value of previous `SUBSCRIBE` operation. Example:

    UNSUBSCRIBE
    id:0

    ^@

### ACK

`ACK` is used to acknowledge consumption of a message from a subscription
using `client` or `client-individual` acknowledgment. Any messages received
from such a subscription will not be considered to have been consumed until
the message has been acknowledged via an `ACK` or a `NACK`.

`ACK` has two REQUIRED headers: `message-id`, which MUST contain a value
matching the `message-id` for the `MESSAGE` being acknowledged and
`subscription`, which MUST be set to match the value of the subscription's
`id` header. Optionally, a `transaction` header MAY be specified, indicating
that the message acknowledgment SHOULD be part of the named transaction.

    ACK
    subscription:0
    message-id:007
    transaction:tx1

    ^@

### NACK

`NACK` is the opposite of `ACK`. It is used to tell the server that the
client did not consume the message. The server can then either send the
message to a different client, discard it, or put it in a dead letter queue.
The exact behavior is server specific.

`NACK` takes the same headers as `ACK`: `message-id` (mandatory),
`subscription` (mandatory) and `transaction` (OPTIONAL).

`NACK` applies either to one single message (if the subscription's ack mode
is `client-individual`) or to all messages sent before and not yet `ACK`'ed
or `NACK`'ed.

### BEGIN

`BEGIN` is used to start a transaction. Transactions in this case apply to
sending and acknowledging - any messages sent or acknowledged during a
transaction will be handled atomically based on the transaction.

    BEGIN
    transaction:tx1

    ^@

The `transaction` header is REQUIRED, and the transaction identifier will be
used for `SEND`, `COMMIT`, `ABORT`, `ACK`, and `NACK` frames to bind them to
the named transaction.

Any started transactions which have not been committed will be implicitly
aborted if the client sends a `DISCONNECT` frame or if the TCP connection
fails for any reason.

### COMMIT

`COMMIT` is used to commit a transaction in progress.

    COMMIT
    transaction:tx1

    ^@

The `transaction` header is REQUIRED and MUST specify the id of the transaction to
commit\!

### ABORT

`ABORT` is used to roll back a transaction in progress.

    ABORT
    transaction:tx1

    ^@


The `transaction` header is REQUIRED and MUST specify the id of the transaction to
abort\!

### DISCONNECT

A client can disconnect from the server at anytime by closing the socket but
there is no guarantee that the previously sent frames have been received by
the server. To do a graceful shutdown, where the client is assured that all
previous frames have been received by the server, the client SHOULD:

1. send a `DISCONNECT` frame with a `receipt` header set.  Example:

        DISCONNECT
        receipt:77
        ^@

2. wait for the `RECEIPT` frame response to the `DISCONNECT`. Example:

        RECEIPT
        receipt-id:77
        ^@

3. close the socket.

Clients MUST NOT send any more frames after the `DISCONNECT` frame is sent.

## Standard Headers

Some headers MAY be used, and have special meaning, with most frames.

### Header content-length

The `SEND`, `MESSAGE` and `ERROR` frames SHOULD include a `content-length`
header if a frame body is present. If a frame's body contains NULL octets, the
frame MUST include a `content-length` header. The header is a byte count for
the length of the message body. If a `content-length` header is included, this
number of bytes MUST be read, regardless of whether or not there are null
characters in the body. The frame still needs to be terminated with a null
byte.

### Header content-type

The `SEND`, `MESSAGE` and `ERROR` frames SHOULD include a `content-type`
header if a frame body is present. It SHOULD be set to a mime type which
describes the format of the body to help the receiver of the frame interpret
it's contents. If the `content-type` header is not set, the receiver SHOULD
consider the body to be a binary blob.

The implied text encoding for mime types starting with `text/` is UTF-8. If
you are using a text based mime type with a different encoding then you
SHOULD append `;charset=<encoding>` to the mime type. For example,
`text/html;charset=utf-16` SHOULD be used if your sending an html body in
UTF-16 encoding. The `;charset=<encoding>` SHOULD also get appended to any
non `text/` mime types which can be interpreted as text. A good example of
this would be a UTF-8 encoded XML. It's `content-type` SHOULD get set to
`application/xml;charset=utf-8`

All STOMP clients and servers MUST support UTF-8 encoding and decoding.  Therefore,
for maximum interoperability in a heterogeneous computing environment, it is
RECOMMENDED that text based content be encoded with UTF-8.

### Header receipt

Any client frame other than `CONNECT` MAY specify a `receipt`
header with an arbitrary value. This will cause the server to acknowledge
receipt of the frame with a `RECEIPT` frame which contains the value of this
header as the value of the `receipt-id` header in the `RECEIPT` frame.

    SEND
    destination:/queue/a
    receipt:message-12345

    hello queue a^@

## Server Frames

The server will, on occasion, send frames to the client (in addition to the
initial `CONNECTED` frame). These frames MAY be one of:

* [`MESSAGE`](#MESSAGE)
* [`RECEIPT`](#RECEIPT)
* [`ERROR`](#ERROR)

### MESSAGE

`MESSAGE` frames are used to convey messages from subscriptions to the
client. The `MESSAGE` frame will include a `destination` header indicating
the destination the message was sent to. It will also contain a `message-id`
header with a unique identifier for that message. The `subscription` header
will be set to match the `id` header of the subscription that is receiving
the message. The frame body contains the contents of the message:

    MESSAGE
    subscription:0
    message-id:007
    destination:/queue/a
    content-type:text/plain

    hello queue a^@

`MESSAGE` frames SHOULD include a
[`content-length`](#Header_content-length) header and a
[`content-type`](#Header_content-type) header if a body is present.

`MESSAGE` frames will also include all user defined headers that were present
when the message was sent to the destination in addition to the server
specific headers that MAY get added to the frame. Consult your server's
documentation to find out the server specific headers that it adds to
messages.

### RECEIPT

A `RECEIPT` frame is sent from the server to the client once a server has
successfully processed a client frame that requests a receipt. A `RECEIPT`
frame will include the header `receipt-id`, where the value is the value of
the `receipt` header in the frame which this is a receipt for.

    RECEIPT
    receipt-id:message-12345

    ^@


The receipt body will be empty.

### ERROR

The server MAY send `ERROR` frames if something goes wrong. The error frame
SHOULD contain a `message` header with a short description of the error, and
the body MAY contain more detailed information (or MAY be empty).

    ERROR
    receipt-id:message-12345
    content-type:text/plain
    content-length:171
    message: malformed frame received

    The message:
    -----
    MESSAGE
    destined:/queue/a
    receipt:message-12345


    Hello queue a!
    -----
    Did not contain a destination header, which is REQUIRED
    for message propagation.
    ^@


If the error is related to specific frame sent from the client, the server
SHOULD add additional headers to help identify the original frame that caused
the error. For example, if the frame included a receipt header, the `ERROR`
frame SHOULD set the `receipt-id` header to match the value of the `receipt`
header of the frame which the error is related to.

`ERROR` frames SHOULD include a
[`content-length`](#Header_content-length) header and a
[`content-type`](#Header_content-type) header if a body is present.

## Heart-beating

Heart-beating can optionally be used to test the healthiness of the
underlying TCP connection and to make sure that the remote end is alive and
kicking.

In order to enable heart-beating, each party has to declare what it can do
and what it would like the other party to do. This happens at the very
beginning of the STOMP session, by adding a `heart-beat` header to the
`CONNECT` and `CONNECTED` frames.

When used, the `heart-beat` header MUST contain two positive integers
separated by a comma.

The first number represents what the sender of the frame can do (outgoing
heart-beats):

* 0 means it cannot send heart-beats

* otherwise it is the smallest number of milliseconds between heart-beats
  that it can guarantee

The second number represents what the sender of the frame would like
to get (incoming heart-beats):

* 0 means it does not want to receive heart-beats

* otherwise it is the desired number of milliseconds between heart-beats

The `heart-beat` header is OPTIONAL. A missing `heart-beat` header MUST be
treated the same way as a "heart-beat:0,0" header, that is: the party cannot
send and does not want to receive heart-beats.

The `heart-beat` header provides enough information so that each party can
find out if heart-beats can be used, in which direction, and with which
frequency.

More formally, the initial frames look like:

    CONNECT
    heart-beat:<cx>,<cy>

    CONNECTED:
    heart-beat:<sx>,<sy>

For heart-beats from the client to the server:

* if `<cx>` is 0 (the client cannot send heart-beats) or `<sy>` is 0 (the
  server does not want to receive heart-beats) then there will be none

* otherwise, there will be heart-beats every MAX(`<cx>`,`<sy>`) milliseconds

In the other direction, `<sx>` and `<cy>` are used the same way.

Regarding the heart-beats themselves, any new data received over the network
connection is an indication that the remote end is alive. In a given
direction, if heart-beats are expected every `<n>` milliseconds:

* the sender MUST send new data over the network connection at least every
  `<n>` milliseconds

* if the sender has no real STOMP frame to send, it MUST send a single
  newline byte (0x0A)

* if, inside a time window of at least `<n>` milliseconds, the receiver did
  not receive any new data, it CAN consider the connection as dead

* because of timing inaccuracies, the receiver SHOULD be tolerant and take
  into account an error margin


## Augmented BNF

A STOMP session can be more formally described using the
Backus-Naur Form (BNF) grammar used in HTTP/1.1
[rfc2616](http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.1).

    LF                  = <US-ASCII new line (line feed) (octet 10)>
    OCTET               = <any 8-bit sequence of data>
    NULL                = <octet 0>

    frame-stream        = 1*frame

    frame               = command LF
                          *( header LF )
                          LF
                          *OCTET
                          NULL
                          *( LF )

    command             = client-command | server-command

    client-command      = "SEND"
                          | "SUBSCRIBE"
                          | "UNSUBSCRIBE"
                          | "BEGIN"
                          | "COMMIT"
                          | "ABORT"
                          | "ACK"
                          | "NACK"
                          | "DISCONNECT"
                          | "CONNECT"
                          | "STOMP"

    server-command      = "CONNECTED"
                          | "MESSAGE"
                          | "RECEIPT"
                          | "ERROR"

    header              = header-name ":" header-value
    header-name         = 1*<any OCTET except LF or ":">
    header-value        = *<any OCTET except LF or ":">

## License

This specification is licensed under the
[Creative Commons Attribution v2.5](http://creativecommons.org/licenses/by/2.5/)
license.
