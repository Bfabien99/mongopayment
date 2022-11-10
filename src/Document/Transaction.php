<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\ODM\MongoDB\Types\Type;

#[MongoDB\Document(db:"mongopayment",collection:"transaction")]
class Transaction{
    #[MongoDB\Id]
    protected $_id;

    #[MongoDB\Field(type: Type::INT)]
    protected $transaction_amount;

    #[MongoDB\Field(type: Type::STRING)]
    protected $transaction_type;

    #[MongoDB\Field(type: Type::STRING)]
    protected $sender_phone;

    #[MongoDB\Field(type: Type::STRING)]
    protected $receiver_phone;

    #[MongoDB\Field(type: Type::STRING)]
    protected $transaction_code;

    #[MongoDB\Field(type: Type::DATE_IMMUTABLE)]
    protected $transaction_date;

    /**
     * Get the value of transaction_amount
     */ 
    public function getTransaction_amount():?float
    {
        return $this->transaction_amount;
    }

    /**
     * Set the value of transaction_amount
     *
     * @return  self
     */ 
    public function setTransaction_amount($transaction_amount)
    {
        $this->transaction_amount = $transaction_amount;

        return $this;
    }

    /**
     * Get the value of transaction_type
     */ 
    public function getTransaction_type():?string
    {
        return $this->transaction_type;
    }

    /**
     * Set the value of transaction_type
     *
     * @return  self
     */ 
    public function setTransaction_type($transaction_type)
    {
        $this->transaction_type = $transaction_type;

        return $this;
    }

    /**
     * Get the value of sender_phone
     */ 
    public function getSender_phone():?string
    {
        return $this->sender_phone;
    }

    /**
     * Set the value of sender_phone
     *
     * @return  self
     */ 
    public function setSender_phone($sender_phone)
    {
        $this->sender_phone = $sender_phone;

        return $this;
    }

    /**
     * Get the value of receiver_phone
     */ 
    public function getReceiver_phone():?string
    {
        return $this->receiver_phone;
    }

    /**
     * Set the value of receiver_phone
     *
     * @return  self
     */ 
    public function setReceiver_phone($receiver_phone)
    {
        $this->receiver_phone = $receiver_phone;

        return $this;
    }

    /**
     * Get the value of transaction_code
     */ 
    public function getTransaction_code():?string
    {
        return $this->transaction_code;
    }

    /**
     * Set the value of transaction_code
     *
     * @return  self
     */ 
    public function setTransaction_code()
    {
        $this->transaction_code = uniqid("MP-".date('dmYHis'));

        return $this;
    }

    /**
     * Get the value of transaction_date
     */ 
    public function getTransaction_date():?\DateTimeInterface
    {
        return $this->transaction_date;
    }

    /**
     * Set the value of transaction_date
     *
     * @return  self
     */ 
    public function setTransaction_date(\DateTimeInterface $transaction_date)
    {
        $this->transaction_date = $transaction_date;

        return $this;
    }

     /**
     * return all data toArray
     *
     * @return  self
     */
    public function returnArray(){
        $arrayCustomer = [
            "from" => $this->getSender_phone(),
            "to" => $this->getReceiver_phone(),
            "type" => $this->getTransaction_type(),
            "amount" => $this->getTransaction_amount(),
            "transaction_code" => $this->getTransaction_code(),
            "transaction_date" => $this->getTransaction_date(),
        ];

        return $arrayCustomer;
    }
}