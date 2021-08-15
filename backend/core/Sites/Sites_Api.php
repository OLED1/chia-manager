<?php
  namespace ChiaMgmt\Sites;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Sites_Api{
    private $db_api, $logging_api, $ini;

    public function __construct(){
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    public function getSiteInfos(array $data, array $loginData = NULL){
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
        }catch(Exception $e){
          return $this->logging->getErrormessage("002", $e);
        }
      }else{
        return $this->logging->getErrormessage("003");
      }
    }
  }
?>
