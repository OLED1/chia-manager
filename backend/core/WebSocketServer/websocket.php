<?php
  use Ratchet\Server\IoServer;
  use Ratchet\Http\HttpServer;
  use Ratchet\WebSocket\WsServer;
  use ChiaMgmt\WebSocketServer\ChiaWebSocketServer;

  require __DIR__ . '/../../../vendor/autoload.php';

  $ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
  $server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new ChiaWebSocketServer()
        )
    ),
    $ini["socket_local_port"]
  );

  $server->run();
?>
