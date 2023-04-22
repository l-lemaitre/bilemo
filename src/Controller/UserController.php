<?php

namespace App\Controller;

use App\Dto\EditUser;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\UserService;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'app_api_', defaults: ['_format'=>'json'])]
class UserController extends AbstractFOSRestController
{
    private UserRepository $userRepository;

    private UserService $userService;

    private SerializerInterface $serializer;

    public function __construct(UserRepository $userRepository, UserService $userService, SerializerInterface $serializer)
    {
        $this->userRepository = $userRepository;
        $this->userService = $userService;
        $this->serializer = $serializer;
    }

    /**
     * Cette méthode permet de récupérer l'ensemble des utilisateurs liés au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des utilisateurs",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers", "getBindedUsers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour afficher la liste des utilisateurs"
     * )
     *
     * @OA\Parameter(
     *     name="page",
     *     in="query",
     *     description="La page que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Parameter(
     *     name="limit",
     *     in="query",
     *     description="Le nombre d'éléments que l'on veut récupérer",
     *     @OA\Schema(type="int")
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/users', name: 'users_index', methods: ['GET'])]
    public function index(Request $request, TagAwareCacheInterface $cache): JsonResponse
    {
        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);

        $idCache = 'getUsers-' . $page . '-' . $limit;
        $cacheItem = $cache->getItem($idCache);

        if(!$cacheItem->isHit()) {
            $customer = $this->getUser()->getCustomer();
            if (!$customer) {
                $data = [
                    'status' => 403,
                    'message' => "Vous n'avez pas les droits suffisants pour afficher la liste des utilisateurs."
                ];

                $jsonError = $this->serializer->serialize($data, 'json');
                return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
            }
        }

        $jsonUsers = $cache->get($idCache, function (ItemInterface $item) use ($page, $limit) {
            $item->tag('usersCache');

            $customer = $this->getUser()->getCustomer();
            $users = $this->userRepository->getUsersCustomerWithPagination($customer, $page, $limit);

            $context = SerializationContext::create()->setGroups(['getUsers', 'getBindedUsers']);
            return $this->serializer->serialize($users, 'json', $context);
        });

        return new JsonResponse($jsonUsers, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet de s'inscrire sur l'API Bilemo.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne l'utilisateur créé",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=400,
     *     description="Mauvaise requête de l'utilisateur"
     * )
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         example={
                   "email": "contact@user.com",
                   "password": "password"
               },
     *         @OA\Schema (
     *              type="object",
     *              @OA\Property(property="status", required=true, description="Event Status", type="string"),
     *              @OA\Property(property="comment", required=false, description="Change Status Comment", type="string")
     *         )
     *     )
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $user = $this->serializer->deserialize($request->getContent(), User::class, 'json');

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        if (!preg_match("/^[0-9A-Za-z-_]{8,60}$/", $user->getPassword())) {
            $data = [
                'status' => 400,
                'message' => "Le Mot de passe n'est pas valide. Il doit contenir 8 caractères alphanumériques au minimum et ne comporter aucun accent ni caractères spéciaux hormis \"-\" ou \"_\"."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_BAD_REQUEST, [], true);
        }

        $user = $this->userService->addUser($user);

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $this->serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de lier un utilisateur au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne l'utilisateur lié au client",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers", "getBindedUsers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour lier cet utilisateur"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Utilisateur inexistant"
     * )
     *
     * @OA\Response(
     *     response=400,
     *     description="Utilisateur déjà lié au client | Utilisateur déjà lié à un autre client"
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/users/bind/{id}', name: 'bind_user', methods: ['PUT'])]
    public function bind(UrlGeneratorInterface $urlGenerator, int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $data = [
                'status' => 404,
                'message' => "Cet utilisateur n'existe pas."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $userToBind = $this->userRepository->getUserToBind($id);

        $customer = $this->getUser()->getCustomer();
        if ($customer) {
            $bindedUser = $this->userRepository->getBindedUser($id, $customer);
        }

        if (!$customer) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour lier cet utilisateur."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        } elseif ($bindedUser) {
            $data = [
                'status' => 400,
                'message' => "Cet utilisateur est déjà lié à votre client."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_METHOD_NOT_ALLOWED, [], true);
        } elseif (!$userToBind) {
            $data = [
                'status' => 400,
                'message' => "Cet utilisateur est déjà lié à un autre client."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_METHOD_NOT_ALLOWED, [], true);
        }

        $user = $this->userService->bindUser($user, $customer);

        $context = SerializationContext::create()->setGroups(['getUsers', 'getBindedUsers']);
        $jsonUser = $this->serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_OK, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de délier un utilisateur du client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne l'utilisateur délié du client",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour délier cet utilisateur"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Utilisateur inexistant"
     * )
     *
     * @OA\Response(
     *     response=400,
     *     description="Utilisateur lié à aucun client | Utilisateur lié à un autre client"
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/users/unbind/{id}', name: 'unbind_user', methods: ['PUT'])]
    public function unbind(UrlGeneratorInterface $urlGenerator, int $id): Response
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $data = [
                'status' => 404,
                'message' => "Cet utilisateur n'existe pas."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $userToBind = $this->userRepository->getUserToBind($id);

        $customer = $this->getUser()->getCustomer();
        if ($customer) {
            $bindedUser = $this->userRepository->getBindedUser($id, $customer);
        }

        if (!$customer) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour délier cet utilisateur."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        } elseif ($userToBind) {
            $data = [
                'status' => 400,
                'message' => "Cet utilisateur n'est lié à aucun client."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_METHOD_NOT_ALLOWED, [], true);
        } elseif (!$bindedUser) {
            $data = [
                'status' => 400,
                'message' => "Cet utilisateur n'est pas lié à votre client."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_METHOD_NOT_ALLOWED, [], true);
        }

        $user = $this->userService->unbindUser($bindedUser);

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $this->serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_OK, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet d'afficher un utilisateur lié au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne l'utilisateur",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers", "getBindedUsers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour afficher cet utilisateur"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Utilisateur inexistant"
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/users/{id}', name: 'users_show', methods: ['GET'])]
    public function show(TagAwareCacheInterface $cache, int $id): JsonResponse
    {
        $idCache = 'getUser-' . $id;
        $cacheItem = $cache->getItem($idCache);

        if(!$cacheItem->isHit()) {
            $user = $this->userRepository->find($id);
            if (!$user) {
                $data = [
                    'status' => 404,
                    'message' => "Cet utilisateur n'existe pas."
                ];

                $jsonError = $this->serializer->serialize($data, 'json');
                return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
            }

            $customer = $this->getUser()->getCustomer();
            if ($customer) {
                $userCustomer = $this->userRepository->getBindedUser($id, $customer);
            }

            if (!isset($userCustomer)) {
                $data = [
                    'status' => 403,
                    'message' => "Vous n'avez pas les droits suffisants pour afficher cet utilisateur."
                ];

                $jsonError = $this->serializer->serialize($data, 'json');
                return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
            }
        }

        $jsonUser = $cache->get($idCache, function (ItemInterface $item) use ($id) {
            $item->tag('userCache-' . $id);

            $user = $this->userRepository->find($id);

            $context = SerializationContext::create()->setGroups(['getUsers', 'getBindedUsers']);
            return $this->serializer->serialize($user, 'json', $context);
        });

        return new JsonResponse($jsonUser, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Cette méthode permet de modifier l'utilisateur connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne l'utilisateur modifié",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=User::class, groups={"getUsers", "getBindedUsers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=400,
     *     description="Mauvaise requête de l'utilisateur"
     * )
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         example={
                   "email": "contact@user.com",
                   "password": "password"
               },
     *         @OA\Schema (
     *              type="object",
     *              @OA\Property(property="status", required=true, description="Event Status", type="string"),
     *              @OA\Property(property="comment", required=false, description="Change Status Comment", type="string")
     *         )
     *     )
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/users', name: 'users_edit', methods: ['PUT'])]
    public function edit(Request $request, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $userId = $this->getUser()->getId();
        $user = $this->userRepository->find($userId);

        $editUserDto = $this->serializer->deserialize($request->getContent(), EditUser::class, 'json');

        $errors = $validator->validate($editUserDto);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        if ($editUserDto->getPassword() && !preg_match("/^[0-9A-Za-z-_]{8,60}$/", $editUserDto->getPassword())) {
            $data = [
                'status' => 400,
                'message' => "Le Mot de passe n'est pas valide. Il doit contenir 8 caractères alphanumériques au minimum et ne comporter aucun accent ni caractères spéciaux hormis \"-\" ou \"_\"."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_BAD_REQUEST, [], true);
        }

        $user = $this->userService->editUser($user, $editUserDto);

        $context = SerializationContext::create()->setGroups(['getUsers']);
        $jsonUser = $this->serializer->serialize($user, 'json', $context);
        $location = $urlGenerator->generate('app_api_users_show', ['id' => $user->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonUser, Response::HTTP_OK, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de supprimer un utilisateur lié au client.
     *
     * @OA\Response(
     *     response=204,
     *     description="Pas de contenu retouné"
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour supprimer cet utilisateur"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Utilisateur inexistant"
     * )
     *
     * @OA\Tag(name="Utilisateurs")
     */
    #[Route('/users/{id}', name: 'users_delete', methods: ['DELETE'])]
    public function delete(int $id): jsonResponse
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            $data = [
                'status' => 404,
                'message' => "Cet utilisateur n'existe pas."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $customer = $this->getUser()->getCustomer();
        if ($customer) {
            $userCustomer = $this->userRepository->getUserCustomer($id, $customer);
        }

        if (!isset($userCustomer)) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour supprimer ce compte utilisateur."
            ];

            $jsonError = $this->serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        }

        $this->userService->removeUser($user);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
