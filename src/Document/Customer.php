<?php
namespace App\Document;

use App\Service\Sendmail;
use Doctrine\ODM\MongoDB\Types\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;


#[MongoDB\Document(db:"mongopayment",collection:"customer")]
class Customer
{
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
    protected $code;

    #[MongoDB\Field(type: Type::DATE_IMMUTABLE)]
    protected $createdAt;

    protected $unc_code;

    protected $unc_pass;

    private $semail;
    public function __construct(Sendmail $semail = null){
        $this->semail = $semail;
    }
    
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
        $this->name = ucwords($name);

        return $this;
    }

    public function getFirstname(): ?string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname): self
    {
        $this->firstname = ucwords($firstname);

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
        $this->unc_pass = $password;
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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->unc_code = $code;
        $this->code = md5($code);

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

    public function returnArray(){
        $arrayCustomer = [
            "id" => $this->getId(),
            "name" => $this->getName(),
            "firstname" => $this->getFirstname(),
            "email" => $this->getEmail(),
            "phone" => $this->getPhone(),
            "account_balance" => $this->getBalance(),
            "createdAt" => $this->getCreatedAt(),
        ];

        return $arrayCustomer;
    }

    public function sendRegisterMail(){
        $html = "<h1>Your mongopay account has just been opened</h1>
        <p>Here are your connection settings</p>
        <p>phone number:</p><strong>".$this->getPhone()."</strong>
        <p>password :</p><strong>".$this->unc_pass."</strong>
        <p>Validation code:</p><strong>".$this->unc_code."</strong>";
        $this->semail->send($this->getEmail(),"Mangopay account opening",$html);
    }
}