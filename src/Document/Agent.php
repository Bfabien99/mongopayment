<?php
namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Doctrine\ODM\MongoDB\Types\Type;


#[MongoDB\Document(db:"mongopayment",collection:"agent")]
class Agent{
    #[MongoDB\Id]
    protected $_id;

    #[MongoDB\Field(type: Type::STRING)]
    protected $name;

    #[MongoDB\Field(type: Type::STRING)]
    protected $firstname;

    #[MongoDB\Field(type: Type::STRING)]
    protected $email;

    #[MongoDB\Field(type: Type::STRING)]
    protected $phone;

    #[MongoDB\Field(type: Type::STRING)]
    protected $password;

    #[MongoDB\Field(type: Type::INT)]
    protected $balance;

    #[MongoDB\Field(type: Type::STRING)]
    protected $identifiant;

    #[MongoDB\Field(type: Type::DATE_IMMUTABLE)]
    protected $createdAt;

    public function getId(): mixed
    {
        return $this->_id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = $firstname;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = md5($password);

        return $this;
    }

    public function getBalance(): ?int
    {
        return $this->balance;
    }

    public function setBalance(int $balance): self
    {
        $this->balance += $balance;

        return $this;
    }


    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get the value of identifiant
     */ 
    public function getIdentifiant()
    {
        return $this->identifiant;
    }

    /**
     * Set the value of identifiant
     *
     * @return  self
     */ 
    public function setIdentifiant($identifiant)
    {
        $this->identifiant = uniqid("MPAG".date('dmY'));

        return $this;
    }

    public function returnArray(){
        $arrayCustomer = [
            "id" => $this->getId(),
            "name" => $this->getName(),
            "firstname" => $this->getFirstname(),
            "email" => $this->getEmail(),
            "phone" => $this->getPhone(),
            "account_balance" => $this->getBalance(),
            "identifiant" => $this->getIdentifiant(),
            "createdAt" => $this->getCreatedAt(),
        ];

        return $arrayCustomer;
    }
}