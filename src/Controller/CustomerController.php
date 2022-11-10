<?php

namespace App\Controller;

use App\Service\JWT;
use App\Document\Customer;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
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

        return $this->json([
            'message' => 'get all customers',
            'path' => 'src/Controller/CustomerController.php',
            'link' => ""
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
    public function registerCustomer(): JsonResponse
    {
        return $this->json([
            'message' => 'register as Customer',
            'path' => 'src/Controller/CustomerController.php',
        ]);
    }
}
