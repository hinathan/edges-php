<?php

$http = new Event\HTTP();

$srv = $http->createServer(function($req, $res) {

    if ($req->method == 'GET') {
        $url = $req->url;
        $res->end("THAT WAS A GET REQUEST FOR $url\n");
    } else if ($req->method == 'POST') {
        // can also do $req->on('file') for each file as they arrive
        // or $req->on('data') for each block as recieved.
        $req->on('end', function() use($req, $res) {
            $files = $req->uploadedFiles();
            $res->writeHead(200, array('Content-Type'=>'application/derp'));
            foreach ($files as $file_info) {
                $res->write("Accepted file $file_info[filename]\n");
            }
            $url = $req->url;
            $res->end("THAT WAS A POST REQUEST FOR $url\n");
        });
    }
});

$srv->listen(8080);
