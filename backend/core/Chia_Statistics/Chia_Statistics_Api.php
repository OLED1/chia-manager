<?php
  namespace ChiaMgmt\Chia_Statistics;
  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The Chia_Statistics_Api class contains every needed methods to show historical data for this chia instance.
   * It manages values regarding chia netspace and other chia information.
   * This class is used by the webclient to get data.
   * @see https://xchscan.com/
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.1
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Chia_Statistics_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Nodes Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds a system config json array.
     * @var array
     */
    private $ini;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this, $server);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

    /**
     * Returns hours of netspace history data queried from this system stated by the user.
     * @param  array  $data       { "from" : [Timestamp YYYY-MM-DD H:i:s], "to" : [Timestamp YYYY-MM-DD H:i:s]}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": { [DB stored and found values] } }
     */
    public function getNetspaceHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            $netspace_history = Promise\resolve((new DB_Api())->execute("SELECT querydate, netspace FROM chia_overall WHERE querydate BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND id mod 3 = 0 ORDER BY querydate ASC", array($data["from"], $data["to"])));
            $netspace_history->then(function($netspace_history_returned) use($resolve, $data){

              $historynetspace = $netspace_history_returned->resultRows;

              foreach($historynetspace AS $arrkey => $netspacedata){
                $historynetspace[$arrkey]["netspace"] = explode(" ", $netspacedata["netspace"])[0];
              }

              $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $historynetspace));
            })->otherwise(function(\Exception $e) use($resolve){
              $resolve($this->logging_api->getErrormessage("getNetspaceHistory", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("getNetspaceHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getNetspaceHistory", "003"));
        }
      };
      
      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns hours of blockheight history data queried from this system stated by the user.
     * @param  array  $data       { "from" : [Timestamp YYYY-MM-DD H:i:s], "to" : [Timestamp YYYY-MM-DD H:i:s]}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": { [DB stored and found values] } }
     */
    public function getBlockheightHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            $blockheight_history = Promise\resolve((new DB_Api())->execute("SELECT querydate, xch_blockheight FROM chia_overall WHERE querydate BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND id mod 3 = 0 ORDER BY querydate ASC", array($data["from"], $data["to"])));
            $blockheight_history->then(function($blockheight_history_returned) use($resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $blockheight_history_returned->resultRows));
            })->otherwise(function(\Exception $e) use($resolve){
              $resolve($this->logging_api->getErrormessage("getBlockheightHistory", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("getBlockheightHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getBlockheightHistory", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns hours of xch value history data queried from this system stated by the user. The data are returned in USD.
     * @param  array  $data       { "from" : [Timestamp YYYY-MM-DD H:i:s], "to" : [Timestamp YYYY-MM-DD H:i:s]}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": { [DB stored and found values] } }
     */
    public function getXCHValueHistory(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("from", $data) && array_key_exists("to", $data)){
          if(strtotime($data["from"]) &&  strtotime($data["to"]) && new \DateTime($data["from"]) < new \DateTime($data["to"])){
            $xch_history = Promise\resolve((new DB_Api())->execute("SELECT querydate, price_usd FROM chia_overall WHERE querydate BETWEEN CAST(? AS DATETIME) AND CAST(? AS DATETIME) AND id mod 3 = 0 ORDER BY querydate ASC", array($data["from"], $data["to"])));
            $xch_history->then(function($xch_history_returned) use($resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully loaded data between {$data["from"]} and {$data["to"]}.", "data" => $xch_history_returned->resultRows));
            })->otherwise(function(\Exception $e) use($resolve){
              $resolve($this->logging_api->getErrormessage("getXCHValueHistory", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("getXCHValueHistory", "002"));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("getXCHValueHistory", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
