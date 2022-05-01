<?php
  namespace ChiaMgmt\Alerting;
  use ChiaMgmt\Alerting\Additional_Functions\AlertingServices;
  use ChiaMgmt\Alerting\Additional_Functions\AlertingDowntimes;
  use ChiaMgmt\DB\DB_Api;

  /**
   * The Alerting_Api class manages the setup of available server like Gotify, alerting rules and sending alerting using available platform (E-Mail, Gotify, etc.).
   * This class is mainly used by the web(/app) client.
   * @version 0.2
   * @author OLED1 - Oliver Edtmair
   * @since 0.2
   * @copyright Copyright (c) 2022, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Alerting_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the AlertingServices Class.
     * @var DB_Api
     */
    private $alerting_services;
    /**
     * Holds an instance to the AlertingDowntimes Class.
     * @var AlertingDowntimes
     */
    private $alerting_downtimes;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
        $this->alerting_services = new AlertingServices();
        $this->alerting_downtimes = new AlertingDowntimes();
        $this->db_api = new DB_Api();
    }

    /**
     * Returns all available alerting services and there enabled status.
     *
     * @return array
     */
    public function getAvailableServices(array $data = []): array
    {
      try{
        $where_statement = "";
        $service_ids = [];
        if(array_key_exists("service_ids", $data) && is_array($data["service_ids"])){
          $where_statement = "WHERE id in (?)";
          $service_ids = [implode(", ",$data["service_ids"])];
        }

        $sql = $this->db_api->execute("SELECT id, service_id, service_name, service_description, enabled FROM alerting_services $where_statement", $service_ids);
        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $alerting_service){
          $returnarray[$alerting_service["id"]] = $alerting_service;
        }
        return array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $returnarray);
      }catch(\Exception $e){
        //TODO Implement correct status code
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
     */
    public function enableService(array $data = []): array
    {
      if(array_key_exists("alerting_service_id", $data) && is_numeric($data["alerting_service_id"]) && array_key_exists("enabled", $data) && ($data["enabled"] == 0 || $data["alerting_service_id"] == 1)){
        try{
          $sql = $this->db_api->execute("UPDATE alerting_services SET enabled = ? WHERE id = ?", array((int)$data["enabled"], $data["alerting_service_id"]));
          
          return array("status" => 0, "message" => "Successfully set service ({$data["alerting_service_id"]}) to {$data["enabled"]}", "data" => $this->getAvailableServices(["service_ids" => [$data["alerting_service_id"]]])["data"]);
        }catch(\Exception $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Stated data not valid.");
      }
    }

    /**
     * Adds a new custom rule to the databse.
     *
     * @param array $data
     * @return array
     */
    public function addCustomRule(array $data): array
    {    
      print_r($data);
      
      if(array_key_exists("nodeid", $data) && array_key_exists("service_type", $data) && array_key_exists("service_type", $data) && 
        array_key_exists("service_type", $data) && array_key_exists("service_name", $data) && array_key_exists("warn_at_after", $data) && array_key_exists("crit_at_after", $data)){

        $found_type = $this->getAvailableRuleTypesAndServices(["typeid" => $data["service_type"], "nodeid" => $data["nodeid"]]);

        echo "Found type:";
        print_r($found_type);

        if(array_key_exists("data", $found_type) && array_key_exists($data["service_type"], $found_type["data"]) && 
          array_key_exists("available_services", $found_type["data"][$data["service_type"]]) && array_key_exists($data["nodeid"], $found_type["data"][$data["service_type"]]["available_services"]) &&
          in_array($data["service_name"], $found_type["data"][$data["service_type"]]["available_services"][$data["nodeid"]]["configurable_services"])){           
          try{
            $found_type = $found_type["data"];
            $rule_target = ($found_type[$data["service_type"]]["rule_target_needed"] == 0 ? NULL : $data["service_name"]);
            $perc_or_min = $found_type[$data["service_type"]]["perc_or_min"];           
            $monitor = (array_key_exists("monitor", $data) ? intval(boolval($data["monitor"])) : 1 );
            
            $sql = $this->db_api->execute("INSERT INTO alerting_rules (id, system_target, rule_type, rule_target, rule_default, perc_or_min_value, warn_at_after, crit_at_after, monitor) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)", 
                                          array($data["nodeid"], $data["service_type"], $rule_target, 0, $perc_or_min, $data["warn_at_after"], $data["crit_at_after"], $monitor));

            $new_rule_id = $this->db_api->lastInsertId();

            $new_rule = $this->getConfiguredRules(["rule_id" => $new_rule_id]);
            if(array_key_exists("data", $new_rule)){
              $sql = $this->db_api->execute("UPDATE chia_infra_available_services
                                              SET refers_to_rule_id = ?
                                              WHERE (service_target = ? OR service_target = '' OR service_target IS NULL) AND node_id = ? AND service_type = ? 
                                              ORDER BY id DESC
                                              LIMIT 1", 
              array($new_rule_id, $rule_target, $data["nodeid"], $data["service_type"]));

              return array("status" => 0, "message" => "Successfully created new custom rule.", "data" => $new_rule["data"]);
            }else{
              return $new_rule;
            }
  
          }catch(\Exception $e){
            //TODO Implement correct status code
            print_r($e);
            return array("status" => 1, "message" => "An error occured.");  
          }
        }else{
          //TODO Implement correct status code
          return array("status" => 1, "message" => "Stated type {$data["service_type"]} seems not to be valid or this service is already configured for node {$data["nodeid"]}.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Stated data not valid.");
      }
    }

    /**
     * Edit a configured default or custom rule by stating the new values and the id.
     *
     * @param array $data
     * @return array
     */
    public function editConfiguredRule(array $data): array
    {
      if(array_key_exists("rule_id", $data) && is_numeric($data["rule_id"]) && $data["rule_id"] > 0 &&
          array_key_exists("warn_level", $data) && is_numeric($data["warn_level"]) && 
          array_key_exists("crit_level", $data) && is_numeric($data["crit_level"])){
        try{
          $sql = $this->db_api->execute("UPDATE alerting_rules SET warn_at_after = ?, crit_at_after = ? WHERE id = ?", array($data["warn_level"],$data["crit_level"], $data["rule_id"]));

          return array("status" => 0, "message" => "Successfully edited value of rule with ID ({$data["rule_id"]}). Set warn level to {$data["warn_level"]} and crit level to {$data["crit_level"]}.", "data" => array("rule_id_changed" => $data["rule_id"], "saved_values" => $this->getConfiguredRules()["data"]));
        }catch(\Exception $e){
           //TODO Implement correct status codes
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    /**
     * Remove a configured custom rule by stating the current rule id.
     *
     * @param array $data
     * @return array
     */
    public function removeConfiguredCustomRule(array $data): array
    {
      try{
        if(array_key_exists("rule_id", $data)){ 
          $sql = $this->db_api->execute("SELECT Count(*) as count, system_target FROM alerting_rules WHERE id = ? AND rule_default = 0", array($data["rule_id"]));
          $found_entries = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];

          if($found_entries["count"] == 1){
            $sql = $this->db_api->execute("DELETE FROM alerting_rules WHERE id = ? AND rule_default = 0", array($data["rule_id"]));

            return array("status" => 0, "message" => "Successfully removed rule with ID {$data["rule_id"]}", "data" => array("rule_id" => $data["rule_id"], "node_id" => $found_entries["system_target"]));
          }else{
            //TODO Implement correct status code
            return array("status" => 1, "message" => "There was no custom rule for rule with ID {$data["rule_id"]} found.");
          }
        }else{
          //TODO Implement correct status code
          return array("status" => 1, "message" => "Not all data stated.");
        }
      }catch(\Exception $e){
          //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns all configured rules available on the database. There are some filters available.
     *
     * @param array $data
     * @return array
     */
    public function getConfiguredRules(array $data = []): array
    {
      //print_r($data);
      
      $where_statement = "";
      $statement_array = [];
      if(array_key_exists("rule_type", $data) && is_numeric($data["rule_type"]) && $data["rule_type"] > 0){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ar.rule_type = ?";
        array_push($statement_array, $data["rule_type"]);
      }

      if(array_key_exists("rule_default", $data) && is_numeric($data["rule_default"]) && ($data["rule_default"] == 0 || $data["rule_default"] == 1)){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ar.rule_default = ?";
        array_push($statement_array, $data["rule_default"]);
      }

      if(array_key_exists("rule_target", $data)){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ar.rule_target = ?";
        array_push($statement_array, $data["rule_target"]);
      }

      if(array_key_exists("rule_id", $data)){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ar.id = ?";
        array_push($statement_array, $data["rule_id"]);
      }

      if(array_key_exists("monitor", $data)){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ar.monitor = ?";
        array_push($statement_array, $data["monitor"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT ar.id, ar.system_target, n.id as node_id, n.hostname, ar.rule_type, cist.service_desc, cist.perc_or_min, 
                                        (CASE WHEN (ar.rule_target IS NULL OR ar.rule_target = '') AND cist.perc_or_min = 1 THEN 'total downtime'
                                              WHEN (ar.rule_target IS NULL OR ar.rule_target = '') AND cist.perc_or_min = 0 THEN 'total usage'
                                              ELSE ar.rule_target
                                        END) AS rule_target,
                                        ar.rule_default, ar.perc_or_min_value, ar.warn_at_after, ar.crit_at_after, ar.monitor
                                      FROM alerting_rules ar
                                      JOIN nodes n ON n.id = ar.system_target
                                      JOIN chia_infra_service_types cist ON cist.id = ar.rule_type
                                      $where_statement", $statement_array);

        $returnarray = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $rule){
          $returnarray["by_rule_default"][$rule["rule_default"]][$rule["id"]] = $rule;
          $returnarray["by_rule_id"][$rule["id"]] = $rule;
          $returnarray["by_node_id"][$rule["node_id"]][$rule["id"]] = $rule;
        }
        
        return array("status" => 0, "message" => "Successfully loaded all found rules.", "data" => $returnarray);
      }catch(\Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }
    
    /**
     * Returns all or host specific available rule types and connected services.
     *
     * @param array $data
     * @return array
     */
    public function getAvailableRuleTypesAndServices(array $data = []): array
    {
      try{
        $where_statement = "";
        $statement_array = [];
        if(array_key_exists("typeid", $data) && is_numeric($data["typeid"])){
          $where_statement .= "WHERE id = ?";
          array_push($statement_array, $data["typeid"]);
        }

        $sql = $this->db_api->execute("SELECT id, service_desc, perc_or_min, api_data_function, rule_target_needed
                                        FROM chia_infra_service_types $where_statement", $statement_array);

        $found_alerting_types = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $avail_service_array = [];
        if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])) $avail_service_array["nodeid"] = $data["nodeid"];

        $returnarray = [];
        foreach($found_alerting_types AS $arrkey => $this_alerting_service){
          if(!is_null($this_alerting_service["api_data_function"])){
            if(!array_key_exists($this_alerting_service["id"], $returnarray)){
              $returnarray[$this_alerting_service["id"]] = [];
              $returnarray[$this_alerting_service["id"]] = $this_alerting_service;
              $returnarray[$this_alerting_service["id"]]["available_services"] = [];
            }
            $returned_data = $this->alerting_services->{$this_alerting_service["api_data_function"]}($avail_service_array);
            if(array_key_exists("nodeid", $data))
              $returnarray[$this_alerting_service["id"]]["available_services"][$data["nodeid"]] = (array_key_exists("data", $returned_data) && array_key_exists($data["nodeid"], $returned_data["data"]) ? $returned_data["data"][$data["nodeid"]] : []);
            else
              $returnarray[$this_alerting_service["id"]]["available_services"] = (array_key_exists("data", $returned_data) ? $returned_data["data"] : []);
          }
        }

        return array("status" => 0, "message" => "Successfully loaded all available configurations.", "data" => $returnarray);
      }catch(\Exeption $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Returns all or a specific configured alerting rules. This rules can be filtered by rule_id or by alerting_service id.
     *
     * @param array $data
     * @return array
     */
    public function getConfiguredAlertingRules(array $data = []): array
    {
      $where_statement = "";
      $statement_array = [];
      if(array_key_exists("rule_id", $data)){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ap.rule_id = ?";
        array_push($statement_array, $data["rule_id"]);
      }

      if(array_key_exists("node_id", $data)){
        if(empty($where_statement)) $where_statement .= "WHERE ";
        else $where_statement .= "AND ";
        $where_statement .= " ap.rule_node_target = ?";
        array_push($statement_array, $data["node_id"]);
      }

      if(array_key_exists("alerting_service", $data)){
        
      }

      if(array_key_exists("alerting_procedure_id", $data)){
        
      }

      try{
        $sql = $this->db_api->execute("SELECT ap.id, ap.rule_id, ac.user_id AS alerting_user_id, ap.rule_node_target, ap.alerting_service, ar.system_target, ap.warn_alert_after, ap.crit_alert_after, ar.monitor
                                        FROM `alerting_procedure` ap
                                        JOIN `alerting_rules` ar ON ar.id = ap.rule_id
                                        LEFT JOIN `alerting_contact`ac ON ac.alerting_procedure_id = ap.id
                                        $where_statement", $statement_array);

        $found_alerting_rules = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $returnarray = [];
        foreach($found_alerting_rules AS $arrkey => $this_alerting_rule){
          if(!array_key_exists("by_rule_id", $returnarray)) $returnarray["by_rule_id"] = [];
          if(!array_key_exists($this_alerting_rule["rule_id"], $returnarray["by_rule_id"])) $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]] = [];
          if(!array_key_exists($this_alerting_rule["rule_node_target"], $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]])) $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]][$this_alerting_rule["rule_node_target"]] = [];
          if(!array_key_exists($this_alerting_rule["alerting_service"], $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]][$this_alerting_rule["rule_node_target"]])){
            $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["alerting_service"]] = $this_alerting_rule;
            if($this_alerting_rule["alerting_user_id"] != NULL){
              $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"][0] = $this_alerting_rule["alerting_user_id"];
            }else{
              $returnarray["by_rule_id"][$this_alerting_rule["rule_id"]][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"] = [];
            }
          }else{
            array_push($returnarray["by_rule_id"][$this_alerting_rule["rule_id"]][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"], $this_alerting_rule["alerting_user_id"]);
          }
          
          if(!array_key_exists("by_alerting_service", $returnarray)) $returnarray["by_alerting_service"] = [];
          if(!array_key_exists($this_alerting_rule["alerting_service"], $returnarray["by_alerting_service"])) $returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]] = [];
          if(!array_key_exists($this_alerting_rule["rule_id"], $returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]])){
            $returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]][$this_alerting_rule["rule_id"]] = $this_alerting_rule;
            $returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]][$this_alerting_rule["rule_id"]]["alerting_user_ids"][0] = $this_alerting_rule["alerting_user_id"];
            if($this_alerting_rule["alerting_user_id"] != NULL){
              $returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]][$this_alerting_rule["rule_id"]]["alerting_user_ids"][0] = $this_alerting_rule["alerting_user_id"];
            }else{
              $returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]][$this_alerting_rule["rule_id"]]["alerting_user_ids"] = [];
            }
          }else{
            array_push($returnarray["by_alerting_service"][$this_alerting_rule["alerting_service"]][$this_alerting_rule["rule_id"]]["alerting_user_ids"], $this_alerting_rule["alerting_user_id"]);
          }

          if(!array_key_exists("by_rule_node_target", $returnarray)) $returnarray["by_rule_node_target"] = [];
          if(!array_key_exists($this_alerting_rule["rule_node_target"], $returnarray["by_rule_node_target"])) $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]] = [];
          if(!array_key_exists($this_alerting_rule["rule_id"], $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]])) $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]] = [];
          if(!array_key_exists($this_alerting_rule["alerting_service"], $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]])){
            $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]][$this_alerting_rule["alerting_service"]] = $this_alerting_rule;
            $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"][0] = $this_alerting_rule["alerting_user_id"];
            if($this_alerting_rule["alerting_user_id"] != NULL){
              $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"][0] = $this_alerting_rule["alerting_user_id"];
            }else{
              $returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"] = [];
            }
          }else{
            array_push($returnarray["by_rule_node_target"][$this_alerting_rule["rule_node_target"]][$this_alerting_rule["rule_id"]][$this_alerting_rule["alerting_service"]]["alerting_user_ids"], $this_alerting_rule["alerting_user_id"]);
          }

        }

        return array("status" => 0, "message" => "Successfully loaded all available alerting rules.", "data" => $returnarray);
      }catch(\Exception $e){
          //TODO Implement correct status codes
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
    }
    
    /**
     * Add, edit or delete an alerting rule for a specific existing rule ID
     *
     * @param array $data
     * @return array
     */
    public function editAddAlertingRule(array $data): array
    {
      if(array_key_exists("node_id", $data) && is_numeric($data["node_id"]) && $data["node_id"] > 2 &&
        array_key_exists("rule_id", $data) && is_numeric($data["rule_id"]) && $data["rule_id"] > 0 &&
        array_key_exists("alerting", $data) && is_array($data["alerting"]) &&
        array_key_exists("users", $data) && is_array($data["users"])
      ){
        try{
          $rule_id = $data["rule_id"];
          $node_id = $data["node_id"];
          
          foreach($data["alerting"] AS $alerting_service_id => $service_config){
            $sql = $this->db_api->execute("DELETE FROM alerting_procedure WHERE rule_id = ? AND alerting_service = ? AND rule_node_target = ?", 
                                            array($rule_id, $alerting_service_id, $node_id));

            if(is_numeric($alerting_service_id)){
              //-1 = "do not alert", 0 = "immediately alert", >0 = "alert after x minutes"
              if(array_key_exists("warn_exceeds", $service_config) || array_key_exists("crit_exceeds", $service_config)){
                $warn_exceeds = (array_key_exists("warn_exceeds", $service_config) && is_numeric($service_config["warn_exceeds"]) && $service_config["warn_exceeds"] >= 0 ? $service_config["warn_exceeds"] : -1 );
                $crit_exceeds = (array_key_exists("crit_exceeds", $service_config) && is_numeric($service_config["crit_exceeds"]) && $service_config["crit_exceeds"] >= 0 ? $service_config["crit_exceeds"] : -1 );
                
                if($warn_exceeds >= 0 || $crit_exceeds >= 0){
                  $sql = $this->db_api->execute("INSERT INTO alerting_procedure (id, rule_id, rule_node_target, alerting_service, warn_alert_after, crit_alert_after) VALUES (NULL, ?, ?, ?, ?, ?)", 
                                                  array($rule_id, $node_id, $alerting_service_id, $warn_exceeds, $crit_exceeds));

                  $new_procedure_id = $this->db_api->lastInsertId();
                  if(array_key_exists($alerting_service_id, $data["users"])){
                    foreach($data["users"][$alerting_service_id] AS $arrkey => $alerting_user_id){
                      $sql = $this->db_api->execute("INSERT INTO alerting_contact (id, user_id, alerting_procedure_id) VALUES (NULL, ?, ?)", 
                                                      array($alerting_user_id, $new_procedure_id));
                    }
                  }
                }
              }
            }
          }

          $new_alerting_rule = $this->getConfiguredAlertingRules(["rule_id" => $rule_id, "node_id" => $node_id]);
          if(array_key_exists("data", $new_alerting_rule)){
            return array("status" => 0, "message" => "Successfully processed service alerting for ID {$data["rule_id"]}.", "data" => array("rule_id" => $data["rule_id"], "node_id" => $node_id, "new_data" => $new_alerting_rule["data"]));
          }else{
            return $new_alerting_rule;
          }
        
        }catch(\Exception $e){
          //TODO Implement correct status codes
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }

      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated. Nothing to save.");   
      }
    }

    /**
     * Returns the current applicable alerting rule id of a specific service by stating the service_type_id, node_id and if available service_target.
     *
     * @param array $data
     * @return array
     */
    public function getRuleInformationOfService(array $data): array
    {     
      if(array_key_exists("service_type_id", $data) && array_key_exists("node_id", $data) && 
          (($data["service_type_id"] == 9 && array_key_exists("service_target", $data)) || $data["service_type_id"] < 9)){
            $target_service_statement = "IS NOT NULL";
            if($data["service_type_id"] == 9) $target_service_statement = "= '{$data["service_target"]}'";  

          try{
            $sql = $this->db_api->execute("SELECT id, system_target, rule_type, rule_target, rule_default, perc_or_min_value, warn_at_after, crit_at_after, monitor
                                            FROM alerting_rules
                                            WHERE rule_target {$target_service_statement} AND system_target = ? AND rule_type = ?
            
                                            UNION ALL
            
                                            SELECT id, system_target, rule_type, rule_target, rule_default, perc_or_min_value, warn_at_after, crit_at_after, monitor
                                            FROM alerting_rules
                                            WHERE rule_target IS NULL
                                                  AND NOT EXISTS (SELECT 1
                                                              FROM alerting_rules
                                                              WHERE rule_target {$target_service_statement} AND system_target = ? AND rule_type = ?)
                                                  AND system_target = 1 AND rule_type = ?", array($data["node_id"], $data["service_type_id"], $data["node_id"], $data["service_type_id"], $data["service_type_id"]));
            
            $found_service_rule_information = $sql->fetchAll(\PDO::FETCH_ASSOC);  

            return array("status" => 0, "message" => "Rule id was found.", "data" => $found_service_rule_information[0]);
        }catch(Exception $e){
            //TODO Implement correct status codes
            print_r($e);
            return array("status" => 1, "message" => "An error occured.");
          }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");   
      }
    }

    /**
     * Calculates the alerting level of given values and returns the result.
     * 1 = OK, 2 = WARN, 3 = CRIT, 4 = UNKN
     *
     * @param array $data
     * @return array
     */
    public function calculateAlertingLevel(array $data): array
    {
      if(array_key_exists("current_service_level", $data) && array_key_exists("perc_or_min_value", $data) &&
        array_key_exists("warn_level_at_after", $data) && array_key_exists("crit_level_at_after", $data) &&
        (($data["perc_or_min_value"] == 0 && array_key_exists("defined_maximum", $data) || ($data["perc_or_min_value"] == 1 && array_key_exists("current_service_minutes", $data))))){ 

        $returndata = [];
        if($data["perc_or_min_value"] == 0){
          $current_usage = $data["current_service_level"] * 100 / $data["defined_maximum"];

          if($current_usage <= $data["warn_level_at_after"]) $returndata = array("time_or_usage" => $current_usage, "level" => 1);
          else if($current_usage > $data["warn_level_at_after"] && $current_usage <= $data["crit_level_at_after"]) $returndata = array("percent_usage" => $current_usage, "level" => 2);
          else $returndata = array("time_or_usage" => $current_usage, "level" => 3);
        }else if($data["perc_or_min_value"] == 1){
          if($data["current_service_level"] == 1){
            $returndata = array("time_or_usage" => $data["current_service_minutes"], "level" => 1);
          }else if($data["current_service_level"] == 0){
            $level = 1;
            if($data["current_service_minutes"] >= $data["warn_level_at_after"]) $level = 2;
            if($data["current_service_minutes"] >= $data["crit_level_at_after"]) $level = 3;

            $returndata = array("time_or_usage" => $data["current_service_minutes"], "level" => $level);
          }
        }else{
          $returndata = array("time_or_usage" => NULL, "level" => 4);
        }

        return array("status" => 0, "message" => "Successfully calculated current service state.", "data" => $returndata);            
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated."); 
      }
    }

    public function alertAllFoundWARNandCRIT(array $data = []): array
    {
      try{
        $statement_string = "n.id = (
          SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
        )";
        $statement_array = [];
        if(array_key_exists("node_id", $data) && is_numeric($data["nodeid"])){
          $statement_string = "n.id = ?";
          array_push($statement_array, $data["node_id"]);
        }

        $sql = $this->db_api->execute("
          SELECT n.id, n.hostname, cias.id AS avail_serv_id, cias.service_type, cist.service_desc, avs.state_short AS current_state_short, avs1.state_short AS prev_state_short, cias.service_target, cias.time_or_usage, ar.perc_or_min_value,
          (CASE WHEN cias.service_state = 1 AND cias1.service_state IS NULL THEN 0
                WHEN cias.service_state = cias1.service_state THEN 0
                WHEN cias.service_state = 1 THEN 1
                WHEN cias.service_state = 2 AND TIMESTAMPDIFF(MINUTE,cias.service_state_first_reported,NOW()) >= ap.warn_alert_after AND ap.warn_alert_after > -1 THEN 1
                WHEN cias.service_state = 3 AND TIMESTAMPDIFF(MINUTE,cias.service_state_first_reported,NOW()) >= ap.crit_alert_after AND ap.crit_alert_after > -1 THEN 1
                WHEN cias.service_state = 4 THEN 1
                ELSE 0
          END) AS alert_service_now, TIMESTAMPDIFF(MINUTE,cias.service_state_first_reported,NOW()) AS state_since, ac.user_id AS alert_to_user, u.name, u.lastname, u.username, u.email, ass.id AS alerting_service_id, ass.service_id AS alerting_service_desc,
          (CASE WHEN ad.downtime_comment IS NULL THEN 0
                ELSE 1
          END) AS downtime_active
          FROM nodes n
          LEFT JOIN chia_infra_available_services cias ON cias.id = (SELECT ciassub.id
                                                                    FROM chia_infra_available_services ciassub
                                                                    WHERE ciassub.service_target = cias.service_target AND ciassub.service_type = cias.service_type AND ciassub.node_id = n.id
                                                                    ORDER BY ciassub.service_state_first_reported DESC
                                                                    LIMIT 1)
          LEFT JOIN chia_infra_available_services cias1 ON cias1.id = (SELECT
                                                                          (SELECT ciassub2.id FROM chia_infra_available_services ciassub2
                                                                                              WHERE ciassub2.id < ciassub1.id AND ciassub2.node_id = ciassub1.node_id AND ciassub2.service_type = ciassub1.service_type AND ciassub2.service_target = ciassub1.service_target
                                                                                              ORDER BY ciassub2.id DESC LIMIT 1) as previous_id
                                                                          FROM chia_infra_available_services ciassub1
                                                                          WHERE ciassub1.id = cias.id AND ciassub1.node_id = n.id AND ciassub1.service_type = cias.service_type AND ciassub1.service_target = cias.service_target)
          LEFT JOIN alerting_rules ar on ar.id = cias.refers_to_rule_id
          LEFT JOIN chia_infra_service_types cist ON cist.id = cias.service_type
          LEFT JOIN alerting_available_states avs ON avs.id = cias.service_state
          LEFT JOIN alerting_available_states avs1 ON avs1.id = cias1.service_state
          LEFT JOIN alerting_procedure ap ON ap.rule_id = ar.id AND ap.rule_node_target = n.id
          LEFT JOIN alerting_contact ac ON ac.alerting_procedure_id = ap.id
          LEFT JOIN alerting_downtimes ad ON ad.node_id = n.id AND NOW() BETWEEN ad.downtime_from AND ad.downtime_to
          JOIN alerting_services ass ON ass.id = ap.alerting_service AND ass.enabled = 1
          JOIN users u ON u.id = ac.user_id AND u.enabled = 1
          WHERE {$statement_string} AND ar.monitor = 1 AND NOT EXISTS ( SELECT 1 FROM alerting_history ah WHERE ah.avail_alerting_serv_id = cias.id  AND ah.service_alerted_using = ass.id )
          HAVING alert_service_now = 1 AND alert_to_user IS NOT NULL AND downtime_active = 0
          ORDER BY n.id ASC, cias.service_type ASC, cias.curr_service_insert_id ASC"
        , $statement_array);

        $found_alertings = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $alerting_services_array = [];
        foreach($found_alertings AS $arrkey => $this_alert){
          if(!array_key_exists($this_alert["id"],$alerting_services_array))
          $alerting_services_array[$this_alert["id"]] = [];
          
          if(!array_key_exists($this_alert["service_type"], $alerting_services_array[$this_alert["id"]]))
          $alerting_services_array[$this_alert["id"]][$this_alert["service_type"]] = [];
            
          $this_namespace = "ChiaMgmt\Alerting\Alerting_Services\Alerting_{$this_alert["alerting_service_desc"]}\Alerting_{$this_alert["alerting_service_desc"]}";
          if(!array_key_exists($this_alert["alerting_service_desc"], $alerting_services_array[$this_alert["id"]][$this_alert["service_type"]]) && 
            class_exists($this_namespace)){

            $alerting_services_array[$this_alert["id"]][$this_alert["service_type"]][$this_alert["alerting_service_desc"]] = new $this_namespace;
          }

          if(array_key_exists($this_alert["alerting_service_desc"], $alerting_services_array[$this_alert["id"]][$this_alert["service_type"]]) && method_exists($alerting_services_array[$this_alert["id"]][$this_alert["service_type"]][$this_alert["alerting_service_desc"]],'queueNewMessage')){
            $alerting_services_array[$this_alert["id"]][$this_alert["service_type"]][$this_alert["alerting_service_desc"]]->queueNewMessage($this_alert);
          }
        }

        foreach($alerting_services_array AS $alert_id => $service_types){
          foreach($service_types AS $service_type_id => $found_alerting_services){
            foreach($found_alerting_services AS $alerting_service_id => $this_service_obj){
              if(method_exists($this_service_obj,'sendQueuedMessages')){
                $alerted_messages = $this_service_obj->sendQueuedMessages();
                $this->updateAlertedServicesHistory($alerted_messages["data"]);
              }
            }
          }
        }
        
        return array("status" => 0, "message" => "Successfully alerted all configured and found WARN, CRIT and UNKN messages."); 
      }catch(\Exception $e){
        //TODO Implement correct status code
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    /**
     * Updates the alerting_history and alerting_hsitory_alerted_to table with all successfully alerted services.
     *
     * @param array $alerted_message (Object of type Alertingdata)
     * @return array
     */
    private function updateAlertedServicesHistory(array $alerted_message): array
    {
      try{
        foreach($alerted_message AS $arrkey => $this_alerted_message){
          $avail_serv_id = $this_alerted_message->get_avail_serv_id();
          $service_alerted_using = $this_alerted_message->get_alerting_service_id();
          $state_changed_from = $this_alerted_message->get_prev_state_short();
          $state_changed_to = $this_alerted_message->get_current_state_short();
          $alerted_to = $this_alerted_message->get_user_id();
          $alert_contact = $this_alerted_message->get_contact();

          $sql = $this->db_api->execute("SELECT id
                                        FROM `alerting_history` 
                                        WHERE avail_alerting_serv_id = ? and service_alerted_using = ? LIMIT 1", array($avail_serv_id, $service_alerted_using));

          $found_data = $sql->fetchAll(\PDO::FETCH_ASSOC);

          $alerting_history_id = 0;
          if(count($found_data) == 1){
            $alerting_history_id = $found_data[0]["id"];
          }else{
            $sql = $this->db_api->execute("INSERT INTO alerting_history (id, avail_alerting_serv_id, service_alerted_using, state_changed_from, state_changed_to, state_alerted_at) 
                                          VALUES (NULL, ?, ?, ?, ?, NOW())", 
                                          array($avail_serv_id, $service_alerted_using, $state_changed_from, $state_changed_to));

            $alerting_history_id = $this->db_api->lastInsertId();
          }

          if($alerting_history_id > 0){
            $sql = $this->db_api->execute("INSERT INTO alerting_history_alerted_to (id, alerting_history_id, user_id, contact) 
                                            VALUES (NULL, ?, ?, ?)", 
                                            array($alerting_history_id, $alerted_to, $alert_contact));
          }
        }
  
        return array("status" => 0, "message" => "Successfully alerted all configured and found WARN, CRIT and UNKN messages."); 
      }catch(\Exception $e){
        //TODO Implement correct status code
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }

    }

    /**
     * Returns all services for which a downtime can be configured.
     *
     * @param array $data
     * @return array
     */
    public function getConfigurableDowntimeServices(array $data = []): array
    {
      return $this->alerting_downtimes->getConfigurableDowntimeServices($data);
    }

    /**
     * Creates a new downtime for all or specific services of a certain Chia node
     *
     * @param array $data
     * @return array
     */
    public function setUpNewDowntime(array $data): array
    {
      return $this->alerting_downtimes->setUpNewDowntime($data);
    }

    /**
     * Creates a new downtime for all or specific services of a certain Chia node
     *
     * @param array $data
     * @return array
     */
    public function getSetupDowntimes(array $data = []): array
    {
      return $this->alerting_downtimes->getSetupDowntimes($data);
    }

    /**
     * Edits the information (Comment, Start date, enddate) of one or more downtime(s).
     *
     * @param array $data
     * @return array
     */
    public function editDowntimes(array $data = []): array
    {
      return $this->alerting_downtimes->editDowntimes($data);
    }
  }
?>