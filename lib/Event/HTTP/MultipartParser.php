<?php

/**
 * This is essentially a direct transliteration of
 * https://github.com/felixge/node-formidable/
 * from js to php
 */

namespace Event\HTTP;
class MultipartParser extends \Event\Emitter
{
    //public $LOG = false;
    const PARSER_UNINITIALIZED = 1;
    const START = 2;
    const START_BOUNDARY = 3;
    const HEADER_FIELD_START = 4;
    const HEADER_FIELD = 5;
    const HEADER_VALUE_START = 6;
    const HEADER_VALUE = 7;
    const HEADER_VALUE_ALMOST_DONE = 8;
    const HEADERS_ALMOST_DONE = 9;
    const PART_DATA_START = 10;
    const PART_DATA = 11;
    const PART_END = 12;
    const END = 13;

    const PART_BOUNDARY = 1;
    const LAST_BOUNDARY = 2;

    const LF = "\n";
    const CR = "\r";
    const SPACE = " ";
    const HYPHEN = "-";
    const COLON = ":";
    const A = "a";
    const Z = "z";

    var $boundary = null;
    var $boundaryChars = null;
    var $lookbehind = array();
    var $state = self::PARSER_UNINITIALIZED;
    var $index = null;
    var $flags = 0;

    var $marks = array();

    var $events = array(
        'headerField' => true,
        'headerValue' => true,
        'headerEnd' => true,
        'headersEnd' => true,
        'partBegin' => true,
        'partData' => true,
        'partEnd' => true,
        'end' => true,
        );

    public function __construct($boundary = null)
    {
        if(null !== $boundary) {
            $this->initWithBoundary($boundary);
        }
    }

    public function initWithBoundary($boundary)
    {
        $this->boundary = "\r\n--" . $boundary;
        $this->lookbehind = array();
        $this->state = self::START;
        $this->boundaryChars = array();
        for ($i=0; $i<strlen($this->boundary); $i++) {
            $this->boundaryChars[$this->boundary{$i}] = true;
        }
        $this->marks = array();
    }

