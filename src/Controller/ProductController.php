<?php

namespace App\Controller;

use App\Dto\EditProduct;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductService;
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

#[Route('/api', name: 'app_api_', defaults: ['_format'=>'json'])]
class ProductController extends AbstractFOSRestController
{
    private ProductRepository $productRepository;

    private ProductService $productService;

    public function __construct(ProductRepository $productRepository, ProductService $productService)
    {
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    /**
     * Cette méthode permet de récupérer l'ensemble des produits liés au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne la liste des produits",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class, groups={"getProducts"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour afficher la liste des produits"
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
     * @OA\Tag(name="Produits")
     */
    #[Route('/products', name: 'products_index', methods: ['GET'])]
    public function index(Request $request, SerializerInterface $serializer): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour afficher la liste des produits."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $products = $this->productRepository->getProductsCustomerWithPagination($customer, $page, $limit);

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProducts = $serializer->serialize($products, 'json', $context);
        return new JsonResponse($jsonProducts, Response::HTTP_OK, [], true);
    }

    /**
     * Cette méthode permet d'ajouter un produit lié au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne le produit créé",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class, groups={"getProducts"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour ajouter un produit"
     * )
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         example={
                   "name": "Product XX",
                   "price": "100.00",
                   "description": "Test Product XX."
               },
     *         @OA\Schema (
     *              type="object",
     *              @OA\Property(property="status", required=true, description="Event Status", type="string"),
     *              @OA\Property(property="comment", required=false, description="Change Status Comment", type="string")
     *         )
     *     )
     * )
     *
     * @OA\Tag(name="Produits")
     */
    #[Route('/products', name: 'products_add', methods: ['POST'])]
    public function add(Request $request, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $product = $serializer->deserialize($request->getContent(), Product::class, 'json');

        $customer = $this->getUser()->getCustomer();

        if (!$customer) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour ajouter un produit."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        }

        $errors = $validator->validate($product);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $product = $this->productService->addProduct($product, $customer);

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProducts = $serializer->serialize($product, 'json', $context);
        $location = $urlGenerator->generate('app_api_products_show', ['id' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonProducts, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet d'afficher un produit lié au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne le produit",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class, groups={"getProducts"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour afficher ce produit"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Produit inexistant"
     * )
     *
     * @OA\Tag(name="Produits")
     */
    #[Route('/products/{id}', name: 'products_show', methods: ['GET'])]
    public function show(SerializerInterface $serializer, int $id): JsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $data = [
                'status' => 404,
                'message' => "Ce produit n'existe pas."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $customer = $this->getUser()->getCustomer();
        if ($customer) {
            $productCustomer = $this->productRepository->getProductCustomer($id, $customer);
        }

        if (!isset($productCustomer)) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour afficher ce produit."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        }

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProduct = $serializer->serialize($product, 'json', $context);
        return new JsonResponse($jsonProduct, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Cette méthode permet de modifier un produit lié au client.
     *
     * @OA\Response(
     *     response=200,
     *     description="Retourne le produit modifié",
     *     @OA\JsonContent(
     *        type="array",
     *        @OA\Items(ref=@Model(type=Product::class, groups={"getProducts"}))
     *     )
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour modifier ce produit"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Produit inexistant"
     * )
     *
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *         example={
                   "name": "Product XX update",
                   "price": "101.50",
                   "description": "Test update Product XX."
               },
     *         @OA\Schema (
     *              type="object",
     *              @OA\Property(property="status", required=true, description="Event Status", type="string"),
     *              @OA\Property(property="comment", required=false, description="Change Status Comment", type="string")
     *         )
     *     )
     * )
     *
     * @OA\Tag(name="Produits")
     */
    #[Route('/products/{id}', name: 'products_edit', methods: ['PUT'])]
    public function edit(Request $request, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator, int $id): Response
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $data = [
                'status' => 404,
                'message' => "Ce produit n'existe pas."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $customer = $this->getUser()->getCustomer();
        if ($customer) {
            $productCustomer = $this->productRepository->getProductCustomer($id, $customer);
        }

        if (!isset($productCustomer)) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour modifier ce produit."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        }

        $editProductDto = $serializer->deserialize($request->getContent(), EditProduct::class, 'json');

        $errors = $validator->validate($editProductDto);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $product = $this->productService->editProduct($product, $editProductDto);

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProduct = $serializer->serialize($product, 'json', $context);
        $location = $urlGenerator->generate('app_api_products_show', ['id' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonProduct, Response::HTTP_OK, ["Location" => $location], true);
    }

    /**
     * Cette méthode permet de supprimer un produit lié au client.
     *
     * @OA\Response(
     *     response=204,
     *     description="Pas de contenu retouné"
     * )
     *
     * @OA\Response(
     *     response=403,
     *     description="Droits insuffisants pour supprimer ce produit"
     * )
     *
     * @OA\Response(
     *     response=404,
     *     description="Produit inexistant"
     * )
     *
     * @OA\Tag(name="Produits")
     */
    #[Route('/products/{id}', name: 'products_delete', methods: ['DELETE'])]
    public function delete(SerializerInterface $serializer, int $id): jsonResponse
    {
        $product = $this->productRepository->find($id);

        if (!$product) {
            $data = [
                'status' => 404,
                'message' => "Ce produit n'existe pas."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_NOT_FOUND, [], true);
        }

        $customer = $this->getUser()->getCustomer();
        if ($customer) {
            $productCustomer = $this->productRepository->getProductCustomer($id, $customer);
        }

        if (!isset($productCustomer)) {
            $data = [
                'status' => 403,
                'message' => "Vous n'avez pas les droits suffisants pour supprimer ce produit."
            ];

            $jsonError = $serializer->serialize($data, 'json');
            return new JsonResponse($jsonError, Response::HTTP_FORBIDDEN, [], true);
        }

        $this->productService->removeProduct($product);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}