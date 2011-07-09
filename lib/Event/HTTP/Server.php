<?php
namespace Event\HTTP;
class Server extends \Event\Emitter
{
    public $events = array(
        'request'=>true,
        'connection'=>true,
        'close'=>true,
        'checkContinue'=>true,
        'upgrade'=>true,
        'clientError'=>true,
        );
    protected $socket_event = null;
    protected $socket = null;
    protected $clients = array();
    const TIMEOUT_SECONDS = 10;
    const LIBEVENT_RUN_NOEVENTS = 1;
    const LIBEVENT_RUN_ERROR = -1;
    const LIBEVENT_RUN_SUCCESS = 0;


    public function __construct()
    {
        parent::__construct();
        $this->on('checkContinue', function($request, $response) {
            $response->writeContinue();
        });
    }
    public function clients()
    {
        return $this->clients;
    }
    public function listen($port, $hostname = '0.0.0.0', $callback = null)
    {
        $this->log("listen($port, $hostname, ...)");
        $streamUri = 'tcp://' . $hostname . ':' . $port;
        $this->socket = stream_socket_server($streamUri, $errNo, $errStr);
        if ($errNo)
        {
            throw new Exception("stream_socket_server() failed: $errNo - $errStr");
        }
        stream_set_blocking($this->socket, false);

        if ($callback) {
            call_user_func($callback);
        }

        $event = event_new();
        $cb = array($this, 'ev_accept');
        $argument = uniqid();
        event_set($event, $this->socket, EV_READ | EV_PERSIST, $cb, $argument);
        event_base_set($event, $this->base);
        event_add($event);
        $this->socket_event = $event;
    }

    //TODO listen($path, $callback = null) bind to unix socket

    public function close()
    {
        $this->log("close()");
        foreach (array_keys($this->clients) as $id) {
            $this->disconnectClient($id);
        }
        $this->clients = array();
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        event_del($this->socket_event);
        event_free($this->socket_event);
        $this->socket_event = null;
    }

    /////////////////////////////////
    /////////////////////////////////
    ////////// Housekeeping /////////
    /////////////////////////////////

    public function disconnectClient($id)
    {
        $this->log("disconnectClient($id)");

        $client = $this->clients[$id];
        if(!$client['request']->fired('end')) {
            $client['request']->fire('end');
        }
        if(!$client['disconnect'] && !$client['request']->fired('close')) {
            $client['request']->fire('close', $id);
        }

        ///
        $this->clients[$id]['response']->connection = null;
        $this->clients[$id]['response']->ev_buffer = null;
        ///

        stream_socket_shutdown($client['socket'], STREAM_SHUT_RDWR);
        event_buffer_disable($client['buffer'], EV_READ | EV_WRITE);
        event_buffer_free($client['buffer']);
        unset($this->clients[$id]);
    }

    public function disconnectClientAfterWrite($id)
    {
        $this->log("disconnectClientAfterWrite($id)");

        $this->clients[$id]['disconnect'] = true;
    }

    public function handleContinue($id)
    {
        $this->log("handleContinue($id)");
        $client = $this->clients[$id];
        $this->fire('checkContinue', $client['request'], $client['response']);
    }

    public function handleRequest($id)
    {
        $this->log("handleRequest($id)");

        $client = $this->clients[$id];
        $this->fire('request', $client['request'], $client['response']);
    }

    /////////////////////////////////
    /////////////////////////////////
    ////////// LibEvent wrappers ////
    /////////////////////////////////

    public function ev_accept($socket, $flag, $argument)
    {
        $this->log("ev_accept(..., $flag, ...)");
        $to = ini_get("default_socket_timeout");
        $connection = stream_socket_accept($socket, $to, $peer);
        stream_set_blocking($connection, 0);
        $readCb = array($this, 'ev_read');
        $writeCb = array($this, 'ev_write');
        $errCb = array($this, 'ev_error');

        $id = uniqid();
        $buffer = event_buffer_new($connection, $readCb, $writeCb, $errCb, $id);
        event_buffer_base_set($buffer, $this->base);

        event_buffer_timeout_set($buffer, self::TIMEOUT_SECONDS, self::TIMEOUT_SECONDS);
        event_buffer_watermark_set($buffer, EV_READ, 0, 0xffffff);
        event_buffer_priority_set($buffer, 10);
        event_buffer_enable($buffer, EV_READ | EV_PERSIST);

        $this->fire('connection', $connection);

        $request = new ServerRequest($this->base);
        $request->client_id = $id;
        $request->server = $this;
        $request->connection = $connection;
        $request->ev_buffer = $buffer;
        $request->peer = $peer;

        $response = new ServerResponse($request);
        $response->client_id = $id;
        $response->server = $this;
        $response->connection = $connection;
        $response->ev_buffer = $buffer;

        $this->clients[$id] = array(
            'socket'=>$connection,
            'buffer'=>$buffer,
            'request'=>$request,
            'response'=>$response,
            'disconnect'=>false,
            );

    }

    public function getResponseObject($id)
    {
        return $this->clients[$id]['response'];
    }

    public function ev_error($buffer, $error, $id)
    {
        $this->log("ev_error(..., $error, $id)");
        $errStr = "Unknown";
        if ($error & 0x10) {
            $this->log("EOF $id");
            $errStr = "EOF";
        }
        if ($error & 0x12) {
            $this->clients[$id]['request']->fire('end');
        }

        if ($error & 0x01) {
            $errStr .= " READ";
        }
        if ($error & 0x02) {
            $errStr .= " WRITE";
        }
        if ($error & 0x20) {
            $errStr .= " ERROR";
        }
        if ($error & 0x40) {
            //$this->fire('timeout', $id, null);
            $errStr .= " TIMEOUT";
        }

        if (($error & 0x41) || ($error & 0x10)) {
        } else {
            $this->fire('clientError', "Error $errStr $id");
        }

        $this->disconnectClient($id);
	}

    public function ev_read($inputeventbuffer, $id) {
        $this->log("ev_read(..., $id)");

        $client = $this->clients[$id];

        $client['request']->read($inputeventbuffer);
	}

    public function ev_write($outputeventbuffer, $id) {
        $this->log("ev_write(..., $id)");

        if($this->clients[$id]['disconnect']) {
            $this->disconnectClient($id);
        }
    }

}