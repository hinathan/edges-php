<?php

$http = new Event\HTTP();

$srv = $http->createServer(function($req, $res) {
    $res->write("Hello World immediately\n");
    setTimeout(function() use (&$res) {
        $res->end("Goodbye world 5 seconds later\n");
    }, 5000);
});

$srv->listen(8080, '127.0.0.1');
