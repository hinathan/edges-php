<?php
namespace Event;
class HTTP
{
    public function createServer($requestListener = null)
    {
        $server = new HTTP\Server();
        if ($requestListener) {
            $server->on('request', $requestListener);
        }
        return $server;
    }

}