<?php

namespace App\Controller;

use App\Entity\Customer;
use FOS\RestBundle\Controller\AbstractFOSRestController;
use JMS\Serializer\SerializationContext;
use JMS\Serializer\SerializerInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api', name: 'app_api_', defaults: ['_format'=>'json'])]
class CustomerController extends AbstractFOSRestController
{
    /**
     * Cette méthode permet d'afficher le client lié à l'utilisateur connecté.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne le client",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Customer::class, groups={"getCustomers"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Client inexistant"
     * )
     *
     * @OA\Tag(name="Clients")
     */
    #[Route('/customers', name: 'customers_index', methods: ['GET'])]
    public function index(TagAwareCacheInterface $cache, SerializerInterface $serializer): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer) {
            $data = [
                'status' => 404,
                'message' => "Ce client n'existe pas."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $idCache = 'getCustomer';
        $jsonCustomer = $cache->get($idCache, function (ItemInterface $item) use ($serializer, $customer) {
            $item->tag('customersCache');

            $context = SerializationContext::create()->setGroups(['getCustomers']);
            return $serializer->serialize($customer, 'json', $context);
        });

        return new JsonResponse($jsonCustomer, Response::HTTP_OK, ['accept' => 'json'], true);
    }
}