    public function marked($name)
    {
        return isset($this->marks[$name])?$this->marks[$name]:false;
    }
    public function mark($name, $i)
    {
        $this->marks[$name] = $i;
    }
    public function clear($name)
    {
        unset($this->marks[$name]);
    }
    public function write($buffer)
    {
        $i = 0;
        $len = strlen($buffer);
        $prevIndex = $this->index;
        $state = $this->state;
        $flags = $this->flags;
        $boundary = $this->boundary;
        $boundaryChars = $this->boundaryChars;
        $boundaryLength = strlen($this->boundary);
        $boundaryEnd = $boundaryLength - 1;
        $bufferLength = strlen($buffer);
        $c = null;
        $cl = null;
        $index = 0;
        $self = $this;

        $callback = function($name, $buffer = null, $start = null, $end = null) use (&$self)
        {

            if ($start && ($start == $end)) {
                //$self->log("ERROR? STAT EQUALS END ELIDE " . __LINE__);
                return;
            }

            if(null !== $start) {
                if ($end) {
                    $val = substr($buffer, $start, $end - $start);
                } else if($start) {
                    $val = substr($buffer, $start);
                } else {
                    $val = $buffer;
                }

                $self->fire($name, $val);
            } else {
                $self->fire($name);
            }
        };

        $dataCallback = function($name, $clear = false) use(&$self, &$callback, &$i, &$buffer, &$bufferLength)
        {

            $marked = $self->marked($name);
            if (false === $marked) {
                return;
            }

            if (false === $clear) {
                $callback($name, $buffer, $marked, $bufferLength);
                $self->mark($name, 0);
            } else {
                $callback($name, $buffer, $marked, $i);
                $self->clear($name);
            }
        };


        $this->log("block length: " . $len);

        for ($i = 0; $i < $len; $i++) {
            $c = $buffer{$i};

            switch ($state) {
                case self::PARSER_UNINITIALIZED:
                    $this->log("ERROR? PARSER_UNINITIALIZED " . __LINE__);
                    return $i;
                case self::START:
                    $index = 0;
                    $state = self::START_BOUNDARY;

                case self::START_BOUNDARY:
                    if ($index == ($boundaryLength - 2)) {
                        if ($c != self::CR) {
                            $this->log("ERROR? Not CR " . __LINE__);
                            return $i;
                        }
                        $index++;
                        break;
                    } else if (($index - 1) == ($boundaryLength - 2)) {
                        if ($c != self::LF) {
                            $this->log("ERROR? Not LF " . __LINE__);
                            return $i;
                        }
                        $index = 0;
                        $callback('partBegin');
                        $state = self::HEADER_FIELD_START;
                        break;
                    }
                    if ($c != $boundary{$index+2}) {
                        $this->log("ERROR? Not boundary/$index+2/ " . __LINE__);
                        return $i;
                    }
                    $index++;
                    break;
                case self::HEADER_FIELD_START:
                    $state = self::HEADER_FIELD;
                    $this->mark('headerField', $i);
                    $index = 0;
                case self::HEADER_FIELD:
                    if ($c == self::CR) {
                        $this->clear('headerField');
                        $state = self::HEADERS_ALMOST_DONE;
                        break;
                    }
                    $index++;
                    if ($c == self::HYPHEN) {
                        break;
                    }
                    if ($c == self::COLON) {
                        if ($index == 1) {
                            $this->log("ERROR? UNEXPECTED COLON " . __LINE__);
                            return $i;
                        }

                        $dataCallback('headerField', true);
                        $state = self::HEADER_VALUE_START;
                        break;
                    }

                    $cl = strtolower($c);
                    if ($cl < self::A || $cl > self::Z) {
                        $this->log("ERROR? HEADER ERROR " . __LINE__);
                        return $i;
                    }
                    break;
                case self::HEADER_VALUE_START:
                    if ($c == self::SPACE) {
                        break;
                    }
                    $this->mark('headerValue', $i);
                    $state = self::HEADER_VALUE;
                case self::HEADER_VALUE:
                    if ($c == self::CR) {
                        $dataCallback('headerValue', true);
                        $callback('headerEnd');
                        $state = self::HEADER_VALUE_ALMOST_DONE;
                    }
                    break;
                case self::HEADER_VALUE_ALMOST_DONE:
                    if ($c != self::LF) {
                        $this->log("ERROR? HEADER_VALUE_ALMOST_DONE NOT LF " . __LINE__);
                        return $i;
                    }
                    $state = self::HEADER_FIELD_START;
                    break;
                case self::HEADERS_ALMOST_DONE:
                    if ($c != self::LF) {
                        $this->log("ERROR? HEADERS_ALMOST_DONE NOT LF " . __LINE__);
                        return $i;
                    }

                    $callback('headersEnd');
                    $state = self::PART_DATA_START;
                    break;
                case self::PART_DATA_START:
                    $state = self::PART_DATA;

                    $this->mark('partData', $i);
                case self::PART_DATA:
                    $prevIndex = $index;
                    if ($index == 0) {
                        // boyer-moore derrived algorithm to safely skip non-boundary data
                        $i += $boundaryEnd;
                        while ($i < ($bufferLength-($boundaryLength+1)) && !(isset($boundaryChars[$buffer{$i}]))) {
                            $i += $boundaryLength;
                        }
                        $i -= $boundaryEnd;
                        $c = $buffer{$i};
                    }

                    if ($index < $boundaryLength) {
                        if ($boundary{$index} == $c) {
                            if ($index == 0) {
                                $dataCallback('partData', true);
                            }
                            $index++;
                        } else {
                            $index = 0;
                        }
                    } else if ($index == $boundaryLength) {
                        $index++;
                        if ($c == self::CR) {
                            // CR = part boundary
                            $flags |= self::PART_BOUNDARY;
                        } else if ($c == self::HYPHEN) {
                            // HYPHEN = end boundary
                            $flags |= self::LAST_BOUNDARY;
                        } else {
                            $index = 0;
                        }
                    } else if ($index - 1 == $boundaryLength)  {
                        if ($flags & self::PART_BOUNDARY) {
                            $index = 0;
                            if ($c == self::LF) {
                                // unset the PART_BOUNDARY flag
                                $flags &= ~self::PART_BOUNDARY;
                                $this->log("part end at PART_BOUNDARY");
                                $callback('partEnd');
                                $this->lookbehind = '';
                                $callback('partBegin');
                                $state = self::HEADER_FIELD_START;
                                break;
                            }
                        } else if ($flags & self::LAST_BOUNDARY) {
                            if ($c == self::HYPHEN) {
                                $this->log("part end at LAST_BOUNDARY");
                                $callback('partEnd');
                                $callback('end');
                                $this->lookbehind = '';
                                $state = self::END;
                            } else {
                                $index = 0;
                            }
                        } else {
                            $index = 0;
                        }
                    }

                    if ($index > 0) {
                        // when matching a possible boundary, keep a lookbehind reference
                        // in case it turns out to be a false lead
                        $this->lookbehind[$index-1] = $c;
                        $this->log("SET Lookbehind $index-1 (".($index-1).") is " . sprintf('0x%02x',ord($c)));
                    } else if ($prevIndex > 0) {
                        // if our boundary turned out to be rubbish, the captured lookbehind
                        // belongs to partData

                        $lookbehindString = implode('',$this->lookbehind);

                        $this->log("USE Lookbehind keys " . implode(',', array_keys($this->lookbehind)));
                        $this->log("USE Lookbehind data \n" . \Event\Runloop::GREEN . \Event\Runloop::hexdump($lookbehindString) . \Event\Runloop::PLAIN);
                        $this->log("USE lookbehind SUBSTR [0, $prevIndex]");
                        $callback('partData', $lookbehindString, 0, $prevIndex);
                        $prevIndex = 0;
                        $this->mark('partData', $i);
                        // reconsider the current character even so it interrupted the sequence
                        // it could be the beginning of a new sequence
                        $i--;
                    }

                    break;
                case self::END:
                    break;
                default:
                    print "DEFAULT ($i)\n";
                    return $i;
            }
        }
        //print "STATE $state index $index flags $flags len $len\n";
        $dataCallback('headerField');
        $dataCallback('headerValue');
        $dataCallback('partData');
        $this->index = $index;
        $this->state = $state;
        $this->flags = $flags;
        return $len;
    }
    public function end() {
        if ($this->state != self::END) {
            throw new \Exception('MultipartParser.end(): stream ended unexpectedly');
        }
    }
}


