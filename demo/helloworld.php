<?php

/// Bascically the same as in the node.js manual, weird.

$http = new Event\HTTP();

$srv = $http->createServer(function($request, $response) {
    $response->writeHead(200, array('Content-Type'=>'text/plain'));
    $response->end("Hello World\n");
})->listen(8080);

print "Server running at http://1270.0.1:8080\n";
