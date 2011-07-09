<?php
namespace Event\HTTP\Upload;

class Files extends \Event\Emitter
{
    //public $LOG = false;
    protected $chunkstream = null;
    protected $files = array();
    protected $file = null;
    protected $events_registered = array();
    public $events = array(
        'fileBegin' => true,
        'file' => true,
        'end' => true,
    );

    public function __construct($chunkstream)
    {
        parent::__construct();
        $this->chunkstream = $chunkstream;
        $this->chunkstream->on('partBegin', array($this, 'partBegin'));
        $this->chunkstream->on('end', array($this, 'end'));
    }

    protected function detach()
    {
        foreach($this->events_registered as $id=>$event) {
            $this->chunkstream->off($event, $id);
        }
        $this->events_registered = array();
    }

    protected function attach($file)
    {
        $events = array(
            'headerField',
            'headerValue',
            'headerEnd',
            'headersEnd',
            'partData',
            'partEnd',
            );

        foreach($events as $event) {
            $id = $this->chunkstream->on($event, array($file, $event));
            $this->events_registered[$id] = $event;
        }
    }

    public function partBegin()
    {
        $this->file = new File();
        $this->files[] = $this->file;
        $this->detach();
        $this->attach($this->file);
        $this->file->partBegin();
        $this->file->on('fileBegin', array($this, 'fileBegin'));
        $this->file->on('file', array($this, 'file'));
    }
    public function fileBegin($file) {
        $this->fire('fileBegin', $file);
    }
    public function file($file) {
        $this->fire('file', $file);
    }
    public function end()
    {
        if ($this->file) {
            if($this->file->fd) {
                fclose($this->file->fd);
                $this->file->fd = null;
                if($this->file) {
                    $this->fire('file', $this->file);
                }
            }
        }
        $this->fire('end');
    }
    public function files()
    {
        return $this->files;
    }
}
