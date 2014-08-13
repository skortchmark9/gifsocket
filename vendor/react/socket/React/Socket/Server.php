<?php

namespace React\Socket;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;

class Server extends EventEmitter implements ServerInterface
{
    public $master;
    private $loop;
    private $time;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function listen($port, $host = '127.0.0.1')
    {
        $this->master = @stream_socket_server("tcp://$host:$port", $errno, $errstr);
        if (false === $this->master) {
            $message = "Could not bind to tcp://$host:$port: $errstr";
            throw new ConnectionException($message, $errno);
        }
        stream_set_blocking($this->master, 0);

        $that = $this;

        $this->loop->addReadStream($this->master, function ($master) use ($that) {
            $newSocket = stream_socket_accept($master);
            if (false === $newSocket) {
                $that->emit('error', array(new \RuntimeException('Error accepting new connection')));

                return;
            }
            $that->handleConnection($newSocket);
        });
    }


    public function handleConnection($socket)
    {
        stream_set_blocking($socket, 0);

        $client = $this->createConnection($socket);

        $client->on('close', function() {
           $this->endTimer();
        });


        $this->emit('connection', array($client));
    }

    public function getPort()
    {
        $name = stream_socket_get_name($this->master, false);

        return (int) substr(strrchr($name, ':'), 1);
    }

    public function startTimer() {
        $this->time = time();
    }

    public function endTimer() {
        $time = time() - $this->time;
        echo "Connection Closed After: $time seconds\n";

        $file = fopen("timing.txt", "a");
        fwrite($file, "\n Connection Time: " . $time . " seconds");
        fclose($file);
    }


    public function shutdown()
    {
        //shuts down the server we blieve.
        $this->loop->removeStream($this->master);
        fclose($this->master);
    }

    public function createConnection($socket)
    {
        echo "Connection Created at " . date('g:i:s A', time()) . "\n";
        $this->startTimer();
        return new Connection($socket, $this->loop);
    }
}
