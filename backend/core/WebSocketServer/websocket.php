<?php
  use Ratchet\Server\IoServer;
  use Ratchet\Http\HttpServer;
  use Ratchet\WebSocket\WsServer;
  use ChiaMgmt\WebSocketServer\ChiaWebSocketServer;

  require __DIR__ . '/../../../vendor/autoload.php';

  if(exec('whoami') == "root"){
    echo "Running this script as root is not allowed.\n";
    exit();
  }

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
