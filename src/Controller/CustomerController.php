<?php

namespace App\Controller;

use DateTime;
use Exception;
use App\Service\JWT;
use App\Document\Agent;
use App\Document\Customer;
use App\Document\Transaction;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/mongopayment/api')]
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
    #[Route('/', name: 'app_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/CustomerController.php',
            'link' => ""
        ]);
    }

    # See all customers
    // #[Route('/customers', name: 'app_getAllCustomers', methods: ['GET'])]
    // public function getAllCustomers(): JsonResponse
    // {
    //     $collection = $this->documentManager->getRepository(Customer::class);

    //     $customers = $collection->findAll();
    //     return $this->json([
    //         'message' => 'get all customers',
    //         'path' => 'src/Controller/CustomerController.php',
    //         'results' => $customers
    //     ], Response::HTTP_OK, [], [
    //         ObjectNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object) {
    //             return $object->getId();
    //         }
    //     ]);
    // }

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
    #[Route('/customer/register', name: 'app_registerCustomer', methods: ['POST'])]
    public function registerCustomer(Request $request): JsonResponse
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ['name', 'firstname', 'email', 'phone', 'password', 'code'];
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

                if (strlen($parameters['firstname']) < 2 || strlen($parameters['firstname']) > 30 || !preg_match('/^[a-zA-Z\é\è\ê\ ]+[a-zA-Z\é\è\ê\-\_\ ]+$/', $parameters['firstname'])) {
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

                if (!preg_match('/^[0-9]{6}+$/', $parameters['code'])) {
                    $errors[] = "Invalid code. He must contain exactly 6 digits";
                    $message = "Parameter error";
                }

                if (strlen($parameters['password']) < 8 || strlen($parameters['password']) > 30) {
                    $errors[] = "password must be between 8 and 30 charactères";
                    $message = "Parameter error";
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
            $isCustomers = $collection->findOne(["phone" => $parameters["phone"]]);
            if (!$isCustomers) {
                $collection = $this->documentManager->getDocumentCollection(Agent::class);
                ## On vérifie si le numéro existe déja dans la base de donnée
                $isAgents = $collection->findOne(["phone" => $parameters["phone"]]);
                if (!$isAgents) {
                    $customer = new Customer();
                    $customer->setName(strip_tags(trim($parameters['name'])));
                    $customer->setFirstname(strip_tags(trim($parameters['firstname'])));
                    $customer->setEmail(strip_tags(trim($parameters['email'])));
                    $customer->setPhone(strip_tags(trim($parameters['phone'])));
                    $customer->setPassword(strip_tags(trim($parameters['password'])));
                    $customer->setCode(strip_tags(trim($parameters['code'])));
                    $customer->setBalance(0);
                    $customer->setCreatedAt(new DateTime('now'));

                    $this->documentManager->persist($customer);
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
            'results' => isset($customer) ? $customer->returnArray() : []
        ]);
    }

    # login as customer
    #[Route('/customer/login', name: 'app_loginCustomer', methods: ['POST'])]
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
            $customer = $collection->findOne(["phone" => $parameters["phone"], "password" => md5($parameters["password"])]);
            if ($customer) {
                $payload = [
                    "customer_phone" => $customer["phone"],
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
    #[Route('/customer/account', name: 'app_customerAccount', methods: ['POST'])]
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
                    $customer = $collection->findOneBy(["phone" => $payload->customer_phone]);
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

    ## customer transaction begin
    #[Route('/customer/account/withdraws', name: 'app_getCustomerWithdraws', methods: ['POST'])]
    public function getCustomerAllWithdraw(Request $request)
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
                        $message = "All withdraw";
                        $transactions = $this->documentManager->getRepository(Transaction::class)->findBy(['sender_phone' => $payload->customer_phone, 'transaction_type' => 'withdraw']);
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

        if (!$errors) {
            if ($transactions) {
                foreach ($transactions as $transaction) {
                    $data[] = $transaction->returnArray();
                }
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

    #[Route('/customer/account/withdraw', name: 'app_customerWithdraw', methods: ['POST'])]
    public function setWithdraw(Request $request)
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["token", "amount", "receiver_phone", "code"];
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

            if (!$errors) {
                try {
                    $customer_collection = $this->documentManager->getRepository(Customer::class);
                    $agent_collection = $this->documentManager->getRepository(Agent::class);
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $sender = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);
                    $receiver = $agent_collection->findOneBy(["phone" => $parameters["receiver_phone"]]);

                    if (($sender && $receiver) && ($sender !== $receiver)) {
                        if ($sender->getCode() == $parameters['code']) {
                            $transaction = new Transaction();
                            if ($sender->getBalance() >= $parameters["amount"]) {
                                if ($receiver->getWithdraw_balance() >= $parameters["amount"]) {
                                    $sender->setBalance(-$parameters["amount"]);

                                    $receiver->setWithdraw_balance(-$parameters["amount"]);
                                    $receiver->setDeposite_balance($parameters["amount"]);
                                    $receiver->setBalance();

                                    $transaction->setSender_phone($sender->getPhone());
                                    $transaction->setReceiver_phone($receiver->getPhone());
                                    $transaction->setTransaction_Type("withdraw");
                                    $transaction->setTransaction_Amount($parameters["amount"]);
                                    $transaction->setTransaction_code("MPC-W-");
                                    $transaction->setTransaction_date(new DateTime("now"));

                                    $this->documentManager->persist($sender);
                                    $this->documentManager->persist($transaction);
                                    $this->documentManager->persist($receiver);

                                    $this->documentManager->flush();

                                    $success = true;
                                    $message = "Withdraw successfuly!";
                                }else{
                                    $message = "Insuffisant Agent balance!";
                                $errors[] = "Sorry, we have not suffisant money!";
                                }
                            } else {
                                $message = "Insuffisant balance!";
                                $errors[] = "Your balance is low than the amount, please refill your account!";
                            }
                        } else {
                            $message = "code error";
                            $errors[] = "You put a wrong code";
                        }
                    } elseif ($receiver == $sender) {
                        $message = "Receiver phone error.";
                        $errors[] = "You can't deposite on your own account";
                    } elseif (!$sender) {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted";
                    } else {
                        $message = "Receiver phone error";
                        $errors[] = "Receiver should be an agent or you put a wrong number";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error.";
                }
            }
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => [
                "customer" => isset($sender) ? $sender->returnArray() : [],
                "transaction" => isset($transaction) ? $transaction->returnArray() : []
            ]
        ]);
    }

    #[Route('/customer/account/withdraw/{code}', name: 'app_getCustomerWithdraw', methods: ['POST'])]
    public function getCustomerWithdraw(Request $request, $code)
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["token"];
        $parameters = json_decode($request->getContent(), true);

        if ($parameters) {
            if (!array_key_exists("token", $parameters)) {
                $errors[] = "token must be set.";
                $message = "Required field missing";
            } elseif (empty($parameters["token"])) {
                $errors[] = "token must not be empty.";
                $message = "Empty field found.";
            }

            if (!$errors) {
                try {
                    $customer_collection = $this->documentManager->getRepository(Customer::class);
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $customer = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);

                    if ($customer) {
                        $transaction_collection = $this->documentManager->getRepository(Transaction::class);
                        $transactions = $transaction_collection->findBy(["sender_phone" => $payload->customer_phone, "transaction_code" => $code]);
                        $success = true;
                        $message = "withdraw";
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error.";
                }
            }

            if (!$errors) {
                if ($transactions) {
                    foreach ($transactions as $transaction) {
                        $data[] = $transaction->returnArray();
                    }
                }
            }

            return $this->json([
                'success' => $success,
                'message' => $message,
                'errors' => $errors,
                'results' => [
                    "transaction" => isset($data) ? $data : []
                ]
            ]);
        }
    }

    #[Route('/customer/account/deposites', name: 'app_getCustomerDeposites', methods: ['POST'])]
    public function getCustomerAllDeposite(Request $request)
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
                        $message = "All deposite";
                        $send_deposite = $this->documentManager->getRepository(Transaction::class)->findBy(['sender_phone' => $payload->customer_phone, 'transaction_type' => 'deposite']);
                        $receive_deposite = $this->documentManager->getRepository(Transaction::class)->findBy(['receiver_phone' => $payload->customer_phone, 'transaction_type' => 'deposite']);
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

        if (!$errors) {
            $data = [];
            if ($send_deposite) {
                foreach ($send_deposite as $transaction) {
                    $data["send"][] = $transaction->returnArray();
                }
            }
            if ($receive_deposite) {
                foreach ($receive_deposite as $transaction) {
                    $data["receive"][] = $transaction->returnArray();
                }
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

    #[Route('/customer/account/deposite', name: 'app_customerDeposite', methods: ['POST'])]
    public function setDeposite(Request $request)
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["token", "amount", "receiver_phone", "code"];
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

            if (!$errors) {
                try {
                    $customer_collection = $this->documentManager->getRepository(Customer::class);
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $sender = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);
                    $receiver = $customer_collection->findOneBy(["phone" => $parameters["receiver_phone"]]);

                    if (($sender && $receiver) && ($sender !== $receiver)) {
                        if ($sender->getCode() == md5($parameters['code'])) {
                            $transaction = new Transaction();
                            if ($sender->getBalance() >= $parameters["amount"]) {
                                $sender->setBalance(-$parameters["amount"]);
                                $receiver->setBalance($parameters["amount"]);

                                $transaction->setSender_phone($sender->getPhone());
                                $transaction->setReceiver_phone($receiver->getPhone());
                                $transaction->setTransaction_Type("deposite");
                                $transaction->setTransaction_Amount($parameters["amount"]);
                                $transaction->setTransaction_code("MPC-D-");
                                $transaction->setTransaction_date(new DateTime("now"));

                                $this->documentManager->persist($sender);
                                $this->documentManager->persist($transaction);
                                $this->documentManager->persist($receiver);

                                $this->documentManager->flush();

                                $success = true;
                                $message = "Deposite successfuly!";
                            } else {
                                $message = "Insuffisant balance!";
                                $errors[] = "Your balance is low than the amount, please refill your account!";
                            }
                        } else {
                            $message = "code error";
                            $errors[] = "You put a wrong code";
                        }
                    } elseif ($receiver == $sender) {
                        $message = "Receiver phone error.";
                        $errors[] = "You can't deposite on your own account";
                    } elseif (!$sender) {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted";
                    } else {
                        $message = "Receiver phone error";
                        $errors[] = "Receiver should register first or you put a wrong number";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error.";
                }
            }
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => [
                "customer" => isset($sender) ? $sender->returnArray() : [],
                "transaction" => isset($transaction) ? $transaction->returnArray() : []
            ]
        ]);
    }

    // #[Route('/customer/account/deposite/{code}', name: 'app_getCustomerDeposite', methods: ['POST'])]
    // public function getCustomerDeposite(Request $request, $code)
    // {
    //     $success = false;
    //     $message = "";
    //     $errors = false;
    //     $require_params = ["token"];
    //     $parameters = json_decode($request->getContent(), true);

    //     if ($parameters) {
    //         if (!array_key_exists("token", $parameters)) {
    //             $errors[] = "token must be set.";
    //             $message = "Required field missing";
    //         } elseif (empty($parameters["token"])) {
    //             $errors[] = "token must not be empty.";
    //             $message = "Empty field found.";
    //         }

    //         if (!$errors) {
    //             try {
    //                 $customer_collection = $this->documentManager->getRepository(Customer::class);
    //                 $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
    //                 $customer = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);

    //                 if ($customer) {
    //                     $transaction_collection = $this->documentManager->getRepository(Transaction::class);
    //                     $transactions = $transaction_collection->findBy(["transaction_code" => $code]);
    //                 } else {
    //                     $message = "Invalid Token.";
    //                     $errors[] = "This token is corrupted";
    //                 }
    //             } catch (Exception $e) {
    //                 $errors[] = $e->getMessage();
    //                 $message = "Token error.";
    //             }
    //         }

    //         if (!$errors) {
    //             if ($transactions) {
    //                 foreach ($transactions as $transaction) {
    //                     $data[] = $transaction->returnArray();
    //                 }
    //             }
    //         }
    //         return $this->json([
    //             'success' => $success,
    //             'message' => $message,
    //             'errors' => $errors,
    //             'results' => [
    //                 "transaction" => isset($data) ? $data : []
    //             ]
    //         ]);
    //     }
    // }

    #[Route('/customer/account/transactions', name: 'app_getCustomerTransactions', methods: ['POST'])]
    public function getCustomerTransactions(Request $request)
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
                        $message = "All transaction";
                        $send_transaction = $this->documentManager->getRepository(Transaction::class)->findBy(['sender_phone' => $payload->customer_phone]);
                        $receive_transaction = $this->documentManager->getRepository(Transaction::class)->findBy(['receiver_phone' => $payload->customer_phone]);
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

        if (!$errors) {
            $data = [];
            if ($send_transaction) {
                foreach ($send_transaction as $transaction) {
                    $data["send"][] = $transaction->returnArray();
                }
            }
            if ($receive_transaction) {
                foreach ($receive_transaction as $transaction) {
                    $data["receive"][] = $transaction->returnArray();
                }
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

    #[Route('/customer/account/transaction/{code}', name: 'app_getCustomerTransaction', methods: ['POST'])]
    public function getCustomerTransaction(Request $request, $code)
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["token"];
        $parameters = json_decode($request->getContent(), true);

        if ($parameters) {
            if (!array_key_exists("token", $parameters)) {
                $errors[] = "token must be set.";
                $message = "Required field missing";
            } elseif (empty($parameters["token"])) {
                $errors[] = "token must not be empty.";
                $message = "Empty field found.";
            }

            if (!$errors) {
                try {
                    $customer_collection = $this->documentManager->getRepository(Customer::class);
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $customer = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);

                    if ($customer) {
                        $transaction_collection = $this->documentManager->getRepository(Transaction::class);
                        $transactions = $transaction_collection->findBy(["transaction_code" => $code]);
                        $message = "Transaction";
                        $success = true;
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error.";
                }
            }

            if (!$errors) {
                if ($transactions) {
                    foreach ($transactions as $transaction) {
                        $data[] = $transaction->returnArray();
                    }
                }
            }
            return $this->json([
                'success' => $success,
                'message' => $message,
                'errors' => $errors,
                'results' => [
                    "transaction" => isset($data) ? $data : []
                ]
            ]);
        }
    }

    #[Route('/customer/account/updatepass', name: 'app_customerPassword', methods: ['POST'])]
    public function updatePassword(Request $request)
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["token", "password"];
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

            if (!$errors) {
                try {
                    $customer_collection = $this->documentManager->getRepository(Customer::class);
                    $agent_collection = $this->documentManager->getRepository(Agent::class);
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $sender = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);

                    if ($sender) {
                        if (strlen($parameters['password']) < 8 || strlen($parameters['password']) > 30) {
                            $errors[] = "password must be between 8 and 30 charactères";
                            $message = "Parameter error";
                        }else{
                            $sender->setPassword($parameters["password"]);
                        $this->documentManager->persist($sender);

                        $this->documentManager->flush();
                        }
                        
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error.";
                }
            }
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => []
        ]);
    }

    #[Route('/customer/account/updatecode', name: 'app_customerCode', methods: ['POST'])]
    public function updateCode(Request $request)
    {
        $success = false;
        $message = "";
        $errors = false;
        $require_params = ["token", "code"];
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

            if (!$errors) {
                try {
                    $customer_collection = $this->documentManager->getRepository(Customer::class);
                    $agent_collection = $this->documentManager->getRepository(Agent::class);
                    $payload = $this->jwt->decode($parameters["token"], "SECRETE_KEY", ['HS256']);
                    $sender = $customer_collection->findOneBy(["phone" => $payload->customer_phone]);

                    if ($sender) {
                        if (!preg_match('/^[0-9]{6}+$/', $parameters['code'])) {
                            $errors[] = "Invalid code. He must contain exactly 6 digits";
                            $message = "Parameter error";
                        }else{
                            $sender->setCode($parameters["code"]);
                        $this->documentManager->persist($sender);

                        $this->documentManager->flush();
                        }
                        
                    } else {
                        $message = "Invalid Token.";
                        $errors[] = "This token is corrupted";
                    }
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                    $message = "Token error.";
                }
            }
        } else {
            $errors[] = "Request body can't be empty";
            $message = "Request body not found.";
        }

        return $this->json([
            'success' => $success,
            'message' => $message,
            'errors' => $errors,
            'results' => []
        ]);
    }
}
