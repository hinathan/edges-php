<?php
namespace Event\HTTP;
class ServerResponse
{
    public $LOG = false;

    public $client_id = null;
    public $server = null;
    public $connection = null;
    public $ev_buffer = null;
    public $statusCode = 200;

    private $_request = null;
    private $_headers = array();
    private $_wrote_head = false;

    public function __construct($request)
    {
        $this->_request = $request;
        $this->_headers = array(
            'Server'=>'node-php/0.1',
            'Transfer-Encoding'=>'chunked',
        );
    }

    public function addTrailers($headers)
    {
        //todo trailers?
        $this->log("addTrailers(...)");
    }

    public function writeContinue()
    {
        $this->log("writeContinue()");

        event_buffer_write($this->ev_buffer, "HTTP/1.1 100 Continue\r\n\r\n");
    }

    public function connected()
    {
        return is_resource($this->ev_buffer);
    }
    public function write($data)
    {
        if (!$this->connected()) {
           $this->log("Not connected.");
           return;
        }

        $this->log("write(#" . strlen($data) . ")");

        if(!$this->_wrote_head) {
            $this->writeHead();
        }

        $block = sprintf("%x\r\n", strlen($data));
        $block .= $data . "\r\n";

        $result = event_buffer_write($this->ev_buffer, $block);

        if (!$result) {
           $this->server->disconnectClientAfterWrite($this->client_id);
        }
    }

    public function end($data = null)
    {
        $this->log("end(...)");

        if (!$this->_wrote_head) {
            $this->writeHead();
        }
        if (null !== $data) {
            $this->write($data);
        }
        $this->write('');

        $this->server->disconnectClientAfterWrite($this->client_id);
    }

    public function setHeader($name, $value)
    {
        $this->log("setHeader($name, $value)");

        $this->_headers[$name] = $value;
    }

    public function getHeader($name)
    {
        $this->log("getHeader($name)");
        return $this->_headers[$name];
    }

    public function removeHeader($name)
    {
        $this->log("removeHeader($name)");
        unset($this->_headers[$name]);
    }

    public function writeHead($status = null, $reasonPhrase = null, $headers = null)
    {
        $addHeaders = array();

        $this->log("writeHead(..., ..., ...)");
        $args = func_get_args();

        if ($this->_wrote_head) {
            throw new \Exception("Already wrote head");
        }
        if (null === $status) {
            $status = $this->statusCode;
        }
        $this->statusCode = $status;

        if (null === $reasonPhrase) {
            $reasonPhrase = "OK";
        } else if(is_array($reasonPhrase)) {
            $addHeaders = $reasonPhrase;
            $reasonPhrase = "OK";
        } else if (null === $headers) {
            $addHeaders = array();
            if (is_array($headers)) {
                $addHeaders = $headers;
            }
        }

        $outputHeaders = $this->_headers;

        foreach ($addHeaders as $key=>$value) {
            $outputHeaders[$key] = $value;
        }

        $result = "HTTP/" . $this->_request->httpVersion;
        $result .= " $status $reasonPhrase\r\n";

        foreach ($outputHeaders as $key=>$value) {
            $result .= "$key: $value\r\n";
        }
        $result .= "\r\n";

        $this->_wrote_head = true;

        event_buffer_write($this->ev_buffer, $result);
    }

    protected function log($msg) {
        if($this->LOG) {
            $mem = sprintf('%0.2fMb', memory_get_usage(true) /(1024*1024));
            print get_class($this) . "@$mem [id:$this->client_id]: $msg\n";
        }
    }
}