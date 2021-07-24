<?php
use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use MyApp\Chat;

  require __DIR__ . '/vendor/autoload.php';

  $server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new Chat()
        )
    ),
    8443
  );

  $server->run();
?>
