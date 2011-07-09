Edges PHP
=============
A simple-to-use PHP 5.3 Libevent wrapper

Author
=============
Nathan Schmidt <nschmidt@gmail.com> 2011

License
=============
MIT


Motivation
=============
Based initially on a hand-rolled libevent wrapper bitbanging HTTP as a thought exercise, then after some more rooting around on the google, inspired by the general concept of getting the Apache/FCGI/CGI layer out of the conversation entirely (see  https://github.com/fhoenig/Kellner) — also the evhttp wrapper used there though using that requires single-channeling http requests. You can't multiplex nor upgrade an established connection to websockets later when using the simple evhttp wrapper. After a few more hours, it occurred to me that this feels a lot like writing against the nodejs API, so I'm turning that thought into actually doing so as nearly as reasonable.

This is a sketch, a proof of concept more than anything production-ready of course, I think it's an interesting take on how PHP and libevent can combine in useful ways. For certain specialized use cases it can provide a long-lived shared-state server with very low latency and high active-clients-per-host capacity. It raises the possibility of adding a few interesting features in terms of streaming upload processing and a few other goodies which might be useful and are currently hard to do via the CGI and Apache SAPIs.

Particularly in a world of lightweight backbone HTML/JS pushed statically to clients and served via (potentially) many AJAX API requests, we need to think of ways to make PHP much more efficient (= lower latency) when there are nontrivial chunks of code to sling around.

APC and other bytecode caches can help to some extent, but if you have a dozen or a hundred classes interacting you spend a lot of time moving code around and double-checking timestamps instead of running userland. Even then, you always start with a completely blank slate in your heap, so have to go get the user data from somewhere else.

For data structures which are best expressed in a durable daemon process (i.e. some huge tree of objects and permissions and acls), you shouldn't have to spend 75% of your request time shunting data in and out of SHM or memcached, as long as you can direct to the pid who has the data already, let's do that instead.



Summary
=============
For edge-triggering things when events happen on sockets, timers, and files.

Uses friendly callbacks and relatively simple info passing, more or less done in the style of node.js

So

>> ./bin/edges ./demo/helloworld.php

Looks and works much like

>> node helloworld.js



The code from demo/helloworld.php:

	$http = new Event\HTTP();

	$srv = $http->createServer(function($request, $response) {
	    $response->writeHead(200, array('Content-Type'=>'text/plain'));
	    $response->end("Hello World\n");
	})->listen(8080);

	print "Server running at http://1270.0.1:8080\n";


And the original JavaScript from http://nodejs.org/docs/v0.5.0/api/synopsis.html

	var http = require('http');

	http.createServer(function (request, response) {
	  response.writeHead(200, {'Content-Type': 'text/plain'});
	  response.end('Hello World\n');
	}).listen(8124);

	console.log('Server running at http://127.0.0.1:8124/');



TODOS/NOTDONES
============
Evented Files
Raw Sockets
Web Sockets
Characterize and eliminate likely memory leaks
Anything else of interest
