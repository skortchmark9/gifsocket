<?php

require __DIR__.'/../vendor/autoload.php';

function createGifFrame(array $messages)
{
    $im = imagecreatetruecolor(500, 400);

    imagefilledrectangle($im, 0, 0, 500, 400, 0x000000);
    foreach ($messages as $i => $message) {
        imagestring($im, 3, 40, 20 + $i*20, $message, 0xFFFFFF);
    }

    ob_start();

    imagegif($im);
    imagedestroy($im);

    return ob_get_clean();
}

function sendEmptyFrameAfter($gifServer)
{
    return function ($request, $response) use ($gifServer) {
        $gifServer($request, $response);
        $gifServer->addFrame(createGifFrame(['']));
    };
}

$loop = React\EventLoop\Factory::create();
$socket = new React\Socket\Server($loop);
$http = new React\Http\Server($socket);
$gifServer = new React\Gifsocket\Server($loop);

$messages = [];
$addMessage = function ($message) use ($gifServer, &$messages) {
    $messages[] = $message;
    if (count($messages) > 18) {
        $messages = array_slice($messages, count($messages) - 18);
    }

    $frame = createGifFrame($messages);
    $gifServer->addFrame($frame);
};

$stdin = new React\Stream\Stream(STDIN, $loop);
$stdin->on('data', function ($data) use ($addMessage) {
    $messages = explode("\n", trim($data));
    foreach ($messages as $message) {
        $addMessage($message);
    }
});

$router = new React\Gifsocket\Router([
    '/' => sendEmptyFrameAfter($gifServer),
]);

$http->on('request', $router);

$hostname = isset($argv[1]) ? $argv[1] : '127.0.0.1';

echo "Webserver running on {$hostname}\n";

$socket->listen(8080, $hostname);
$loop->run();
