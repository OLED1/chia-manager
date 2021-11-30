<?php
namespace ChiaMgmt\Chia_Wallet\Data_Objects;

class Walletdata{
    /** @var string */
    private $address;
    /** @var int */
    private $height;
    /** @var int */
    private $syncstatus;
    /** @var int */
    private $type;
    /** @var int */
    private $confirmed_wallet_balance; //in mojo(s)
    /** @var int */
    private $unconfirmed_wallet_balance; //in mojo(s)
    /** @var int */
    private $spendable_balance; //in mojo(s)

    public function __construct(array $reportedwalletdata){
        if(is_null($reportedwalletdata["address"]) || !is_string($reportedwalletdata["address"])){
            throw new \InvalidArgumentException("The reported data for the key 'address' are not fully set. Expected: 'address': <string>, but got {" . json_encode($reportedwalletdata["address"]) . "}");
        }
        if(is_null($reportedwalletdata["height"]) || !is_int($reportedwalletdata["height"])){
            throw new \InvalidArgumentException("The reported data for the key 'height' are not fully set. Expected: 'height': <int>, but got {" . json_encode($reportedwalletdata["height"]) . "}");
        }
        if(
            is_null($reportedwalletdata["sync_status"]) || !is_array($reportedwalletdata["sync_status"]) ||
            is_null($reportedwalletdata["sync_status"]["syncing"]) || !is_bool($reportedwalletdata["sync_status"]["syncing"]) ||
            is_null($reportedwalletdata["sync_status"]["synced"]) || !is_bool($reportedwalletdata["sync_status"]["synced"])
        ){
            throw new \InvalidArgumentException("The reported data for the key 'sync_status' are not fully set. Expected: 'sync_status': { 'syncing' : <bool>, 'synced' : <bool> }, but got {" . json_encode($reportedwalletdata["sync_status"]) . "}");
        }
        if(is_null($reportedwalletdata["type"]) || !is_int($reportedwalletdata["type"])){
            throw new \InvalidArgumentException("The reported data for the key 'type' are not fully set. Expected: 'type': <int>, but got {" . jsojson_encoden_decode($reportedwalletdata["type"]) . "}");
        }
        if(
            is_null($reportedwalletdata["balance"]) || !is_array($reportedwalletdata["balance"]) ||
            is_null($reportedwalletdata["balance"]["confirmed_wallet_balance"]) || !is_int($reportedwalletdata["balance"]["confirmed_wallet_balance"]) ||
            is_null($reportedwalletdata["balance"]["unconfirmed_wallet_balance"]) || !is_int($reportedwalletdata["balance"]["unconfirmed_wallet_balance"]) ||
            is_null($reportedwalletdata["balance"]["spendable_balance"]) || !is_int($reportedwalletdata["balance"]["spendable_balance"])
        ){
            throw new \InvalidArgumentException("The reported data for the key 'balance' are not fully set. Expected: 'balance': { 'confirmed_wallet_balance' : <int>, 'unconfirmed_wallet_balance' : <int>, 'spendable_balance' : <int> }, but got {" . json_encode($reportedfwalletdata["balance"]) . "}");
        }

        $this->address = $reportedwalletdata["address"];
        $this->height = $reportedwalletdata["height"];
        $this->syncstatus = ($reportedwalletdata["sync_status"]["syncing"] ? 0 : (!$reportedwalletdata["sync_status"]["synced"] ? 1 : 2)); //0 = Syncing, 1 = Not synced, 2 = Synced
        $this->type = $reportedwalletdata["type"];
        $this->confirmed_wallet_balance = $reportedwalletdata["balance"]["confirmed_wallet_balance"];
        $this->unconfirmed_wallet_balance = $reportedwalletdata["balance"]["unconfirmed_wallet_balance"];
        $this->spendable_balance = $reportedwalletdata["balance"]["spendable_balance"];  
    }

    public function get_address(): string
    {
        return $this->address;
    }

    public function get_height(): int
    {
        return $this->height;
    }

    public function get_syncstatus(): int
    {
        return $this->syncstatus;
    }

    public function get_type(): int
    {
        return $this->type;
    }

    public function get_confirmed_wallet_balance(): int
    {
        return $this->confirmed_wallet_balance;
    }

    public function get_unconfirmed_wallet_balance(): int
    {
        return $this->unconfirmed_wallet_balance;
    }

    public function get_spendable_balance(): int
    {
        return $this->spendable_balance;
    }
}