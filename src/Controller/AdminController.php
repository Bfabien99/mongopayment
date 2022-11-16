<?php

namespace App\Controller;

use App\Document\Admin;
use DateTime;
use Exception;
use App\Service\JWT;
use App\Document\Agent;
use App\Document\Customer;
use App\Document\Transaction;
use App\Service\Sendmail;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route('/mongopayment/api')]
class AdminController extends AbstractController
{

    private $documentManager;
    private $jwt;
    public function __construct(DocumentManager $documentManager, JWT $jwt)
    {
        $this->documentManager = $documentManager;
        $this->jwt = $jwt;
    }

    public function verifyToken($token)
    {
        $payload = $this->jwt->decode($token, "SECRETE_KEY", ['HS256']);
        $collection = $this->documentManager->getRepository(Admin::class);
        $admin = $collection->findBy(["pseudo" => $payload->admin_pseudo]);
        if ($admin) {
            return true;
        } else {
            return false;
        }
    }

    #[Route('/admin/login', name: 'app_loginAdmin', methods: ['POST'])]
    public function loginAdmin(Request $request, Sendmail $email): JsonResponse
    {
        $token = false;
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["pseudo", "password"];
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
            $collection = $this->documentManager->getDocumentCollection(Admin::class);
            $admin = $collection->findOne(["pseudo" => $parameters["pseudo"], "password" => md5($parameters["password"])]);
            if ($admin) {
                $payload = [
                    "admin_pseudo" => $admin["pseudo"],
                    'iat' => time(),
                    'exp' => time() + (24 * 60 * 60),
                ];

                $token = $this->jwt->encode($payload, "SECRETE_KEY");
                if (!$token) {
                    $errors[] = "Error while generating token.";
                    $message = "Token error.";
                } else {
                    $message = "Login successfully";
                    $success = true;
                    $email->send($admin["email"],'mongopay notif','<h4>You just login!</h4>');
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

    # See all agent
    #[Route('/admin/agents', name: 'app_getAllAgents', methods: ['POST'])]
    public function getAllAgents(Request $request): JsonResponse
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
                    if ($this->verifyToken($parameters["token"])) {
                        $collection = $this->documentManager->getRepository(Agent::class);
                        $agents = $collection->findAll();
                        if ($agents) {
                            foreach ($agents as $agent) {
                                $data[] = $agent->returnArray();
                            }
                        }
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted.";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error";
                }
            }
        }
        return $this->json([
            'message' => 'get all agents',
            'path' => 'src/Controller/CustomerController.php',
            'results' => isset($data) ? $data : []
        ], Response::HTTP_OK, [], [
            ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
                return $object;
            }
        ]);
    }

    #[Route('/admin/agent/register', name: 'app_registerAgent', methods: ['POST'])]
    public function registerAgent(Request $request, Sendmail $email): JsonResponse
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ['name', 'firstname', 'email', 'phone', 'localisation', 'token'];
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

            try {
                if ($this->verifyToken($parameters["token"])) {
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
                    }
                } else {
                    $message = "Invalid Token.";
                    $errors[] = "This token is corrupted.";
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                $message = "Token error";
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
                if (!$isCustomers) {
                    $agent = new Agent($email);
                    $agent->setName($parameters['name']);
                    $agent->setFirstname($parameters['firstname']);
                    $agent->setEmail($parameters['email']);
                    $agent->setPhone($parameters['phone']);
                    $agent->setPassword($parameters['password'] ?? null);
                    $agent->setDeposite_balance(100000);
                    $agent->setWithdraw_balance(100000);
                    $agent->setBalance();
                    $agent->setIdentifiant();
                    $agent->setLocalisation($parameters['localisation']);
                    $agent->setCode($parameters['code'] ?? null);
                    $agent->setCreatedAt(new DateTime('now'));
                    $agent->sendRegisterMail();

                    $this->documentManager->persist($agent);
                    $this->documentManager->flush();

                    $success = true;
                    $message = "Registered successfully";
                } else {
                    $errors[] = "Double identity, phone already exist";
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

    // #[Route('/agent/{id}', name: 'app_getAgent', methods: ['POST'])]
    // public function getAgent(Request $request): JsonResponse
    // {
    // }

    #[Route('/admin/customers', name: 'app_getAllCustomers', methods: ['POST'])]
    public function getAllCustomers(Request $request): JsonResponse
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
                    if ($this->verifyToken($parameters["token"])) {
                        $collection = $this->documentManager->getRepository(Customer::class);

                        $customers = $collection->findAll();
                        if ($customers) {
                            foreach ($customers as $customer) {
                                $data[] = $customer->returnArray();
                            }
                        }
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted.";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error";
                }
            }
        }
        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors'=>$errors,
            'path' => 'src/Controller/CustomerController.php',
            'results' => isset($data) ? $data : []
        ]);
    }

    #[Route('/admin/customer/{id}', name: 'app_getCustomer', methods: ['POST'])]
    public function getCustomer(Request $request, $id): JsonResponse
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
                    if ($this->verifyToken($parameters["token"])) {
                        $collection = $this->documentManager->getRepository(Customer::class);

                        $customer = $collection->findOneBy(['_id'=>$id]);
                        if ($customer) {
                            $data[] = $customer->returnArray();
                            $message = "Customer";
                            $success = true;
                        }
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted.";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error";
                }
            }
        }
        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors'=>$errors,
            'results' => isset($data) ? $data : []
        ]);
    }
}
