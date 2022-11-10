<?php

namespace App\Controller;

use App\Service\JWT;
use App\Document\Customer;
use DateTime;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

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

    #[Route('/', name: 'app_index', methods:['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/CustomerController.php',
            'link' => ""
        ]);
    }

    #[Route('/customers', name: 'app_getAllCustomers', methods:['GET'])]
    public function getAllCustomers(): JsonResponse
    {
        $collection = $this->documentManager->getDocumentCollection(Customer::class);
        
        $customers = $collection->find();
        return $this->json([
            'message' => 'get all customers',
            'path' => 'src/Controller/CustomerController.php',
            'results' => $customers->toArray()
        ]);
    }

    #[Route('/customer/{id}', name: 'app_getCustomer', methods:['GET'])]
    public function getCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'get a specific customer',
            'path' => 'src/Controller/CustomerController.php',
            'link' => ""
        ]);
    }

    #[Route('/customer/login', name: 'app_loginCustomer', methods:['POST'])]
    public function loginCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'login as customer',
            'path' => 'src/Controller/CustomerController.php',
            'link' => ""
        ]);
    }

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
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        ## On vérifie s'il n'y a pas d'erreur
        if (!$errors) {
            $collection = $this->documentManager->getDocumentCollection(Customer::class);
            ## On vérifie si le numéro existe déja dans la base de donnée
            $isCustomers = $collection->find(["phone" => $parameters["phone"]]);
            if (!$isCustomers) {
                $customer = new Customer();
                $customer->setName($parameters['name']);
                $customer->setFirstname($parameters['firstname']);
                $customer->setEmail($parameters['email']);
                $customer->setPhone($parameters['phone']);
                $customer->setPassword($parameters['password']);
                $customer->setBalance(0);

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
}
