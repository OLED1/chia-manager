<?php 
    $init_chia_manager_docker_websocket = new Init_Chia_Manager_Docker_Websocket();

    if($init_chia_manager_docker_websocket->waitForConfigExists()){
        $init_chia_manager_docker_websocket->loadConfig();
    }else{
        exit(1);
    }

    class Init_Chia_Manager_Docker_Websocket{
        private $config_file;

        public function waitForConfigExists(): bool{
            echo "[STEP]Waiting until config.ini.php is existing. Maybe the first init steps are currently executed.\n";
            echo "  [INFO]Waiting:";

            $config_file = __DIR__ . "/../backend/config/config.ini.php";
            $config_file_exists = false;
            while(!$config_file_exists){
                echo ".";
                if(file_exists($config_file)){
                    echo "\n  [SUCC]File detected.\n";
                    $config_file_exists = true;
                }else{
                    sleep(1);
                }
            }

            return true;
        }

        public function loadConfig(): bool{
            $this->config_file = parse_ini_file(__DIR__.'/../../config/config.ini.php');
            return true;
        }

        public function printWebsocketConfig(){
            echo "  [INFO]CM_WEBSOCKET_PORT: " . getenv("CM_WEBSOCKET_PORT") . " CM_WEBSOCKET_LISTENER: " . getenv("CM_WEBSOCKET_LISTENER") . "\n";
        }
    }
?>