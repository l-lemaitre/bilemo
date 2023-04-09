<?php

namespace App\Controller;

use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Service\ProductService;
use Doctrine\Persistence\ManagerRegistry;
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
class ProductController extends AbstractFOSRestController
{
    private ProductRepository $productRepository;

    private ProductService $productService;

    public function __construct(ProductRepository $productRepository, ProductService $productService)
    {
        $this->productRepository = $productRepository;
        $this->productService = $productService;
    }

    #[Route('/products', name: 'products_index', methods: ['GET'])]
    public function index(Request $request, SerializerInterface $serializer): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();

        if (!$customer) {
            throw new HttpException(403, "Vous n'avez pas les droits suffisants pour afficher la liste des produits.");
        }

        $page = $request->get('page', 1);
        $limit = $request->get('limit', 5);
        $products = $this->productRepository->getProductsCustomerWithPagination($customer, $page, $limit);

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProducts = $serializer->serialize($products, 'json', $context);
        return new JsonResponse($jsonProducts, Response::HTTP_OK, [], true);
    }

    #[Route('/products', name: 'products_add', methods: ['POST'])]
    public function add(ManagerRegistry $doctrine, Request $request, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ValidatorInterface $validator): Response
    {
        $entityManager = $doctrine->getManager();

        $product = $serializer->deserialize($request->getContent(), Product::class, 'json');

        $customer = $this->getUser()->getCustomer();

        $errors = $validator->validate($product);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $product = $this->productService->addProduct($entityManager, $product, $customer);

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProducts = $serializer->serialize($product, 'json', $context);
        $location = $urlGenerator->generate('app_api_products_show', ['id' => $product->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonProducts, Response::HTTP_CREATED, ["Location" => $location], true);
    }

    #[Route('/products/{id}', name: 'products_show', methods: ['GET'])]
    public function show(SerializerInterface $serializer, int $id): JsonResponse
    {
        $customer = $this->getUser()->getCustomer();
        $product = $this->productRepository->getProductCustomer($id, $customer);

        if (!$product) {
            throw new HttpException(403, "Vous n'avez pas les droits suffisants pour afficher ce produit.");
        }

        $context = SerializationContext::create()->setGroups(['getProducts']);
        $jsonProduct = $serializer->serialize($product, 'json', $context);
        return new JsonResponse($jsonProduct, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    #[Route('/products/{id}', name: 'products_edit', methods: ['PUT'])]
    public function edit(ManagerRegistry $doctrine, Request $request, Product $currentProduct, SerializerInterface $serializer, ValidatorInterface $validator, int $id): Response
    {
        $entityManager = $doctrine->getManager();

        $newProduct = $serializer->deserialize($request->getContent(), Product::class, 'json');
        $currentProduct->setName($newProduct->getName());
        $currentProduct->setPrice($newProduct->getPrice());
        $currentProduct->setDescription($newProduct->getDescription());

        $customer = $this->getUser()->getCustomer();
        $product = $this->productRepository->getProductCustomer($id, $customer);

        if (!$product) {
            throw new HttpException(403, "Vous n'avez pas les droits suffisants pour modifier ce produit.");
        }

        $errors = $validator->validate($currentProduct);

        if ($errors->count() > 0) {
            $view = $this->view($errors, 400);
            return $this->handleView($view);
        }

        $this->productService->editProduct($entityManager, $currentProduct, $customer);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/products/{id}', name: 'products_delete', methods: ['DELETE'])]
    public function delete(ManagerRegistry $doctrine, int $id): jsonResponse
    {
        $entityManager = $doctrine->getManager();

        $customer = $this->getUser()->getCustomer();
        $product = $this->productRepository->getProductCustomer($id, $customer);

        if (!$product) {
            throw new HttpException(403, "Vous n'avez pas les droits suffisants pour supprimer ce produit.");
        }

        $this->productService->removeProduct($entityManager, $product);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}