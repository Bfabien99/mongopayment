<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Service\JWT;
use App\Document\Customer;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/mongopayment/api', name: 'app_route')]
class CustomerController extends AbstractController
{

    private $documentManager;
    private $jwt;
    public function __construct(DocumentManager $documentManager, JWT $jwt)
    {
        $this->documentManager = $documentManager;
        $this->jwt = $jwt;
    }

    ## Home page
    #[Route('/', name: 'app_index', methods:['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/CustomerController.php',
            'link' => ""
        ]);
    }

    # See all customers
    #[Route('/customers', name: 'app_getAllCustomers', methods:['GET'])]
    public function getAllCustomers(): JsonResponse
    {
        $collection = $this->documentManager->getRepository(Customer::class);
        
        $customers = $collection->findAll();
        return $this->json([
            'message' => 'get all customers',
            'path' => 'src/Controller/CustomerController.php',
            'results' => $customers
        ],Response::HTTP_OK, [],[
            ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER=>function($object){
                return $object->getId();
            }
        ]);
    }

    # Get a specific customer
    // #[Route('/customer/{id}', name: 'app_getCustomer', methods:['GET'])]
    // public function getCustomer(): JsonResponse
    // {
    //     return $this->json([
    //         'message' => 'get a specific customer',
    //         'path' => 'src/Controller/CustomerController.php',
    //         'link' => ""
    //     ]);
    // }

    # Register a customer
    #[Route('/customer/register', name: 'app_registerCustomer', methods:['POST'])]
    public function registerCustomer(Request $request): JsonResponse
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ['name', 'firstname', 'email', 'phone', 'password'];
        $parameters = json_decode($request->getContent(), true);

        ## On vérifie si les param(tres du request body existe)
        if ($parameters) {
            ## On vérifie si les paramètres requis y sont présents
            foreach ($require_params as $value) {
                if (!array_key_exists($value, $parameters)) {
                    $errors[] = "$value must be set.";
                    $message = "Required field missing";
                } elseif (empty($parameters[$value])) {
                    $errors[] = "$value must not be empty.";
                    $message = "Empty field found.";
                }
            }

            if(!$errors){
                if(strlen($parameters['name']) < 2 || strlen($parameters['name']) > 30 || !ctype_alpha($parameters['name'])){
                    $errors[] = "name must be between 2 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }

                if(strlen($parameters['firstname']) < 5 || strlen($parameters['firstname']) > 30 || !preg_match('/^[a-zA-Z ]+[a-zA-Z-_ ]+$/', $parameters['firstname'])){
                    $errors[] = "firstname must be between 5 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }

                if(strlen($parameters['email']) < 5 || strlen($parameters['email']) > 50){
                    $errors[] = "email must be between 5 and 50 charactères";
                    $message = "email error";
                }elseif(!filter_var($parameters['email'], FILTER_VALIDATE_EMAIL)){
                    $errors[] = "Invalid email";
                    $message = "Parameter error";
                }

                if(!preg_match('/^[0-9]{10}+$/', $parameters['phone'])) {
                    $errors[] = "Invalid phone. He must contain exactly 10 digits";
                    $message = "Parameter error";
                }

                if(strlen($parameters['password']) < 8 || strlen($parameters['password']) > 30){
                    $errors[] = "password must be between 8 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }
            }
        }
        else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        ## On vérifie s'il n'y a pas d'erreur
        if (!$errors) {
            $collection = $this->documentManager->getDocumentCollection(Customer::class);
            ## On vérifie si le numéro existe déja dans la base de donnée
            $isCustomers = $collection->findOne(["phone" => $parameters["phone"]]);
            if (!$isCustomers) {
                $customer = new Customer();
                $customer->setName($parameters['name']);
                $customer->setFirstname($parameters['firstname']);
                $customer->setEmail($parameters['email']);
                $customer->setPhone($parameters['phone']);
                $customer->setPassword($parameters['password']);
                $customer->setBalance(0);
                $customer->setCreatedAt(new DateTime('now'));

                $this->documentManager->persist($customer);
                $this->documentManager->flush();                

                $success = true;
                $message = "Registered successfully";
            }else{
                $errors[] = "Phone already exist!";
                $message = "Canceled registration";
            }
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => isset($customer) ? $customer->returnArray() : "[]"
        ]);
    }

    # login as customer
    #[Route('/customer/login', name: 'app_loginCustomer', methods:['POST'])]
    public function loginCustomer(Request $request): JsonResponse
    {
        $token = false;
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["phone", "password"];
        $parameters = json_decode($request->getContent(), true);

        if ($parameters) {
            foreach ($require_params as $value) {
                if (!array_key_exists($value, $parameters)) {
                    $errors[] = "$value must be set.";
                    $message = "Required field missing";
                } elseif (empty($parameters[$value])) {
                    $errors[] = "$value must not be empty.";
                    $message = "Empty field found.";
                }
            }
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        if (!$errors) {
            $collection = $this->documentManager->getDocumentCollection(Customer::class);
            $customer = $collection->findOne(["phone" => $parameters["phone"],"password" => md5($parameters["password"])]);
            if($customer){
                $payload = [
                "customer_phone" => $customer["phone"],
                'iat' => time(),
                'exp' => time() + (30 * 60),
            ];

            $token = $this->jwt->encode($payload, "SECRETE_KEY");
            if(!$token){
                $errors[] = "Error while generating token.";
                $message = "Token error.";
            }else{
                $message = "Login successfully";
                $success = true;
            }
            
            }else{
                $errors[] = "phone or password is not correct.";
                $message = "Credentials error.";
            }
            
        }
        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'token' => isset($token) ? $token:""
        ]);
    }

    # Customer Account
    #[Route('/customer/account', name: 'app_customerAccount', methods:['POST'])]
    public function customerAccount(Request $request){
        $success = false;
        $message = "";
        $errors = false;
        $parameters = json_decode($request->getContent(), true);

        if ($parameters) {
            if (!array_key_exists("token", $parameters)) {
                $errors[] = "token must be set.";
                $message = "Required field missing";
            } elseif (empty($parameters["token"])) {
                $errors[] = "token must not be empty.";
                $message = "Empty field found.";
            } else {
                try {
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $collection = $this->documentManager->getDocumentCollection(Customer::class);
                    $customer = $collection->findOne(["phone"=>$payload->customer_phone]);
                    if($customer){
                        $success = true;
                        $message = "Access granted";
                    }else{
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted."; 
                    }
                    
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error";
                }
            }
        } else {
            $errors[] = "Request body can't be empty.";
            $message = "Request body not found";
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => isset($customer) ? $customer : []
        ]);
    }

    ## customer transaction begin
    // #[Route('/customer/account/withdraws', name: 'app_getCustomerWithdraws', methods:['GET'])]
    // #[Route('/customer/account/withdraw', name: 'app_customerWithdraw', methods:['POST'])]
    // #[Route('/customer/account/withdraw/{id}', name: 'app_getCustomerWithdraw', methods:['GET'])]

    // #[Route('/customer/account/deposites', name: 'app_getCustomerDeposites', methods:['GET'])]
    // #[Route('/customer/account/deposite', name: 'app_customerDeposite', methods:['POST'])]
    // #[Route('/customer/account/deposite/{id}', name: 'app_getCustomerDeposite', methods:['GET'])]
}
