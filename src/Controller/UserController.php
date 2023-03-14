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
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api', name: 'app_api_', defaults: ['_format'=>'json'])]
class UserController extends AbstractFOSRestController
{
    #[Route('/users', name: 'users_index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return $this->json([
            'message' => 'Welcome to your new controller!',
            'path' => 'src/Controller/UserController.php',
        ]);
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(ManagerRegistry $doctrine, Request $request, UserService $userService, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $entityManager = $doctrine->getManager();

        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $user = $userService->addUser($entityManager, $user);

        $jsonUser = $serializer->serialize($user, 'json', ['groups' => 'getCustomers']);
        //$location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, [/*"Location" => $location*/], true);
    }

    #[Route('/bind/user/{id}', name: 'bind_user', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN', message: 'Vous n\'avez pas les droits suffisants pour modifier un produit.')]
    public function edit(ManagerRegistry $doctrine, Request $request, UserService $userService, SerializerInterface $serializer, ValidatorInterface $validator, int $id): Response
    {
        $entityManager = $doctrine->getManager();

        $user = $doctrine->getRepository(User::class)->getUsertoBind($id);

        if (!$user) {
            throw new HttpException(400, "Vous n'avez pas les droits suffisants pour lier cet utilisateur à votre client.");
        }

        $customer = $this->getUser()->getCustomer();

        $userService->bindUser($entityManager, $user, $customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
