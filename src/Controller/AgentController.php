<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Service\JWT;
use App\Document\Agent;
use App\Document\Customer;
use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/mongopayment/api', name: 'app_route')]
class AgentController extends AbstractController
{
    private $documentManager;
    private $jwt;
    public function __construct(DocumentManager $documentManager, JWT $jwt)
    {
        $this->documentManager = $documentManager;
        $this->jwt = $jwt;
    }
    
    # See all agent
    #[Route('/agents', name: 'app_getAllAgents', methods: ['GET'])]
    public function getAllAgents(): JsonResponse
    {
        $collection = $this->documentManager->getRepository(Agent::class);

        $agents = $collection->findAll();
        if($agents){
            foreach ($agents as $agent) {
                $data[] = $agent->returnArray();
            }
        }
        return $this->json([
            'message' => 'get all agents',
            'path' => 'src/Controller/CustomerController.php',
            'results' => isset($data) ? $data:[]
        ], Response::HTTP_OK, [], [
            ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object;
            }
        ]);
    }

    #[Route('/agent/register', name: 'app_registerAgent', methods: ['POST'])]
    public function registerAgent(Request $request): JsonResponse
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ['name', 'firstname', 'email', 'phone', 'localisation','password'];
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

            if (!$errors) {
                if (strlen($parameters['name']) < 2 || strlen($parameters['name']) > 30 || !ctype_alpha($parameters['name'])) {
                    $errors[] = "name must be between 2 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }

                if (strlen($parameters['localisation']) < 2 || strlen($parameters['localisation']) > 30 || !ctype_alpha($parameters['localisation'])) {
                    $errors[] = "localisation must be between 2 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }

                if (strlen($parameters['firstname']) < 2 || strlen($parameters['firstname']) > 30 || !preg_match('/^[a-zA-Z ]+[a-zA-Z-_ ]+$/', $parameters['firstname'])) {
                    $errors[] = "firstname must be between 2 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }

                if (strlen($parameters['email']) < 5 || strlen($parameters['email']) > 50) {
                    $errors[] = "email must be between 5 and 50 charactères";
                    $message = "email error";
                } elseif (!filter_var($parameters['email'], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Invalid email";
                    $message = "Parameter error";
                }

                if (!preg_match('/^[0-9]{10}+$/', $parameters['phone'])) {
                    $errors[] = "Invalid phone. He must contain exactly 10 digits";
                    $message = "Parameter error";
                }

                if (strlen($parameters['password']) < 8 || strlen($parameters['password']) > 30) {
                    $errors[] = "password must be between 8 and 30 charactères and contain only letters";
                    $message = "Parameter error";
                }
            }
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        ## On vérifie s'il n'y a pas d'erreur
        if (!$errors) {
            $collection = $this->documentManager->getDocumentCollection(Agent::class);
            ## On vérifie si le numéro existe déja dans la base de donnée
            $isAgents = $collection->findOne(["phone" => $parameters["phone"]]);
            if (!$isAgents) {
                $collection = $this->documentManager->getDocumentCollection(Customer::class);
            ## On vérifie si le numéro existe déja dans la base de donnée
            $isCustomers = $collection->findOne(["phone" => $parameters["phone"]]);
            if(!$isCustomers){
                $agent = new Agent();
                $agent->setName($parameters['name']);
                $agent->setFirstname($parameters['firstname']);
                $agent->setEmail($parameters['email']);
                $agent->setPhone($parameters['phone']);
                $agent->setPassword($parameters['password']);
                $agent->setDeposite_balance();
                $agent->setWithdraw_balance();
                $agent->setBalance();
                $agent->setIdentifiant();
                $agent->setLocalisation($parameters['localisation']);
                $agent->setCreatedAt(new DateTime('now'));

                $this->documentManager->persist($agent);
                $this->documentManager->flush();

                $success = true;
                $message = "Registered successfully";
            }else {
                $errors[] = "Phone already exist as Customer!";
                $message = "Canceled registration";
            }
                
            } else {
                $errors[] = "Phone already exist!";
                $message = "Canceled registration";
            }
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => isset($agent) ? $agent->returnArray() : []
        ]);
    }

    #[Route('/agent/login', name: 'app_loginAgent', methods: ['POST'])]
    public function loginAgent(Request $request): JsonResponse
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
            $collection = $this->documentManager->getDocumentCollection(Agent::class);
            $agent = $collection->findOne(["phone" => $parameters["phone"], "password" => md5($parameters["password"])]);
            if ($agent) {
                $payload = [
                    "agent_phone" => $agent["phone"],
                    'iat' => time(),
                    'exp' => time() + (24*60*60),
                ];

                $token = $this->jwt->encode($payload, "SECRETE_KEY");
                if (!$token) {
                    $errors[] = "Error while generating token.";
                    $message = "Token error.";
                } else {
                    $message = "Login successfully";
                    $success = true;
                }
            } else {
                $errors[] = "phone or password is not correct.";
                $message = "Credentials error.";
            }
        }
        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'token' => isset($token) ? $token : ""
        ]);
    }

    # Customer Account
    #[Route('/customer/account', name: 'app_agentAccount', methods: ['POST'])]
    public function customerAccount(Request $request)
    {
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
                    $collection = $this->documentManager->getRepository(Customer::class);
                    $customer = $collection->findBy(["phone" => $payload->customer_phone]);
                    if ($customer) {
                        $success = true;
                        $message = "Access granted";
                    } else {
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

    ## Agent transaction begin
    #[Route('/agent/account/withdraws', name: 'app_getAgentWithdraws', methods: ['POST'])]
    public function getAgentAllWithdraw(Request $request)
    {
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
                    $collection = $this->documentManager->getRepository(Agent::class);
                    $agent = $collection->findBy(["phone" => $payload->agent_phone]);
                    if ($agent) {
                        $success = true;
                        $message = "All withdraw";
                        $transactions = $this->documentManager->getRepository(Transaction::class)->findBy(['sender_phone'=>$payload->agent_phone,'transaction_type'=>'withdraw']);
                    } else {
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

        if($transactions){
            foreach ($transactions as $transaction) {
                $data[] = $transaction->returnArray();
            }
        }
        
        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => isset($data) ? $data : []
        ], Response::HTTP_OK, [], [
            ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object;
            },
        ]);
    }
}
