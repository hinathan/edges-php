<?php
namespace Event;
class Timer
{
    public function setTimeout($callback, $interval_ms)
    {
        $base = \Event\Emitter::getBase();
        $event = event_timer_new();
        $arg = array($callback,$event,$interval_ms);
        event_timer_set($event, array($this, 'ev_timer'), $arg);
        event_base_set($event, $base);
        event_add($event, $interval_ms * 1000);
    }

    public function ev_timer($null, $events_flag, $arg) {
        list($callback, $event, $interval_ms) = $arg;
        $repeat = call_user_func($callback);
        if($repeat) {
            $this->setTimeout($callback, $interval_ms);
        }
    }
}