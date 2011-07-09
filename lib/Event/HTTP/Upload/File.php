<?php
namespace Event\HTTP\Upload;

class File extends \Event\Emitter
{
    //public $LOG = true;
    public $tmp = null;
    public $filename = null;
    public $field = null;
    public $header = null;
    public $contentType = 'text/plain';
    public $fd = null;
    public $events = array(
        'fileBegin' => true,
        'file' => true,
    );

    public function headerField($field) {
        $this->header = $field;
        $this->log("headerField('$field')");
    }
    public function headerValue($value) {
        if (strtolower($this->header) == 'content-type') {
            $this->contentType = $value;
        } else if(strtolower($this->header) == 'content-disposition') {
            if (preg_match('/\bname=([^;]+)/', $value, $m)) {
                $this->field = trim($m[1], '"');
            }
            if (preg_match('/filename=([^;]+)/', $value, $m)) {
                $this->filename = trim($m[1], '"');
                $this->fire('fileBegin', $this->filename);
            }
        }
        $this->log("headerValue('$value')");
    }
    public function headerEnd() {
        $this->log("headerEnd()");
    }
    public function headersEnd() {
        $this->log("headersEnd()");
    }
    public function partBegin() {
        $this->log("partBegin()");
        $this->tmp = tempnam('/tmp/', 'uploadStream');
        $this->log("partBegin() tmp: $this->tmp");
        $this->fd = fopen($this->tmp, 'w');
        if(!is_resource($this->fd)) {
            print "Can't open temp file $this->tmp for new part\n";
        }
    }
    public function partData($data = '') {
        $this->log("partData(bytes:".strlen($data).") at " . ftell($this->fd));
        fwrite($this->fd, $data);
        //print "PARTDATA\t\t\t" . strlen($data) . "\n";
/*
        if (ftell($this->fd) > 144000) {
            //print "SMALLPARTDATA:\n";
            print \Event\Runloop::RED;
            print \Event\Runloop::hexdump($data);
            print \Event\Runloop::PLAIN;
        }
*/

        //$this->log("partData(...) file now " . ftell($this->fd));
        $this->log("partData(...) now at ".ftell($this->fd)." file chunks " . (ftell($this->fd)/4096));
    }
    public function partEnd() {
        $this->log("partEnd()");
        fclose($this->fd);
        $this->fd = null;
        $this->fire('file', $this);
        //`opendiff /Users/nathan/test.pdf $this->tmp`;
    }
    public function end() {
        $this->log("end()");
        $this->file = null;
    }
}

