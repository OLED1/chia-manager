<?php
  use Ratchet\Server\IoServer;
  use Ratchet\Http\HttpServer;
  use Ratchet\WebSocket\WsServer;
  use ChiaMgmt\WebSocketServer\ChiaWebSocketServer;

  require __DIR__ . '/../../../vendor/autoload.php';

  $ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');

  $wsServer = new WsServer(new ChiaWebSocketServer());
  $server = IoServer::factory(
    new HttpServer(
      $wsServer
    ),
    $ini["socket_local_port"]
  );

  $wsServer->enableKeepAlive($server->loop, 10);
  $server->run();
?>
