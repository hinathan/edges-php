<?php
namespace Event;
class Emitter
{
    const EVENT_LOG = false;
    const EVENT_FIRE_DEBUG = false;
    public $LOG = false;
    protected $base = null;
    protected static $globalBase = null;
    public $events = array();
    public $fired = array();
    public static function getBase() {
        if(!self::$globalBase) {
            self::$globalBase = event_base_new();
        }
        return self::$globalBase;
    }
    public function __construct()
    {
        $this->base = self::getBase();
    }
    public function off($event, $id)
    {
        if(!isset($this->events[$event][$id])) {
            $this->log("Can't off('$event').$id");
        } else {
            $this->log("off('$event').$id");
        }
        unset($this->events[$event][$id]);
    }
    public function on($event, $function, $debug = self::EVENT_FIRE_DEBUG)
    {
        if (!isset($this->events[$event])) {
            throw new \Exception("No such '$event'");
        }
        if(true === $this->events[$event]) {
            $this->events[$event] = array();
        }
        $def_at = '?';
        if ($debug) {
            $bt = debug_backtrace();
            $def_at = $bt[0]['file'] .':' . $bt[0]['line'];
        }
        $id = uniqid();
        $this->events[$event][$id] = array($function, $def_at, $debug);

        return $id;
    }
    public function fire($event)
    {
        if (!isset($this->events[$event])) {
            print "NO SUCH EVENT: $event\n";
            throw new \Exception("No such '$event'");
        }
        $functions = $this->events[$event];
        $args = func_get_args();
        array_shift($args);
        if(true === $functions) {
            $this->log("no listeners for '$event'");
            return;
        }
        foreach ($functions as $function_at) {
            list($function, $def_at, $debug) = $function_at;
            if (is_callable($function)) {
                if ($debug && self::EVENT_LOG) {
                    $this->log("/" . "/ $def_at", true);
                    if($function instanceof \Closure) {
                        $this->log("FIRE $event {closure}()", true);
                    } else if(is_array($function)) {
                        $this->log("FIRE $event " . get_class($function[0]) . "->" . $function[1] . "()", true);
                    } else {
                        $this->log("FIRE $event $function()", true);
                    }
                }
                call_user_func_array($function, $args);
                $this->fired[$event] = true;
            } else {
                $this->log("uncallable callback for '$event'");
                var_dump($function);
            }
        }
    }
    public function fired($event) {
        return isset($this->fired[$event]);
    }


    protected function log($msg, $force = false)
    {
        if($this->LOG || $force) {
            $class = get_class($this);
            $mem = sprintf('%0.2fMb', memory_get_usage(true) /(1024*1024));
            print str_pad("$class+$mem:",36) . " $msg\n";
        }
    }

}