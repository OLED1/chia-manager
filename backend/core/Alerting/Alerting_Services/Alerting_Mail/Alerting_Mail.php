<?php
    namespace ChiaMgmt\Alerting\Alerting_Services\Alerting_Mail;
    use React\Promise;
    use ChiaMgmt\Mailing\Mailing_Api;
    use ChiaMgmt\Alerting\Data_Objects\Alertingdata;

    class Alerting_Mail{
        /**
         * Holds an instance to the System's Mailing Api
         *
         * @var Mailing_Api
         */
        private $mailing_api;
        /**
         * Messages Queue
         */
        private $messages = [];

        public function __construct(){
            $this->mailing_api = new Mailing_Api();
        }

        public function queueNewMessage(array $data){
            try{
                $data["contact"] = $data["email"];
                $this_alerting_obj = new Alertingdata($data);
                array_push($this->messages,$this_alerting_obj);
            }catch(\Exception $e){
                //Log to file
                print_r($e);
            }
        }

        public function sendQueuedMessages(){
            $resolver = function (callable $resolve, callable $reject, callable $notify){
                $error_mailing_promises = [];

                foreach($this->messages AS $arrkey => $message){
                    $subject = "Status changed for service " . $message->get_service_desc() . " on host " . $message->get_hostname();
                    $generated_message = $this->generateMail($arrkey); 
                    array_push($error_mailing_promises, Promise\resolve((new Mailing_Api())->sendMail([$message->get_contact()], $subject , $generated_message)));
                }

                return Promise\all($error_mailing_promises)->then(function($error_mailing_promises_returned) use(&$resolve){
                    $error = 0;
                    $alerted_messages = [];
                    foreach($error_mailing_promises_returned AS $arrkey => $this_mail_returned){
                        if($this_mail_returned["status"] == 0){
                            array_push($alerted_messages, $this->messages[$arrkey]);
                        }else{
                            $error = 1;
                        }
                    }

                    if($error == 0){
                        $resolve(array("status" => 0, "message" => "Messages were successfully sent using service 'Mail'.", "data" => $alerted_messages));
                    }
                    $resolve(array("status" => 1, "message" => "Some messages could not be sent. Trying again later.'.", "data" => $alerted_messages));
                });
            };

            $canceller = function () {
                throw new \Exception('Promise cancelled');
            };
        
            return new Promise\Promise($resolver, $canceller);
        }

        private function generateMail(int $array_key){
            $message_to_send = $this->messages[$array_key];
            $template = file_get_contents(__DIR__."/template.html");
            $template =  str_replace("[USERNAME]", ucfirst($message_to_send->get_username()), $template);
            $template =  str_replace("[SERVICENAME]", $message_to_send->get_service_desc(), $template);
            $template =  str_replace("[HOSTNAME]",$message_to_send->get_hostname(), $template);
            $template =  str_replace("[SERVICETARGET]",(is_null($message_to_send->get_service_target()) ? "Not specified" : $message_to_send->get_service_target()), $template);
            $prevstate = $message_to_send->get_prev_state_short();
            $template =  str_replace("[PREVSTATE]","<b class='state-" . strtolower($prevstate) . "'>{$prevstate}</b>", $template);
            $newstate = $message_to_send->get_current_state_short();
            $template =  str_replace("[NEWSTATE]","<b class='state-" . strtolower($newstate) . "'>{$newstate}</b>", $template);
            $template =  str_replace("[SERVICEDOWNDESC]",$this->getServiceDownDesc($message_to_send->get_current_state_short(), $message_to_send->get_perc_or_min_value(), $message_to_send->get_time_or_usage()), $template);
            $template =  str_replace("[STATESINCE]",$message_to_send->get_state_since(), $template);
            $template =  str_replace("[URGENTACTION]",$this->getActionStatus($newstate), $template);

            echo "Mail to send:\n";
            print_r($template);

            return $template;
        }

        private function getServiceDownDesc(string $newstate, int $perc_or_min_value, int $time_or_usage){
            if($newstate == "OK"){
                return "<span class='state-ok'>Service went back to OK.</span>";
            }else{
                if($perc_or_min_value == 0){
                    return "Service exceeded the max percent usage for {$newstate}. Current total usage is {$time_or_usage}%.";
                }else{
                    return "Service exceeded the max down time for {$newstate}. Current down time is {$time_or_usage} minute(s).";
                }
            }
        }

        private function getActionStatus(string $newstate){
            if($newstate == "OK"){
                return "<p><b class='state-ok'>No further actions needed.</b></p>";
            }else if($newstate == "WARN"){
                return "<p><b class='state-warn'>There might be urgent actions needed now or soon.</b></p>";
            }else{
                return "<p><b class='state-crit'>Urgent actions needed!</b></p>";
            }
        }
    }
?>