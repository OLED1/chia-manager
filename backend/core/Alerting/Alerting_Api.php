<?php
  namespace ChiaMgmt\Alerting;
  use React\Promise;
  use ChiaMgmt\Logging\Logging_Api;
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
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the AlertingDowntimes Class.
     * @var AlertingDowntimes
     */
    private $alerting_downtimes;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
        $this->alerting_downtimes = new AlertingDowntimes();
        $this->logging_api = new Logging_Api($this, $server);
    }

    /**
     * Returns all available alerting services and there enabled status.
     * Function made for: Web GUI/App, API
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array $data                    Default: [] (empty) - Returns all availabe services. Can include comma (,) seperated service id's to only return these.
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getAvailableServices(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $where_statement = "";
        $service_ids = [];
        if(array_key_exists("service_ids", $data) && is_array($data["service_ids"])){
          $where_statement = "WHERE id in (?)";
          $service_ids = [implode(", ",$data["service_ids"])];
        }

        $available_services = Promise\resolve((new DB_Api())->execute("SELECT id, service_id, service_name, service_description, enabled FROM alerting_services $where_statement", $service_ids));
        $available_services->then(function($available_services_returned) use(&$resolve){
          $returnarray = [];
          foreach($available_services_returned->resultRows AS $arrkey => $alerting_service){
            $returnarray[$alerting_service["id"]] = $alerting_service;
          }
          $resolve(array("status" => 0, "message" => "Successfully loaded all system settings.", "data" => $returnarray));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getAvailableServices", "001", $e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Enables a certain service.
     * Function made for: Web GUI/App
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     { "alerting_service_id" : <id>, "enabled" : <0/1> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function enableService(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("alerting_service_id", $data) && is_numeric($data["alerting_service_id"]) && array_key_exists("enabled", $data) && ($data["enabled"] == 0 || $data["alerting_service_id"] == 1)){
          $enable_service = Promise\resolve((new DB_Api())->execute("UPDATE alerting_services SET enabled = ? WHERE id = ?", array((int)$data["enabled"], $data["alerting_service_id"])));
          $enable_service->then(function($enable_service_returned) use(&$resolve, $data){
            $get_avail_services = Promise\resolve($this->getAvailableServices(["service_ids" => [$data["alerting_service_id"]]]));
            $get_avail_services->then(function($get_avail_services_returned) use(&$resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully set service ({$data["alerting_service_id"]}) to {$data["enabled"]}", "data" => $get_avail_services_returned["data"]));
            });
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("enableService", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("enableService", "002"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Adds a new custom rule to the databse which will be applied immediately.
     * Function made for: Web GUI/App
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     { "nodeid" : <int>, "service_type" : <int>, "service_name" : <string>, "warn_at_after" : <int>, "crit_at_after" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function addCustomRule(array $data): object
    {         
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("nodeid", $data) && array_key_exists("service_type", $data) && array_key_exists("service_type", $data) && 
          array_key_exists("service_type", $data) && array_key_exists("service_name", $data) && array_key_exists("warn_at_after", $data) && 
          array_key_exists("crit_at_after", $data)
        ){
          $available_rule_types = Promise\resolve($this->getAvailableRuleTypesAndServices(["typeid" => $data["service_type"], "nodeid" => $data["nodeid"]]));
          $available_rule_types->then(function($available_rule_types_returned) use(&$resolve, $data){
            $found_type = $available_rule_types_returned;

            if(array_key_exists("data", $found_type) && array_key_exists($data["service_type"], $found_type["data"]) && 
              array_key_exists("available_services", $found_type["data"][$data["service_type"]]) && array_key_exists($data["nodeid"], $found_type["data"][$data["service_type"]]["available_services"]) &&
              in_array($data["service_name"], $found_type["data"][$data["service_type"]]["available_services"][$data["nodeid"]]["configurable_services"])
            ){              
              $found_type = $found_type["data"];
              $rule_target = ($found_type[$data["service_type"]]["rule_target_needed"] == 0 ? NULL : $data["service_name"]);
              $perc_or_min = $found_type[$data["service_type"]]["perc_or_min"];           
              $monitor = (array_key_exists("monitor", $data) ? intval(boolval($data["monitor"])) : 1 );

              echo "MONITOR: $monitor\n";

              $add_rule = Promise\resolve((new DB_Api())->execute("INSERT INTO alerting_rules (id, system_target, rule_type, rule_target, rule_default, perc_or_min_value, warn_at_after, crit_at_after, monitor) VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?)", 
                                            array($data["nodeid"], $data["service_type"], $rule_target, 0, $perc_or_min, $data["warn_at_after"], $data["crit_at_after"], $monitor)));
            
              $add_rule->then(function($add_rule_returned) use(&$resolve, $rule_target, $data){
                $new_rule_id = $add_rule_returned->insertId;

                
                $new_rule = Promise\resolve($this->getConfiguredRules(["rule_id" => $new_rule_id]));
                $new_rule->then(function($new_rule_returned) use(&$resolve, $new_rule_id, $rule_target, $data){
                  if(array_key_exists("data", $new_rule_returned)){


                    $update_dependencies = Promise\resolve((new DB_Api())->execute("UPDATE chia_infra_available_services
                                                                                    SET refers_to_rule_id = ?
                                                                                    WHERE (service_target = ? OR service_target = '' OR service_target IS NULL) AND node_id = ? AND service_type = ? 
                                                                                    ORDER BY id DESC
                                                                                    LIMIT 1", 
                                                                array($new_rule_id, $rule_target, $data["nodeid"], $data["service_type"])));

                    $update_dependencies->then(function($update_dependencies_returned) use(&$resolve, $new_rule_returned){
                      $resolve(array("status" => 0, "message" => "Successfully created new custom rule.", "data" => $new_rule_returned["data"]));
                    })->otherwise(function(\Exception $e) use(&$resolve){
                      $resolve($this->logging_api->getErrormessage("addCustomRule", "001", $e));
                    });
                  }else{
                    $resolve($new_rule_returned);
                  }
                });
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("addCustomRule", "002", $e));
              });
            }else{
              $resolve($this->logging_api->getErrormessage("addCustomRule", "003"));
            }
          });
        }else{
          $resolve($this->logging_api->getErrormessage("addCustomRule", "004"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Edit a configured default or custom rule by stating the new values and the id.
     * Function made for: Web GUI/App
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     { "rule_id" : <int>, "warn_level" : <int>, "crit_level" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function editConfiguredRule(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("rule_id", $data) && is_numeric($data["rule_id"]) && $data["rule_id"] > 0 &&
          array_key_exists("warn_level", $data) && is_numeric($data["warn_level"]) && 
          array_key_exists("crit_level", $data) && is_numeric($data["crit_level"])
        ){
          $edit_rule = Promise\resolve((new DB_Api())->execute("UPDATE alerting_rules SET warn_at_after = ?, crit_at_after = ? WHERE id = ?", array($data["warn_level"],$data["crit_level"], $data["rule_id"])));
          $edit_rule->then(function($edit_rule_returned) use(&$resolve, $data){
            $get_configured_rules = Promise\resolve($this->getConfiguredRules());
            $get_configured_rules->then(function($get_configured_rules_returned) use(&$resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully edited value of rule with ID ({$data["rule_id"]}). Set warn level to {$data["warn_level"]} and crit level to {$data["crit_level"]}.", "data" => array("rule_id_changed" => $data["rule_id"], "saved_values" => $get_configured_rules_returned["data"])));
            });
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("editConfiguredRule", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("editConfiguredRule", "002"));
        } 
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Remove a configured custom rule by stating the current rule id.
     * Function made for: Web GUI/App
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     { "rule_id" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function removeConfiguredCustomRule(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("rule_id", $data)){
          $rule_to_remove = Promise\resolve((new DB_Api)->execute("SELECT Count(*) as count, system_target FROM alerting_rules WHERE id = ? AND rule_default = 0", array($data["rule_id"])));
          $rule_to_remove->then(function($rule_to_remove_returned) use(&$resolve, $data){
            $found_entries = $rule_to_remove_returned->resultRows[0];

            $remove_custom_rule = Promise\resolve((new DB_Api)->execute("DELETE FROM alerting_rules WHERE id = ? AND rule_default = 0", array($data["rule_id"])));
            $remove_custom_rule->then(function($remove_custom_rule_returned) use(&$resolve, $data, $found_entries){
              if($remove_custom_rule_returned->affectedRows > 0){
                $resolve(array("status" => 0, "message" => "Successfully removed rule with ID {$data["rule_id"]}", "data" => array("rule_id" => $data["rule_id"], "node_id" => $found_entries["system_target"])));
              }else{
                $resolve($this->logging_api->getErrormessage("editConfiguredRule", "001", "There was no custom rule for rule with ID {$data["rule_id"]} found."));
              }
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("editConfiguredRule", "002", $e));
            });
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("editConfiguredRule", "003", $e));
          });
          
        }else{  
          $resolve($this->logging_api->getErrormessage("editConfiguredRule", "004"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns all configured rules available on the database. There are some filters available.
     * Function made for: Web GUI/App, Api
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     Default: [] (empty), returns all rules. Optional: { "rule_type" : <int|null>, "rule_default" : <int|null>, "rule_target" : <int|null>, "rule_id" : <int|null>, "monitor" : <int|null> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getConfiguredRules(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
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

        $configured_rules = Promise\resolve((new DB_Api())->execute("SELECT ar.id, ar.system_target, n.id as node_id, n.hostname, ar.rule_type, cist.service_desc, cist.perc_or_min, 
                                                                        (CASE WHEN (ar.rule_target IS NULL OR ar.rule_target = '') AND cist.perc_or_min = 1 THEN 'total downtime'
                                                                              WHEN (ar.rule_target IS NULL OR ar.rule_target = '') AND cist.perc_or_min = 0 THEN 'total usage'
                                                                              ELSE ar.rule_target
                                                                        END) AS rule_target,
                                                                        ar.rule_default, ar.perc_or_min_value, ar.warn_at_after, ar.crit_at_after, ar.monitor
                                                                      FROM alerting_rules ar
                                                                      JOIN nodes n ON n.id = ar.system_target
                                                                      JOIN chia_infra_service_types cist ON cist.id = ar.rule_type
                                                                      $where_statement", $statement_array));
        
        $configured_rules->then(function($configured_rules_returned) use(&$resolve){
          $returnarray = [];
          foreach($configured_rules_returned->resultRows AS $arrkey => $rule){
            $returnarray["by_rule_default"][$rule["rule_default"]][$rule["id"]] = $rule;
            $returnarray["by_rule_id"][$rule["id"]] = $rule;
            $returnarray["by_node_id"][$rule["node_id"]][$rule["id"]] = $rule;
          }
          
          $resolve(array("status" => 0, "message" => "Successfully loaded all found rules.", "data" => $returnarray));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getConfiguredRules", "001", $e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
    
    /**
     * Returns all or host specific available rule types and configured services.
     * Function made for: Web GUI/App, Api
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     Default: [] (empty), returns all rules and types. Optional: { "nodeid" : <int|null>, "typeid" : <int|null> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getAvailableRuleTypesAndServices(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $statement_string = "n.id = (
          SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
        )";
        $statement_array = [];
        if(!is_null($data) && array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])){
          $statement_string = "n.id = ?";
          array_push($statement_array, $data["nodeid"]);
        }

        if(array_key_exists("typeid", $data) && is_numeric($data["typeid"])){
          if(count($statement_array) > 0) $statement_string .= "AND ";
          $statement_string .= "cias.service_type = ?";
          array_push($statement_array, $data["typeid"]);
        }

        $avail_rules_and_services = Promise\resolve((new DB_Api())->execute("SELECT DISTINCT n.id, cias.service_type, cias.refers_to_rule_id, cist.service_desc, cist.perc_or_min, cist.rule_target_needed,
                                                                                    (CASE WHEN cisf.mountpoint IS NULL AND cist.perc_or_min = 0 AND cias.refers_to_rule_id <= (SELECT max(rule_type) FROM alerting_rules WHERE rule_default = 1) THEN 'total percent'
                                                                                          WHEN cisf.mountpoint IS NULL AND cist.perc_or_min = 1 AND cias.refers_to_rule_id <= (SELECT max(rule_type) FROM alerting_rules WHERE rule_default = 1) THEN 'total downtime'
                                                                                          ELSE cisf.mountpoint
                                                                                    END) AS service_target, ar.rule_default
                                                                              FROM nodes n
                                                                              LEFT JOIN chia_infra_available_services cias ON cias.current = 1 AND cias.node_id = n.id
                                                                              LEFT JOIN chia_infra_service_types cist ON cist.id = cias.service_type
                                                                              LEFT JOIN chia_infra_sysinfo_filesystems cisf ON cisf.id = cias.curr_service_insert_id
                                                                              LEFT JOIN alerting_rules ar ON ar.id = cias.refers_to_rule_id
                                                                              WHERE {$statement_string} AND ar.monitor = 1
                                                                              ORDER BY n.id ASC, cias.service_type ASC, service_target ASC", $statement_array));
        
        $avail_rules_and_services->then(function($avail_rules_and_services_returned) use(&$resolve){
          $returnarray = [];
          foreach($avail_rules_and_services_returned->resultRows AS $arrkey => $this_alerting_service){
            if(!array_key_exists($this_alerting_service["service_type"], $returnarray)){
              $returnarray[$this_alerting_service["service_type"]] = [];
              $returnarray[$this_alerting_service["service_type"]] = $this_alerting_service;
              $returnarray[$this_alerting_service["service_type"]]["available_services"] = [];
            } 
  
            if(!array_key_exists($this_alerting_service["id"], $returnarray[$this_alerting_service["service_type"]]["available_services"])) $returnarray[$this_alerting_service["service_type"]]["available_services"][$this_alerting_service["id"]] = [];
            if(!array_key_exists("configurable_services", $returnarray[$this_alerting_service["service_type"]]["available_services"][$this_alerting_service["id"]])) $returnarray[$this_alerting_service["service_type"]]["available_services"][$this_alerting_service["id"]]["configurable_services"] = [];
            if(!is_Null($this_alerting_service["service_target"])) array_push($returnarray[$this_alerting_service["service_type"]]["available_services"][$this_alerting_service["id"]]["configurable_services"], $this_alerting_service["service_target"]);
          }

          $resolve(array("status" => 0, "message" => "Successfully loaded all available configurations.", "data" => $returnarray));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getAvailableRuleTypesAndServices", "001", $e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns all or a specific configured alerting rules. This rules can be filtered by rule_id or by alerting_service id.
     * Function made for: Web GUI/App, Api
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     Default: [] (empty), returns all rules and types. Optional: { "rule_id" : <int|null>, "node_id" : <int|null>, "monitor" : <int|null> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getConfiguredAlertingRules(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
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
  
        if(array_key_exists("monitor", $data)){
          if(empty($where_statement)) $where_statement .= "WHERE ";
          else $where_statement .= "AND ";
          $where_statement .= " ar.monitor = ?";
          array_push($statement_array, $data["monitor"]);
        }
  
        if(array_key_exists("alerting_service", $data)){
          //TODO Implement when needed
        }
  
        if(array_key_exists("alerting_procedure_id", $data)){
          //TODO Implement when needed
        }

        $configured_rules = Promise\resolve((new DB_Api())->execute("SELECT ap.id, ap.rule_id, ac.user_id AS alerting_user_id, ap.rule_node_target, ap.alerting_service, ar.system_target, ap.warn_alert_after, ap.crit_alert_after, ar.monitor
                                                                      FROM `alerting_procedure` ap
                                                                      JOIN `alerting_rules` ar ON ar.id = ap.rule_id
                                                                      LEFT JOIN `alerting_contact`ac ON ac.alerting_procedure_id = ap.id
                                                                      $where_statement", $statement_array));

        $configured_rules->then(function($configured_rules_returned) use(&$resolve){

          $returnarray = [];
          foreach($configured_rules_returned->resultRows AS $arrkey => $this_alerting_rule){
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
  
          $resolve(array("status" => 0, "message" => "Successfully loaded all available alerting rules.", "data" => $returnarray));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getConfiguredAlertingRules", "001", $e));
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
    
    /**
     * Add, edit or delete an alerting rule for a specific existing rule ID.
     * Function made for: Web GUI/App, Api
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     { "node_id" : <int>, "rule_id" : <int>, "alerting" : <int>, "users" : { (int)<alerting-service-id> : [(int)<user-id>,(int)<user-id>,...] } }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function editAddAlertingRule(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("node_id", $data) && is_numeric($data["node_id"]) && $data["node_id"] > 2 &&
          array_key_exists("rule_id", $data) && is_numeric($data["rule_id"]) && $data["rule_id"] > 0 &&
          array_key_exists("alerting", $data) && is_array($data["alerting"]) &&
          array_key_exists("users", $data) && is_array($data["users"])
        ){
          $rule_id = $data["rule_id"];
          $node_id = $data["node_id"];

          $processing_resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $rule_id, $node_id){
            if(count($data["alerting"]) > 0){
              foreach($data["alerting"] AS $alerting_service_id => $service_config){
                $cleanup_rule = Promise\resolve((new DB_Api)->execute("DELETE FROM alerting_procedure WHERE rule_id = ? AND alerting_service = ? AND rule_node_target = ?", 
                                                  array($rule_id, $alerting_service_id, $node_id)));
                $cleanup_rule->then(function($cleanup_rule_returned) use(&$resolve, $data, $rule_id, $node_id, $alerting_service_id, $service_config){
                  if(is_numeric($alerting_service_id)){
                    //-1 = "do not alert", 0 = "immediately alert", >0 = "alert after x minutes"
                    if(array_key_exists("warn_exceeds", $service_config) || array_key_exists("crit_exceeds", $service_config)){
                      $warn_exceeds = (array_key_exists("warn_exceeds", $service_config) && is_numeric($service_config["warn_exceeds"]) && $service_config["warn_exceeds"] >= 0 ? $service_config["warn_exceeds"] : -1 );
                      $crit_exceeds = (array_key_exists("crit_exceeds", $service_config) && is_numeric($service_config["crit_exceeds"]) && $service_config["crit_exceeds"] >= 0 ? $service_config["crit_exceeds"] : -1 );
      
                      if($warn_exceeds >= 0 || $crit_exceeds >= 0){
                        $insert_procedure = Promise\resolve((new DB_Api)->execute("INSERT INTO alerting_procedure (id, rule_id, rule_node_target, alerting_service, warn_alert_after, crit_alert_after) VALUES (NULL, ?, ?, ?, ?, ?)", 
                                                            array($rule_id, $node_id, $alerting_service_id, $warn_exceeds, $crit_exceeds)));
                                                            
                        $insert_procedure->then(function($insert_procedure_returned) use(&$resolve, $alerting_service_id, $data){
                          $new_procedure_id = $insert_procedure_returned->insertId;
      
                          if(array_key_exists($alerting_service_id, $data["users"])){
                            foreach($data["users"][$alerting_service_id] AS $arrkey => $alerting_user_id){
                              $set_alerting_contact = Promise\resolve((new DB_Api())->execute("INSERT INTO alerting_contact (id, user_id, alerting_procedure_id) VALUES (NULL, ?, ?)", 
                                                              array($alerting_user_id, $new_procedure_id)));
    
                              $set_alerting_contact->otherwise(function(\Exception $e) use(&$resolve){
                                $resolve($this->logging_api->getErrormessage("editAddAlertingRule", "001", $e));
                              });
                            }
                          }
                        })->otherwise(function(\Exeption $e) use(&$resolve){
                          $resolve($this->logging_api->getErrormessage("editAddAlertingRule", "002", $e));
                        });
                      }
                    }
                  }
                })->otherwise(function(\Exception $e) use(&$resolve){
                  $resolve($this->logging_api->getErrormessage("editAddAlertingRule", "003", $e));
                });
              }
              $resolve(array("status" => 0, "message" => "Resolved."));
            }else{
              $resolve(array("status" => 0, "message" => "Resolved."));
            }
          };


          $processing_canceller = function () {
            throw new \Exception('Promise cancelled');
          };

          $processing_promise = Promise\Promise($processing_resolver, $processing_canceller);
          $processing_promise->then(function($processing_promise_returned) use(&$resolve, $data, $rule_id, $node_id){
            if($processing_promise_returned["status"] == 0){
              $new_alerting_rule = Promise\resolve($this->getConfiguredAlertingRules(["rule_id" => $rule_id, "node_id" => $node_id]));
              $new_alerting_rule->then(function($new_alerting_rule_returned) use(&$resolve, $data, $rule_id, $node_id){
                if(array_key_exists("data", $new_alerting_rule_returned)){
                  $resolve(array("status" => 0, "message" => "Successfully processed service alerting for ID {$data["rule_id"]}.", "data" => array("rule_id" => $data["rule_id"], "node_id" => $node_id, "new_data" => $new_alerting_rule_returned["data"])));
                }else{
                  $resolve($new_alerting_rule_returned);
                }
              });
            }else{
              $resolve($processing_promise_returned);
            }
          });
        }else{
          $resolve($this->logging_api->getErrormessage("editAddAlertingRule", "004"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the current applicable alerting rule id of a specific service by stating the service_type_id, node_id and if available service_target.
     * Function made for: Web GUI/App, Api
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     { "service_type_id" : <int>, "node_id" : <int>, "service_target" : <string|null> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function getRuleInformationOfService(array $data): object
    {          
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("service_type_id", $data) && array_key_exists("node_id", $data) && 
          (($data["service_type_id"] == 9 && array_key_exists("service_target", $data)) || $data["service_type_id"] < 9))
        {

          $target_service_statement = "IS NOT NULL";
          if($data["service_type_id"] == 9) $target_service_statement = "= '{$data["service_target"]}'";

          $alerting_rules = Promise\resolve((new DB_Api())->execute("SELECT id, system_target, rule_type, rule_target, rule_default, perc_or_min_value, warn_at_after, crit_at_after, monitor
                                                                    FROM alerting_rules
                                                                    WHERE rule_target {$target_service_statement} AND system_target = ? AND rule_type = ?

                                                                    UNION ALL

                                                                    SELECT id, system_target, rule_type, rule_target, rule_default, perc_or_min_value, warn_at_after, crit_at_after, monitor
                                                                    FROM alerting_rules
                                                                    WHERE rule_target IS NULL
                                                                          AND NOT EXISTS (SELECT 1
                                                                                      FROM alerting_rules
                                                                                      WHERE rule_target {$target_service_statement} AND system_target = ? AND rule_type = ?)
                                                                          AND system_target = 1 AND rule_type = ?", 
                                            array($data["node_id"], $data["service_type_id"], $data["node_id"], $data["service_type_id"], $data["service_type_id"])));

          $alerting_rules->then(function($alerting_rules_returned) use(&$resolve){
            $resolve(array("status" => 0, "message" => "Rule id was found.", "data" => $alerting_rules_returned->resultRows[0]));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("getRuleInformationOfService", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("getRuleInformationOfService", "002"));  
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Calculates the alerting level of given values and returns the result. (1 = OK, 2 = WARN, 3 = CRIT, 4 = UNKN)
     * Function made for: Web GUI/App, Api
     * @param array $data                     { "current_service_level" : <int>, "perc_or_min_value" : <int>, "warn_level_at_after" : <int>, "crit_level_at_after" : <int>, "defined_maximum" : <int>, "current_service_minutes" : <int> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function calculateAlertingLevel(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("current_service_level", $data) && array_key_exists("perc_or_min_value", $data) &&
          array_key_exists("warn_level_at_after", $data) && array_key_exists("crit_level_at_after", $data) &&
          (($data["perc_or_min_value"] == 0 && array_key_exists("defined_maximum", $data) || ($data["perc_or_min_value"] == 1 && array_key_exists("current_service_minutes", $data)))))
        { 
          
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
            $returndata = array("time_or_usage" => 0, "level" => 4);
          }

          $resolve(array("status" => 0, "message" => "Successfully calculated current service state.", "data" => $returndata));
        }else{
          $resolve($this->logging_api->getErrormessage("calculateAlertingLevel", "001"));
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Alerts all found WARN, CRIT and UNKN messages using the alerting services which are configured.
     * Function made for: Web GUI/App, Api
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $data                     Default: [] (empty) - All nodes will be alerted. Optional: { "node_id" : <int|null> }
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function alertAllFoundWARNandCRIT(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $statement_string = "n.id = (
          SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
        )";
        $statement_array = [];
        if(array_key_exists("node_id", $data) && is_numeric($data["nodeid"])){
          $statement_string = "n.id = ?";
          array_push($statement_array, $data["node_id"]);
        }

        $getHostsToAlert = Promise\resolve((new DB_Api())->execute("SELECT n.id, n.hostname, cias.id AS avail_serv_id, cias.service_type, cist.service_desc, avs.state_short AS current_state_short, avs1.state_short AS prev_state_short, cias.service_target, cias.time_or_usage, ar.perc_or_min_value,
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
                                                                  , $statement_array));

        $getHostsToAlert->then(function($getHostsToAlert_returned) use(&$resolve){
          $alerting_services_array = [];
         
          foreach($getHostsToAlert_returned->resultRows AS $arrkey => $this_alert){
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
          
          $send_queued_messages_promises = [];

          foreach($alerting_services_array AS $alert_id => $service_types){
            foreach($service_types AS $service_type_id => $found_alerting_services){
              foreach($found_alerting_services AS $alerting_service_id => $this_service_obj){
                if(method_exists($this_service_obj,'sendQueuedMessages')){
                  array_push($send_queued_messages_promises, Promise\resolve($this_service_obj->sendQueuedMessages()));
                }
              }
            }
          }

          Promise\all($send_queued_messages_promises)->then(function($send_queued_messages_promises_returned) use(&$resolve){
            $error = 0;
            $message_to_mark_as_alerted = [];          

            foreach($send_queued_messages_promises_returned AS $arrkey => $this_queued_messages){
              if($this_queued_messages["status"] == 0){
                foreach($this_queued_messages["data"] AS $this_message_arrkey => $this_sent_message){
                  array_push($message_to_mark_as_alerted, $this_sent_message);
                }
              }else{
                $error = 1;
              }
            }

            $update_alerted_history = Promise\resolve($this->updateAlertedServicesHistory($message_to_mark_as_alerted));
            $update_alerted_history->then(function($update_alerting_history_returned) use(&$resolve, $error){
              if($error == 0){
                $resolve(array("status" => 0, "message" => "Successfully alerted all configured and found WARN, CRIT and UNKN messages.")); 
              }else{
                $resolve($this->logging_api->getErrormessage("alertAllFoundWARNandCRIT", "001"));
              }
            });
          });
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("alertAllFoundWARNandCRIT", "002", $e));
        });
      };


      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Updates the history of alerted services to be able to see when which service was alerted.
     * Function made for: Api/Backend
     * @throws Exception $e                   Throws an exception on db errors.
     * @param array $alerted_messages         Default: [] (empty) - No service has been alerted so not logged. Optional: [<Alerting-Service-Obj>, <Alerting-Service-Obj>, ...]
     * @return object                         Returns a promise object: {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    private function updateAlertedServicesHistory(array $alerted_messages = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($alerted_messages){
        if(count($alerted_messages) == 0) return $resolve(array("status" => 0, "message" => "Successfully alerted all configured and found WARN, CRIT and UNKN messages."));

        foreach($alerted_messages AS $arrkey => $this_alerted_message){
          $avail_serv_id = $this_alerted_message->get_avail_serv_id();
          $service_alerted_using = $this_alerted_message->get_alerting_service_id();
          $state_changed_from = $this_alerted_message->get_prev_state_short();
          $state_changed_to = $this_alerted_message->get_current_state_short();
          $alerted_to = $this_alerted_message->get_user_id();
          $alert_contact = $this_alerted_message->get_contact();

          $alerting_id = Promise\resolve((new DB_Api)->execute("SELECT id FROM `alerting_history` WHERE avail_alerting_serv_id = ? and service_alerted_using = ? LIMIT 1", array($avail_serv_id, $service_alerted_using)));
          $alerting_id->then(function($alerting_id_returned) use(&$resolve, $avail_serv_id, $service_alerted_using, $state_changed_from, $state_changed_to, $alerted_to, $alert_contact){
            $alerting_history_id = 0;

            if(count($alerting_id_returned->resultRows) == 1){
              $alerting_history_id = Promise\resolve($alerting_id_returned->resultFields[0]["id"]);
            }else{
              $alerting_history_id = Promise\resolve((new DB_Api)->execute("INSERT INTO alerting_history (id, avail_alerting_serv_id, service_alerted_using, state_changed_from, state_changed_to, state_alerted_at) VALUES (NULL, ?, ?, ?, ?, NOW())", 
                                                      array($avail_serv_id, $service_alerted_using, $state_changed_from, $state_changed_to)));
            }

            $alerting_history_id->then(function($alerting_history_id_returned) use(&$resolve, $alerted_to, $alert_contact){
              $alerting_history_id = $alerting_history_id_returned;
              if(array_key_exists("insertId", $alerting_history_id_returned)){
                $alerting_history_id = $alerting_history_id_returned->insertId;
              }

              $update_alerting_history = true;
              if($alerting_history_id > 0){
                $update_alerting_history = Promise\resolve((new DB_Api)->execute("INSERT INTO alerting_history_alerted_to (id, alerting_history_id, user_id, contact) VALUES (NULL, ?, ?, ?)", 
                                                          array($alerting_history_id, $alerted_to, $alert_contact))); 
              }else{
                $update_alerting_history = Promise\resolve([]);
              }

              $update_alerting_history->then(function($update_alerting_history_returned) use(&$resolve){
                $resolve(array("status" => 0, "message" => "Successfully alerted all configured and found WARN, CRIT and UNKN messages."));
              })->then(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("updateAlertedServicesHistory", "001", $e));
              });
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("updateAlertedServicesHistory", "002", $e));
            });
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("updateAlertedServicesHistory", "003", $e));
          });
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns all services for which a downtime can be configured.
     *
     * @param array $data
     * @return array
     */
    public function getConfigurableDowntimeServices(array $data = []): object
    {
      return Promise\resolve($this->alerting_downtimes->getConfigurableDowntimeServices($data));
    }

    /**
     * Creates a new downtime for all or specific services of a certain Chia node
     *
     * @param array $data
     * @return array
     */
    public function setUpNewDowntime(array $data): object
    {
      return Promise\resolve($this->alerting_downtimes->setUpNewDowntime($data));
    }

    /**
     * Creates a new downtime for all or specific services of a certain Chia node
     *
     * @param array $data
     * @return array
     */
    public function getSetupDowntimes(array $data = []): object
    {
      return Promise\resolve($this->alerting_downtimes->getSetupDowntimes($data));
    }

    /**
     * Edits the information (Comment, Start date, enddate) of one or more downtime(s).
     *
     * @param array $data
     * @return array
     */
    public function editDowntimes(array $data = []): object
    {
      return Promise\resolve($this->alerting_downtimes->editDowntimes($data));
    }
  }
?>