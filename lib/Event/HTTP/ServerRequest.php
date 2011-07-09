<?php
namespace Event\HTTP;
class ServerRequest extends \Event\Emitter
{
    //public $LOG = false;
    const READ_CHUNK_SIZE = 4096;
    public $connection = null;
    public $server = null;
    public $client_id = null;
    public $ev_buffer = null;
    public $peer = null;
    private $_have_header = false;
    private $_did_continue = false;
    private $_blanks = 0;
    private $_headers = array();
    private $_method = null;
    private $_url = null;
    private $_version = null;
    private $_content_bytes = 0;
    private $_chunked = null;
    private $_files = null;
    private $_raw_buffer = '';
    private $_total_bytes = 0;
    private $_uploadedFiles = array();

    public $events = array(
        'data'=>true,
        'end'=>true,
        'close'=>true,
        //novel
        'file'=>true,
        'fileStart'=>true,
        );

    public function uploadedFiles()
    {
        return $this->_uploadedFiles;
    }
    public function noteUpload($fileInfo)
    {
        $this->_uploadedFiles[] = $fileInfo;
    }

    protected function rawData($data)
    {
        return $this->fire('data', $data);
    }

    public function read($eventbuffer)
    {
        $this->log("read()");

        if ($this->fired('end')) {
            $this->log("read but already fired 'end' event");
            return;
        }
        $data = event_buffer_read($eventbuffer, self::READ_CHUNK_SIZE);


        if ($this->_have_header) {
            $this->_content_bytes += strlen($data);
            $this->rawData($data);
        } else {
            $chunks = explode("\r\n", $data);
            while (!$this->_have_header && $chunks) {
                $chunk = array_shift($chunks);
                $this->readHeaderLine($chunk);
            }
            if ($this->_have_header) {
                $this->server->handleRequest($this->client_id);
            }
            if ($chunks) {
                $reconstitutedData = implode("\r\n", $data);
                $this->_content_bytes += strlen($reconstitutedData);
                $this->rawData($reconstitutedData);
            }
        }
    }

    public function __get($key)
    {
        switch ($key) {
            case 'method':
            return $this->_method;
            case 'url':
            return $this->_url;
            case 'headers':
            return $this->_headers;
            case 'trailers':
            return null;
            case 'httpVersion':
            return $this->_version;
        }
        return null;
    }

    private function readHeaderLine($line)
    {
        if ($this->_have_header) {
            return;
        }
        if ($line === '') {
            $this->_blanks ++;
        } else {
            $this->_blanks = 0;
            if (count($this->_headers)) {
                $keyvalue = explode(':', $line, 2);
                if(count($keyvalue) == 2) {
                    list($key, $value) = $keyvalue;
                    $this->_headers[strtolower($key)] = trim($value);
                }
            } else {
                $this->_request = $line;
                $this->_headers[''] = $line;
            }
        }
        if ($this->_blanks == 2) {
            $this->_have_header = true;
            list($method, $url, $httpClause) = explode(' ', $this->_request, 3);
            list($http, $version) = explode('/', $httpClause);
            $this->_url = $url;
            $this->_method = $method;
            $this->_version = $version;
            if (isset($this->_headers['expect'])) {
                if (strtolower($this->_headers['expect']) == '100-continue') {
                    $this->log("client expects a continue");
                    $this->_did_continue = true;
                    $this->server->handleContinue($this->client_id);
                }
            }
            if (isset($this->_headers['content-type'])) {
                $type = $this->_headers['content-type'];
                $mfd = 'multipart/form-data';
                $bound = 'boundary=';
                if ((0 === strpos($type, $mfd)) && ($pos = strpos($type, $bound))) {
                    $mdf_boundary = substr($type, $pos+strlen($bound));
                    //print "BOUNDARY: '$mdf_boundary'\n";

                    $chunked = new \Event\HTTP\MultipartParser($mdf_boundary);

                    //forward my read data to the chunked reader
                    $this->on('data', function($data) use($chunked) {
                        $chunked->write($data);
                    });

                    $this->_chunked = $chunked;
                    $this->_files = new \Event\HTTP\Upload\Files($this->_chunked);
                    $self = $this;
                    $this->_files->on('fileBegin', function($filename) {
                        // meh
                    });
                    $this->_files->on('file', function($file) use($self) {
                        $info = array(
                            'temp'=>$file->tmp,
                            'field'=>$file->field,
                            'filename'=>$file->filename,
                            'contentType'=>$file->contentType,
                            );
                        $self->noteUpload($info);
                    });
                    $chunked->on('end', function() use($self) {
                        $self->fire('end');
                    });
                }
            }
        }
    }
}