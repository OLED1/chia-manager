<?php
namespace ChiaMgmt\Chia_Wallet\Data_Objects;

class Wallettransaction{
    /** @var string */
     private $parent_coin_info;
    /** @var int */
     private $amount; //in mojo(s)
    /** @var bool */
     private $confirmed;
    /** @var int */
     private $confirmed_at_height;
    /** @var int */
     private $created_at_time;
    /** @var int */
     private $fee_amount; //in mojo(s)
    /** @var string */
     private $transaction_name;
    /** @var array */
     private $removals;
    /** @var int */
     private $sent;
    /** @var array */
     private $sent_to;
    /** @var string */
     private $spend_bundle;
    /** @var string */
     private $to_address;
    /** @var string */
     private $to_puzzle_hash;
    /** @var int */
     private $trade_id;
    /** @var int */
     private $type;
    

    public function __construct(array $reportedtransactiondata){
        if(is_null($reportedtransactiondata["additions"]) || is_null($reportedtransactiondata["additions"][0]) || is_null($reportedtransactiondata["additions"][0]["parent_coin_info"]) || !is_string($reportedtransactiondata["additions"][0]["parent_coin_info"])){
            throw new \InvalidArgumentException("The reported data for the key 'additions' are not fully set. Expected: 'additions': [ 'parent_coin_info' : <string> ], but got {" . json_encode($reportedtransactiondata["additions"]) . "}");
        }
        if(is_null($reportedtransactiondata["amount"]) || !is_int($reportedtransactiondata["amount"])){
            throw new \InvalidArgumentException("The reported data for the key 'amount' are not fully set. Expected: 'amount': <int>, but got {" . json_encode($reportedtransactiondata["amount"]) . "}");
        }
        if(is_null($reportedtransactiondata["confirmed"]) || !is_bool($reportedtransactiondata["confirmed"])){
            throw new \InvalidArgumentException("The reported data for the key 'confirmed' are not fully set. Expected: 'confirmed': <bool>, but got {" . json_encode($reportedtransactiondata["confirmed"]) . "}");
        }
        if(is_null($reportedtransactiondata["confirmed_at_height"]) || !is_int($reportedtransactiondata["confirmed_at_height"])){
            throw new \InvalidArgumentException("The reported data for the key 'confirmed_at_height' are not fully set. Expected: 'confirmed_at_height': <int>, but got {" . json_encode($reportedtransactiondata["confirmed_at_height"]) . "}");
        }
        if(is_null($reportedtransactiondata["created_at_time"]) || !is_int($reportedtransactiondata["created_at_time"])){
            throw new \InvalidArgumentException("The reported data for the key 'created_at_time' are not fully set. Expected: 'created_at_time': <int>, but got {" . json_encode($reportedtransactiondata["created_at_time"]) . "}");
        }
        if(is_null($reportedtransactiondata["fee_amount"]) || !is_int($reportedtransactiondata["fee_amount"])){
            throw new \InvalidArgumentException("The reported data for the key 'fee_amount' are not fully set. Expected: 'fee_amount': <int>, but got {" . json_encode($reportedtransactiondata["fee_amount"]) . "}");
        }
        if(is_null($reportedtransactiondata["name"]) || !is_string($reportedtransactiondata["name"])){
            throw new \InvalidArgumentException("The reported data for the key 'name' are not fully set. Expected: 'name': <string>, but got {" . json_encode($reportedtransactiondata["name"]) . "}");
        }
        if(is_null($reportedtransactiondata["removals"]) || !is_array($reportedtransactiondata["removals"])){
            throw new \InvalidArgumentException("The reported data for the key 'removals' are not fully set. Expected: 'removals': <array>, but got {" . json_encode($reportedtransactiondata["removals"]) . "}");
        }
        if(is_null($reportedtransactiondata["sent"]) || !is_int($reportedtransactiondata["sent"])){
            throw new \InvalidArgumentException("The reported data for the key 'sent' are not fully set. Expected: 'sent': <int>, but got {" . json_encode($reportedtransactiondata["sent"]) . "}");
        }
        if(is_null($reportedtransactiondata["sent_to"]) || !is_array($reportedtransactiondata["sent_to"])){
            throw new \InvalidArgumentException("The reported data for the key 'sent_to' are not fully set. Expected: 'sent_to': <array>, but got {" . json_encode($reportedtransactiondata["sent_to"]) . "}");
        }
        if(is_null($reportedtransactiondata["to_address"]) || !is_string($reportedtransactiondata["to_address"])){
            throw new \InvalidArgumentException("The reported data for the key 'to_address' are not fully set. Expected: 'to_address': <string>, but got {" . json_encode($reportedtransactiondata["to_address"]) . "}");
        }
        if(is_null($reportedtransactiondata["to_puzzle_hash"]) || !is_string($reportedtransactiondata["to_puzzle_hash"])){
            throw new \InvalidArgumentException("The reported data for the key 'to_puzzle_hash' are not fully set. Expected: 'to_puzzle_hash': <string>, but got {" . json_encode($reportedtransactiondata["to_puzzle_hash"]) . "}");
        }
        if(is_null($reportedtransactiondata["type"]) || !is_int($reportedtransactiondata["type"])){
            throw new \InvalidArgumentException("The reported data for the key 'type' are not fully set. Expected: 'type': <int>, but got {" . json_encode($reportedtransactiondata["type"]) . "}");
        }

        $this->parent_coin_info = $reportedtransactiondata["additions"][0]["parent_coin_info"];
        $this->amount = $reportedtransactiondata["amount"];
        $this->confirmed = $reportedtransactiondata["confirmed"];
        $this->confirmed_at_height = $reportedtransactiondata["confirmed_at_height"];
        $this->created_at_time = $reportedtransactiondata["created_at_time"];
        $this->fee_amount = $reportedtransactiondata["fee_amount"];
        $this->transaction_name = $reportedtransactiondata["name"];
        $this->removals = json_encode($reportedtransactiondata["removals"]);
        $this->sent = $reportedtransactiondata["sent"];
        $this->sent_to = json_encode($reportedtransactiondata["sent_to"]);
        $this->spend_bundle = (is_Null($reportedtransactiondata["spend_bundle"]) ? "" : $reportedtransactiondata["spend_bundle"]);
        $this->to_address = $reportedtransactiondata["to_address"];
        $this->to_puzzle_hash = $reportedtransactiondata["to_puzzle_hash"];
        $this->trade_id = (is_Null($reportedtransactiondata["trade_id"]) ? 0 : $reportedtransactiondata["trade_id"]);
        $this->type = $reportedtransactiondata["type"];
    }

    public function get_parent_coin_info(): string
    {
        return $this->parent_coin_info;
    }

    public function get_amount(): int
    {
        return $this->amount;
    }

    public function get_confirmed(): bool
    {
        return $this->confirmed;
    }

    public function get_confirmed_at_height(): int
    {
        return $this->confirmed_at_height;
    }

    public function get_created_at_time(): int
    {
        return $this->created_at_time;
    }

    public function get_fee_amount(): int
    {
        return $this->fee_amount;
    }

    public function get_transaction_name(): string
    {
        return $this->transaction_name;
    }

    public function get_removals(): string
    {
        return $this->removals;
    }

    public function get_sent(): int
    {
        return $this->sent;
    }

    public function get_sent_to(): string
    {
        return $this->sent_to;
    }

    public function get_spend_bundle(): string
    {
        return $this->spend_bundle;
    }

    public function get_to_address(): string
    {
        return $this->to_address;
    }

    public function get_to_puzzle_hash(): string
    {
        return $this->to_puzzle_hash;
    }

    public function get_trade_id(): int
    {
        return $this->trade_id;
    }

    public function get_type(): int
    {
        return $this->type;
    }
}