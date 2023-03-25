<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'app_api_', defaults: ['_format'=>'json'])]
class UserController extends AbstractFOSRestController
{
    private UserRepository $userRepository;

    private UserService $userService;

    public function __construct(UserRepository $userRepository, UserService $userService)
    {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
    }

    #[Route('/users', name: 'users_index', methods: ['GET'])]
    public function index(Request $request, SerializerInterface $serializer): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $users = $this->userRepository->getUsersCustomerWithPagination($customer, $page, $limit);

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUsers = $serializer->serialize($users, 'json', $context);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $user = $this->userService->addUser($user);

        $context = SerializationContext::create()->setGroups(['getCustomers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/bind/users/{id}', name: 'bind_user', methods: ['PUT'])]
    public function bind(int $id): Response
    {
        $user = $this->userRepository->getUsertoBind($id);

        $customer = $this->getUser()->getCustomer();

        $bindedUser = $this->userRepository->getBindedUser($id, $customer);

        if ($bindedUser) {
            throw new HttpException(405, "Cet utilisateur est déjà lié à votre client.");
        } elseif (!$user) {
            throw new HttpException(405, "Cet utilisateur est déjà lié à un autre client.");
        }

        $this->userService->bindUser($user, $customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/unbind/users/{id}', name: 'unbind_user', methods: ['PUT'])]
    public function unbind(int $id): Response
    {
        $user = $this->userRepository->getUsertoBind($id);

        $customer = $this->getUser()->getCustomer();

        $bindedUser = $this->userRepository->getBindedUser($id, $customer);

        if ($user) {
            throw new HttpException(405, "Cet utilisateur n'est lié à aucun client.");
        } elseif (!$bindedUser) {
            throw new HttpException(405, "Cet utilisateur n'est pas lié à votre client.");
        }

        $this->userService->unbindUser($bindedUser);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/users/{id}', name: 'users_show', methods: ['GET'])]
    public function show(SerializerInterface $serializer, int $id): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();
        $user = $this->userRepository->getBindedUser($id, $customer);

        if (!$user) {
            throw new HttpException(403, "Vous n'avez pas les droits suffisants pour afficher cet utilisateur.");
        }

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $serializer->serialize($user, 'json', $context);
        return new JsonResponse($jsonUser, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/users', name: 'users_edit', methods: ['PUT'])]
    public function edit(Request $request, SerializerInterface $serializer, ValidatorInterface $validator): Response
    {
        $currentUser = $this->getUser();

        $updatedUser = $serializer->deserialize($request->getContent(), User::class, 'json');
        $currentUser->setEmail($updatedUser->getEmail());

        $errors = $validator->validate($currentUser);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $this->userService->editUser($currentUser, $updatedUser);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/users', name: 'users_delete', methods: ['DELETE'])]
    public function delete(): jsonResponse
    {
        $user = $this->getUser();

        $this->userService->removeUser($user);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
