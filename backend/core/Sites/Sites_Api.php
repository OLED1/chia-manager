<?php
  namespace ChiaMgmt\Sites;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The Sites_Api class contains every needed methods to manage all available frontend available sites.
   * This class is used by the webclient to get data.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Sites_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Logging Class.
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
     * [getSiteInfos description]
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $data       { "siteid" : [The siteid of which the infos are needed] }
     * @param  array $loginData   { NULL } No logindata needed to query this method.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function getSiteInfos(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("siteid", $data)){
        $siteid = $data["siteid"];
        $returndata = [];

        try{
          if(is_null($siteid)){
            $sql = $this->db_api->execute("SELECT s.id, spi.sitetoinform, s.namespace FROM sites s LEFT JOIN sites_pagestoinform spi ON spi.siteid = s.id ORDER by siteid, spi.sitetoinform", array());
          }else if($siteid > 0){
            $sql = $this->db_api->execute("SELECT s.id, spi.sitetoinform, s.namespace FROM sites s LEFT JOIN sites_pagestoinform spi ON spi.siteid = s.id WHERE s.id = ? ORDER by siteid, spi.sitetoinform", array($siteid));
          }else{
            return $this->logging->getErrormessage("001");
          }

          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
          $returndata["by-id"] = [];
          $returndata["by-namespace"] = [];
          foreach ($sqdata as $arrkey => $sitesvalues) {
            if(!array_key_exists($sitesvalues["id"], $returndata["by-id"])){
              $returndata["by-id"][$sitesvalues["id"]] = $sitesvalues;
              $returndata["by-id"][$sitesvalues["id"]]["sitestoinform"] = [];
            }
            unset($returndata["by-id"][$sitesvalues["id"]]["sitetoinform"]);
            array_push($returndata["by-id"][$sitesvalues["id"]]["sitestoinform"], $sitesvalues["sitetoinform"]);

            if(!array_key_exists($sitesvalues["namespace"], $returndata["by-namespace"])){
              $returndata["by-namespace"][$sitesvalues["namespace"]] = $sitesvalues;
              $returndata["by-namespace"][$sitesvalues["namespace"]]["sitestoinform"] = [];
            }
            unset($returndata["by-namespace"][$sitesvalues["namespace"]]["sitetoinform"]);
            array_push($returndata["by-namespace"][$sitesvalues["namespace"]]["sitestoinform"], $sitesvalues["sitetoinform"]);
          }

          return array("status" => 0, "message" => "Successfully loaded site(s) information.", "data" => $returndata);
        }catch(\Exception $e){
          return $this->logging->getErrormessage("002", $e);
        }
      }else{
        return $this->logging->getErrormessage("003");
      }
    }
  }
?>
