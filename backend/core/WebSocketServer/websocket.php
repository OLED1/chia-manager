<?php
  use Ratchet\Server\IoServer;
  use Ratchet\Http\HttpServer;
  use Ratchet\WebSocket\WsServer;
  use ChiaMgmt\WebSocketServer\ChiaWebSocketServer;

  require __DIR__ . '/../../../vendor/autoload.php';

  $server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChiaWebSocketServer()
        )
    ),
    8443
  );

  $server->run();
?>
