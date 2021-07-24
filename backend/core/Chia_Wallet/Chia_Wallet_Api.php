<?php
  namespace ChiaMgmt\Chia_Wallet;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Chia_Wallet_Api{
    private $db_api, $logging_api;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    public function updateWalletData(array $data, array $loginData = NULL){
      if(array_key_exists("wallet", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          foreach($data["wallet"] AS $walletid => $walletdata){
            $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_wallets WHERE walletid = ?", array($walletid));
            $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

            if($count == 0){
              $sql = $this->db_api->execute("INSERT INTO chia_wallets (id, nodeid, walletid, walletaddress, walletheight, syncstatus, wallettype, totalbalance, pendingtotalbalance, spendable) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
              array($nodeid, $walletid, $walletdata["walletaddress"], $walletdata["walletheight"], $walletdata["syncstatus"], $walletdata["wallettype"], $walletdata["totalbalance"], $walletdata["pendingtotalbalance"], $walletdata["spendable"]));
            }else{
              $sql = $this->db_api->execute("UPDATE chia_wallets SET nodeid = ?, walletaddress = ?, walletheight = ?, syncstatus = ?, wallettype = ?, totalbalance = ?, pendingtotalbalance = ?, spendable = ? WHERE walletid = ?",
              array($nodeid, $walletdata["walletaddress"], $walletdata["walletheight"], $walletdata["syncstatus"], $walletdata["wallettype"], $walletdata["totalbalance"], $walletdata["pendingtotalbalance"], $walletdata["spendable"], $walletid));
            }
          }
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }

        return array("status" =>0, "message" => "Successfully updated system information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
      }
    }

    public function getWalletData(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT cw.walletid, cw.nodeid, n.nodeauthhash, cw.walletaddress, cw.walletheight, cw.syncstatus, cw.wallettype, cw.totalbalance, cw.pendingtotalbalance, cw.spendable
                                       FROM chia_wallets cw
                                       JOIN nodes n ON cw.nodeid = n.id"
                                       , array());

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $walletinfo){
          $walletinfo["nodeauthhash"] = $this->decryptAuthhash($walletinfo["nodeauthhash"]);
          $returndata[$walletinfo["walletid"]] = $walletinfo;
        }

        return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    private function encryptAuthhash(string $encryptedauthhash){
      return openssl_encrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    public function decryptAuthhash(string $encryptedauthhash){
      return openssl_decrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }
?>
