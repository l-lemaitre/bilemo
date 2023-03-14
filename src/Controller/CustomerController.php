<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Service\CustomerService;
use Doctrine\Persistence\ManagerRegistry;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'app_api_', defaults: ['_format'=>'json'])]
class CustomerController extends AbstractFOSRestController
{
    #[Route('/customers', name: 'customers_index', methods: ['GET'])]
    public function index(ManagerRegistry $doctrine, SerializerInterface $serializer): JsonResponse
    {
        $customers = $doctrine->getRepository(Customer::class)->findAll();
        $jsonCustomers = $serializer->serialize($customers, 'json', ['groups' => 'getCustomers']);
        return new JsonResponse($jsonCustomers, Response::HTTP_OK, [], true);
    }

    #[Route('/customers', name: 'customers_add', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour créer un client')]
    public function add(ManagerRegistry $doctrine, Request $request, CustomerService $customerService, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $entityManager = $doctrine->getManager();

        $customer = $serializer->deserialize($request->getContent(), Customer::class, 'json');

        $errors = $validator->validate($customer);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $customer = $customerService->addCustomer($entityManager, $customer);

        $jsonCustomer = $serializer->serialize($customer, 'json', ['groups' => 'getCustomers']);
        $location = $urlGenerator->generate('app_api_customers_show', ['id' => $customer->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonCustomer, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/customers/{id}', name: 'customers_show', methods: ['GET'])]
    public function show(Customer $customer, SerializerInterface $serializer): JsonResponse
    {
        $customer = $serializer->serialize($customer, 'json', ['groups' => 'getCustomers']);
        return new JsonResponse($customer, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/customers/{id}', name: 'customers_edit', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un client')]
    public function edit(ManagerRegistry $doctrine, Request $request, Customer $currentCustomer, CustomerService $customerService, SerializerInterface $serializer, ValidatorInterface $validator): Response
    {
        $entityManager = $doctrine->getManager();

        $updatedCustomer = $serializer->deserialize($request->getContent(),
            Customer::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentCustomer]);

        $errors = $validator->validate($updatedCustomer);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $customerService->editCustomer($entityManager, $updatedCustomer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/customers/{id}', name: 'customers_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer un client')]
    public function delete(ManagerRegistry $doctrine, Customer $customer, CustomerService $customerService): jsonResponse
    {
        $entityManager = $doctrine->getManager();

        $customerService->removeCustomer($entityManager, $customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
