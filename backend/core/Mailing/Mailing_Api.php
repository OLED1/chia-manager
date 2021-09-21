<?php
  namespace ChiaMgmt\Mailing;

  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\System\System_Api;
  use PHPMailer\PHPMailer\PHPMailer;
  use PHPMailer\PHPMailer\SMTP;
  use PHPMailer\PHPMailer\Exception;

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
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->mailer = new PHPMailer(true);
      $this->logging_api = new Logging_Api($this);
      $this->system_api = new System_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
    }

  /**
   * Checks if the target mail server is available
   * Function made for: Api/Backend
   * @param  array  $maildata The mailsettings
   * @return array            Returns a status code array
   */
    private function mailServerAvailable(array $maildata, array $loginData = NULL){
      if(isset($maildata["mailserverdomain"]) && isset($maildata["mailserverport"])){
        $connection = @fsockopen($maildata["mailserverdomain"], $maildata["mailserverport"]);

        if (is_resource($connection)){
          return array("status" => 0, "message" => "Mailserver configuration seems valid.");
        }else{
          //return array("status" => 1, "message" => "Cannot connect to smtp server " . $maildata["mailserverdomain"].":".$maildata["mailserverport"] . " .");
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
    public function sendTestMail(array $data, array $loginData = NULL){
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
    public function sendMail(array $recepients, string $subject , string $message){
      $mailsettings = $this->system_api->getSpecificSystemSetting("mailing");

      if($mailsettings["status"] == 0 && Count($mailsettings["data"]) > 0){
        $mailsettings_data = $mailsettings["data"]["mailing"];

        $message .= "<br><br>This mail was automatically generated. Please do not reply to this e-mail.";
        $footer = "Sent by Chia Management (<a href='{$this->ini["app_protocol"]}://{$this->ini["app_domain"]}'>{$this->ini["app_protocol"]}://{$this->ini["app_domain"]}</a>) {$this->ini["versnummer"]}.";
        $preheader = substr($message, 0, 100);

        $template = file_get_contents(__DIR__."/template.html");
        $template =  str_replace("[MAILTEXT]",$message, $template);
        $template =  str_replace("[PREHEADER]",$preheader, $template);
        $template =  str_replace("[MAILINFO]",$footer, $template);

        $mailer = "Chia Management";

        try{
          $mail = $this->mailer;
          //$mail->SMTPDebug = SMTP::DEBUG_SERVER;
          $mail->isSMTP();
          $mail->Host       = $mailsettings_data["mailserverdomain"]["value"];
          $mail->SMTPAuth   = true;
          $mail->Username   = $mailsettings_data["loginname"]["value"];
          $mail->Password   = $mailsettings_data["loginpassword"]["value"];
          if($mailsettings_data["security"]["value"] == "ssl_tls") $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
          else if($mailsettings_data["security"]["value"] == "starttls") $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

          $mail->Port       = $mailsettings_data["mailserverport"]["value"];

          //Recipients
          $mail->setFrom($mailsettings_data["fromuser"]["value"]."@".$mailsettings_data["domain"]["value"], $mailer);
          $mail->addReplyTo($mailsettings_data["fromuser"]["value"]."@".$mailsettings_data["domain"]["value"], 'Information');
          foreach ($recepients as $recepient) {
            $mail->addBCC($recepient);
          }

          // Content
          $mail->isHTML(true);
          $mail->Subject = $subject;
          $mail->Body    = $template;

          $mail->send();

          return array("status" => 0, "message" => "Message has been sent.");
        }catch (Exception $e) {
          //return array("status" => 1, "message" => "Message could not be sent. Mailer Error: {$mail->ErrorInfo}.");
          return $this->logging_api->getErrormessage("001","Message could not be sent. Mailer Error: {$mail->ErrorInfo}.");
        }
      }
      return $mailsettings;
    }
  }
?>
