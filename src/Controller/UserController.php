<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use Doctrine\Persistence\ManagerRegistry;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
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
    public function index(SerializerInterface $serializer): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();
        $users = $this->userRepository->getUsersCustomer($customer);

        $jsonUsers = $serializer->serialize($users, 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(ManagerRegistry $doctrine, Request $request, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $entityManager = $doctrine->getManager();

        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $user = $this->userService->addUser($entityManager, $user);

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomers']);
        $location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/bind/users/{id}', name: 'bind_user', methods: ['PUT'])]
    ##[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour lier cet utilisateur.')]
    public function bind(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();

        $user = $this->userRepository->getUsertoBind($id);

        $customer = $this->getUser()->getCustomer();

        $bindedUser = $this->userRepository->getBindedUser($id, $customer);

        if ($bindedUser) {
            throw new HttpException(405, "Cet utilisateur est déjà lié à votre client.");
        } elseif (!$user) {
            throw new HttpException(405, "Cet utilisateur est déjà lié à un autre client.");
        }

        $this->userService->bindingUser($entityManager, $user, $customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
    #[Route('/unbind/users/{id}', name: 'unbind_user', methods: ['PUT'])]
    ##[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour lier cet utilisateur.')]
    public function unbind(ManagerRegistry $doctrine, int $id): Response
    {
        $entityManager = $doctrine->getManager();

        $user = $this->userRepository->getUsertoBind($id);

        $customer = $this->getUser()->getCustomer();

        $bindedUser = $this->userRepository->getBindedUser($id, $customer);

        if ($user) {
            throw new HttpException(405, "Cet utilisateur n'est lié à aucun client.");
        } elseif (!$bindedUser) {
            throw new HttpException(405, "Cet utilisateur n'est pas lié à votre client.");
        }

        $this->userService->bindingUser($entityManager, $bindedUser);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/users/{id}', name: 'users_show', methods: ['GET'])]
    public function show(SerializerInterface $serializer, int $id): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();
        $user = $this->userRepository->getUserCustomer($id, $customer);

        if (!$user) {
            throw new HttpException(403, "Vous n'avez pas les droits suffisants pour afficher cet utilisateur.");
        }

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getUsers']);
        return new JsonResponse($jsonUser, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/users', name: 'users_edit', methods: ['PUT'])]
    ##[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier cet utilisateur.')]
    public function edit(ManagerRegistry $doctrine, Request $request, SerializerInterface $serializer, ValidatorInterface $validator): Response
    {
        $entityManager = $doctrine->getManager();

        $currentUser = $this->getUser();

        $customer = $this->getUser()->getCustomer();

        $currentPassword = $this->getUser()->getPassword();

        $updatedUser = $serializer->deserialize($request->getContent(),
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $currentUser]);

        $errors = $validator->validate($updatedUser);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $this->userService->editUser($entityManager, $updatedUser, $customer, $currentPassword);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/users', name: 'users_delete', methods: ['DELETE'])]
    ##[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour supprimer cet utilisateur.')]
    public function delete(ManagerRegistry $doctrine): jsonResponse
    {
        $entityManager = $doctrine->getManager();

        $user = $this->getUser();

        $this->userService->removeUser($entityManager, $user);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
