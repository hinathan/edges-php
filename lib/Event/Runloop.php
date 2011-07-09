<?php
namespace Event;
class Runloop
{
    const BOLD = "\033[1m";
    const BLUE = "\033[34m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const CYAN = "\033[36m";
    const MAGENTA = "\033[35m";
    const LIGHT = "\033[37m";
    const PLAIN = "\033[m";

    public function runOnce()
    {
        $base = \Event\Emitter::getBase();
        return event_base_loop($base, EVLOOP_ONCE | EVLOOP_NONBLOCK);
    }

    public function run()
    {
        $base = \Event\Emitter::getBase();
        return event_base_loop($base);
    }


    public static function hexdump($data)
    {
        $hexi = '';
        $ascii = '';
        $dump = '';
        $offset = 0;
        $len = strlen($data);
        // Upper or lower case hexidecimal
        $x = 'X';
        // Iterate string
        for ($i = $j = 0;$i < $len;$i++) {
            // Convert to hexidecimal
            $hexi.= sprintf("%02$x ", ord($data[$i]));
            // Replace non-viewable bytes with '.'
            if (ord($data[$i]) >= 32) {
                $ascii.= $data[$i];
            } else {
                $ascii.= '.';
            }
            // Add extra column spacing
            if ($j === 7) {
                $hexi.= ' ';
                $ascii.= ' ';
            }
            // Add row
            if (++$j === 16 || $i === $len - 1) {
                // Join the hexi / ascii output
                $dump.= sprintf("%04$x  %-49s  %s", $offset, $hexi, $ascii);
                // Reset vars
                $hexi = $ascii = '';
                $offset+= 16;
                $j = 0;
                // Add newline
                if ($i !== $len - 1) {
                    $dump.= "\n";
                }
            }
        }
        // Finish dump
        $dump.= "\n";
        // Output method
        return $dump;
    }
}