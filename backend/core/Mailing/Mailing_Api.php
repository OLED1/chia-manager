<?php
  namespace ChiaMgmt\Mailing;
  use React\Promise;
  use React\ChildProcess;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\SMTP;
  use PHPMailer\PHPMailer\Exception;

  require __DIR__ . '/../../../vendor/autoload.php';

  /**
   * The Mailing_Api class enables the sending of mails using PHPMailer.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Mailing_Api{
    /**
     * Holds an instance to the PHPMailer Class.
     * @var PHPMailer
     */
    private $mailer;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the System Class.
     * @var System_Api
     */
    private $system_api;
    /**
     * Holds the system's config json array.
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
      $this->logging_api = new Logging_Api($this, $server);
      $this->system_api = new System_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

  /**
   * Checks if the target mail server is available
   * Function made for: Api/Backend
   * @param  array  $maildata The mailsettings
   * @return array            Returns a status code array
   */
    private function mailServerAvailable(array $maildata, array $loginData = NULL): array
    {
      if(isset($maildata["mailserverdomain"]) && isset($maildata["mailserverport"])){
        $connection = @fsockopen($maildata["mailserverdomain"], $maildata["mailserverport"]);

        if (is_resource($connection)){
          return array("status" => 0, "message" => "Mailserver configuration seems valid.");
        }else{
          return $this->logging->getErrormessage("001","Not able to connect to the configured smtp server " . $maildata["mailserverdomain"].":".$maildata["mailserverport"] . " .");
        }
      }
    }

    /**
     * Sends a testmail to a specific user's email
     * Function made for: Api/Backend
     * @param  string $recepient The recepients email
     * @return array             Returns a status code array
     */
    public function sendTestMail(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("receipients", $data) && is_array($data["receipients"])){
          $subject = "Mail Settings Testmail";
          $message = "If you got this message your mail settings are working correctly.<br>Congrats!<br><strong>Note: Please do not reply to this e-mail.</strong>";
          $recpients = $data["receipients"];

          $resolve(Promise\resolve($this->sendMail($recpients, $subject, $message)));
        }else{
          $resolve($this->logging_api->getErrormessage("sendTestMail", "001"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
      
      if(array_key_exists("receipients", $data) && is_array($data["receipients"])){
        $subject = "Mail Settings Testmail";
        $message = "If you got this message your mail settings are working correctly.<br>Congrats!<br><strong>Note: Please do not reply to this e-mail.</strong>";
        $recpients = $data["receipients"];
        return $this->sendMail($recpients, $subject, $message);
      }else{
        return $this->logging_api->getErrormessage("001");
      }
    }

    /**
     * Sends emails to particular users
     * Function made for: Api/Backend
     * @throws Exception $e Throws an exception on PHPMailer errors.
     * @param  array  $recepients The recepients which should get this mail
     * @param  string $subject    The mail's subject
     * @param  string $message    The mail's message as html formatted
     * @return array              Returns a status code array
     */
    public function sendMail(array $recepients, string $subject , string $message): object
    {     
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($recepients, $subject, $message){
        $cmd = "php " . __DIR__ . "/Mail_Background.php '" . implode(",", $recepients) . "' '{$subject}' '{$message}'";
       
        $process = new ChildProcess\Process($cmd);
        $process->start();
  
        $process->stdout->on('data', function ($chunk) use($resolve){
          $returned_message = (!is_null($chunk) ? json_decode($chunk, true) : array());

          if(!is_null($returned_message) && array_key_exists("status", $returned_message) && $returned_message["status"] == 0){
            $resolve(array("status" => 0, "message" => "Message has been sent on " . date("Y-m-d H:i:s") . "."));
            return;
          }else if(!is_null($returned_message) && array_key_exists("data", $returned_message) && $returned_message["status"] != 0){
            $resolve($this->logging_api->getErrormessage("sendMail", "001","Message could not be sent. Mailer Error: " . $returned_message["data"] . "."));
          }else{
            $resolve($this->logging_api->getErrormessage("sendMail", "002","Message could not be sent. UNKNOWN Error."));
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
