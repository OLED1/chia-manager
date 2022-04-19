<?php
namespace ChiaMgmt\Alerting\Data_Objects;

class Alertingdata{
    /** @var int */
    private $avail_serv_id;
    /** @var int */
    private $alerting_service_id;
    /** @var string */
    private $hostname;
    /** @var string */
    private $service_desc;
    /** @var string */
    private $current_state_short; //Contains OK, WARN, CRIT or UNKN
    /** @var string */
    private $prev_state_short; //Contains OK, WARN, CRIT or UNKN
    /** @var string */
    private $service_target;
    /** @var int */
    private $perc_or_min_value;
    /** @var int */
    private $time_or_usage; //In minutes or percent, depends on perc_or_min_value
    /** @var int */
    private $state_since; //Since when the service has a particular state
    /** @var string */
    private $username;
    /** @var int */
    private $user_id;
    /** @var string */
    private $contact; //Can be an e-mail address, phone number, whatever. Depends on service.

    public function __construct(array $reportedAlertingData){
        if(!array_key_exists("alerting_service_id", $reportedAlertingData) || !is_numeric($reportedAlertingData["alerting_service_id"]) || is_null($reportedAlertingData["alerting_service_id"])){
            throw new \InvalidArgumentException("The reported data for the key 'alerting_service_id' are not fully set. Expected: 'alerting_service_id': <int>, but got {" . json_encode($reportedAlertingData["avail_serv_id"]) . "}");
        }
        if(!array_key_exists("avail_serv_id", $reportedAlertingData) || !is_numeric($reportedAlertingData["avail_serv_id"]) || is_null($reportedAlertingData["avail_serv_id"])){
            throw new \InvalidArgumentException("The reported data for the key 'avail_serv_id' are not fully set. Expected: 'avail_serv_id': <int>, but got {" . json_encode($reportedAlertingData["avail_serv_id"]) . "}");
        }
        if(!array_key_exists("hostname", $reportedAlertingData) || !is_string($reportedAlertingData["hostname"]) || is_null($reportedAlertingData["hostname"])){
            throw new \InvalidArgumentException("The reported data for the key 'hostname' are not fully set. Expected: 'hostname': <string>, but got {" . json_encode($reportedAlertingData["hostname"]) . "}");
        }
        if(!array_key_exists("service_desc", $reportedAlertingData) || !is_string($reportedAlertingData["service_desc"]) || is_null($reportedAlertingData["service_desc"])){
            throw new \InvalidArgumentException("The reported data for the key 'service_desc' are not fully set. Expected: 'service_desc': <string>, but got {" . json_encode($reportedAlertingData["service_desc"]) . "}");
        }
        if(!array_key_exists("current_state_short", $reportedAlertingData) || !is_string($reportedAlertingData["current_state_short"]) || is_null($reportedAlertingData["current_state_short"])){
            throw new \InvalidArgumentException("The reported data for the key 'current_state_short' are not fully set. Expected: 'current_state_short': <string>, but got {" . json_encode($reportedAlertingData["current_state_short"]) . "}");
        }
        if(!array_key_exists("service_target", $reportedAlertingData) || !is_string($reportedAlertingData["service_target"]) || is_null($reportedAlertingData["service_target"])){
            throw new \InvalidArgumentException("The reported data for the key 'service_target' are not fully set. Expected: 'service_target': <string>, but got {" . json_encode($reportedAlertingData["service_target"]) . "}");
        }
        if(!array_key_exists("time_or_usage", $reportedAlertingData) || !is_numeric($reportedAlertingData["time_or_usage"]) || is_null($reportedAlertingData["time_or_usage"])){
            throw new \InvalidArgumentException("The reported data for the key 'time_or_usage' are not fully set. Expected: 'time_or_usage': <int>, but got {" . json_encode($reportedAlertingData["time_or_usage"]) . "}");
        }
        if(!array_key_exists("perc_or_min_value", $reportedAlertingData) || !is_numeric($reportedAlertingData["perc_or_min_value"]) || is_null($reportedAlertingData["perc_or_min_value"])){
            throw new \InvalidArgumentException("The reported data for the key 'perc_or_min_value' are not fully set. Expected: 'perc_or_min_value': <int>, but got {" . json_encode($reportedAlertingData["perc_or_min_value"]) . "}");
        }
        if(!array_key_exists("state_since", $reportedAlertingData) || !is_numeric($reportedAlertingData["state_since"]) || is_null($reportedAlertingData["state_since"])){
            throw new \InvalidArgumentException("The reported data for the key 'state_since' are not fully set. Expected: 'state_since': <int>, but got {" . json_encode($reportedAlertingData["state_since"]) . "}");
        }

        if(!array_key_exists("alert_to_user", $reportedAlertingData) || !is_numeric($reportedAlertingData["alert_to_user"]) || is_null($reportedAlertingData["alert_to_user"])){
            throw new \InvalidArgumentException("The reported data for the key 'alert_to_user' are not fully set. Expected: 'alert_to_user': <string>, but got {" . json_encode($reportedAlertingData["alert_to_user"]) . "}");
        }

        if(!array_key_exists("username", $reportedAlertingData) || !is_string($reportedAlertingData["username"]) || is_null($reportedAlertingData["username"])){
            throw new \InvalidArgumentException("The reported data for the key 'username' are not fully set. Expected: 'username': <string>, but got {" . json_encode($reportedAlertingData["username"]) . "}");
        }
        if(!array_key_exists("contact", $reportedAlertingData) || !is_string($reportedAlertingData["contact"]) || is_null($reportedAlertingData["contact"])){
            throw new \InvalidArgumentException("The reported data for the key 'contact' are not fully set. Expected: 'contact': <string>, but got {" . json_encode($reportedAlertingData["contact"]) . "}");
        }
        $this->alerting_service_id = $reportedAlertingData["alerting_service_id"];
        $this->avail_serv_id = $reportedAlertingData["avail_serv_id"];
        $this->hostname = $reportedAlertingData["hostname"];
        $this->service_desc = $reportedAlertingData["service_desc"];
        $this->current_state_short = $reportedAlertingData["current_state_short"];
        $this->prev_state_short = (is_null($reportedAlertingData["prev_state_short"]) ? "UNKN" : $reportedAlertingData["prev_state_short"]);
        $this->service_target = $reportedAlertingData["service_target"];
        $this->perc_or_min_value = $reportedAlertingData["perc_or_min_value"];
        $this->time_or_usage = $reportedAlertingData["time_or_usage"];
        $this->state_since = $reportedAlertingData["state_since"];
        $this->username = $reportedAlertingData["username"];
        $this->user_id = $reportedAlertingData["alert_to_user"];
        $this->contact = $reportedAlertingData["contact"];
    }

    public function get_alerting_service_id(): int
    {
        return $this->alerting_service_id;
    }

    public function get_avail_serv_id(): int
    {
        return $this->avail_serv_id;
    }

    public function get_hostname(): string
    {
        return $this->hostname;
    }

    public function get_service_desc(): string
    {
        return $this->service_desc;
    }

    public function get_current_state_short(): string
    {
        return $this->current_state_short;
    }

    public function get_prev_state_short(): string
    {
        return $this->prev_state_short;
    }

    public function get_service_target(): string
    {
        return $this->service_target;
    }

    public function get_perc_or_min_value(): int
    {
        return $this->perc_or_min_value;
    }

    public function get_time_or_usage(): int
    {
        return $this->time_or_usage;
    }

    public function get_state_since(): int
    {
        return $this->state_since;
    }

    public function get_user_id(): int
    {
        return $this->user_id;
    }

    public function get_username(): string
    {
        return $this->username;
    }

    public function get_contact(): string
    {
        return $this->contact;
    }
}