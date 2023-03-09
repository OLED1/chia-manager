<?php
    use React\Promise;
    use React\Promise\Deferred;
    use ChiaMgmt\System\System_Api;
    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    require __DIR__ . '/../../../vendor/autoload.php';
    
    $ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
    $recepients = explode(",", $argv[1]);
    $subject = $argv[2];
    $message = $argv[3];

    $mail_settings = Promise\resolve((new System_Api())->getSpecificSystemSetting("mailing"));
    $mail_settings->then(function($mail_settings_returned) use(&$resolve, $ini, $recepients, $subject, $message){
        if($mail_settings_returned["status"] == 0 && Count($mail_settings_returned["data"]) > 0){
            $mailsettings_data = $mail_settings_returned["data"]["mailing"];

            $message .= "<br><br>This mail was automatically generated. Please do not reply to this e-mail.";
            $footer = "Sent by Chia Management (<a href='{$ini["app_protocol"]}://{$ini["app_domain"]}'>{$ini["app_protocol"]}://{$ini["app_domain"]}</a>) {$ini["versnummer"]}.";
            $preheader = substr($message, 0, 100);
    
            $template = file_get_contents(__DIR__."/template.html");
            $template =  str_replace("[MAILTEXT]",$message, $template);
            $template =  str_replace("[PREHEADER]",$preheader, $template);
            $template =  str_replace("[MAILINFO]",$footer, $template);
    
            $mailer = "Chia Management";

            //TESTING!!!!
            echo json_encode(array("status" => 0, "message" => "Message has been sent."));
            return;
            //TESTING!!!!

            try{
                $mail = new PHPMailer(true);
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
    
                echo json_encode(array("status" => 0, "message" => "Message has been sent."));
                return;
            }catch (\Exception $e) {
                echo json_encode(array("status" => 1, "message" => "An error occured.", "data" => $e->getMessage()));
                return;
            }
        }
        echo json_encode($mail_settings_returned);
    });
?>