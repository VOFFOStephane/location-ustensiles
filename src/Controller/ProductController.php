<?php

namespace App\Controller;

use App\Repository\ProductRepository;
use App\Repository\CategoryRepository;
use App\Service\AvailabilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\Tools\Pagination\Paginator;

final class ProductController extends AbstractController
{
    #[Route('/', name: 'product_index', methods: ['GET'])]
    public function index(
        Request $request,
        ProductRepository $productRepo,
        CategoryRepository $categoryRepo
    ): Response {
        $q = trim((string) $request->query->get('q', ''));

        // ⚠️ évite l’erreur "FILTER_NULL_ON_FAILURE"
        $catRaw = (string) $request->query->get('category', '0');
        $categoryId = ctype_digit($catRaw) ? (int) $catRaw : 0;

        $page = max(1, (int) $request->query->get('page', 1));

        $limit = 5; // ✅ 5 produits par page
        $offset = ($page - 1) * $limit;

        // produits + total
        [$products, $total] = $productRepo->searchPaginated($q, $categoryId, $limit, $offset);

        $pages = (int) max(1, ceil($total / $limit));

        return $this->render('product/index.html.twig', [
            'products' => $products,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'q' => $q,
            'categoryId' => $categoryId,
            'categories' => $categoryRepo->findBy(['isDisabled' => false], ['name' => 'ASC']),
        ]);
    }

    #[Route('/product/{id}', name: 'product_show', methods: ['GET'])]
    public function show(int $id, ProductRepository $repo): Response
    {
        $product = $repo->findOneBy(['id' => $id, 'isDisabled' => false]);
        if (!$product) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('product/show.html.twig', [
            'product' => $product,
        ]);
    }

    // Endpoint AJAX JSON pour afficher "disponible sur ces dates"
    #[Route('/product/{id}/availability', name: 'product_availability', methods: ['GET'])]
    public function availability(
        int $id,
        Request $request,
        AvailabilityService $availability
    ): JsonResponse {
        $startStr = $request->query->get('startDate');
        $endStr   = $request->query->get('endDate');

        if (!$startStr || !$endStr) {
            return $this->json(['error' => 'Dates manquantes'], 400);
        }

        try {
            $start = new \DateTimeImmutable($startStr);
            $end   = new \DateTimeImmutable($endStr);
        } catch (\Throwable) {
            return $this->json(['error' => 'Dates invalides'], 400);
        }

        if ($end < $start) {
            return $this->json(['error' => 'Fin avant début'], 400);
        }

        $available = $availability->getAvailableQuantity($id, $start, $end);

        return $this->json([
            'ok' => true,
            'available' => $available,
            'startDate' => $start->format('Y-m-d'),
            'endDate'   => $end->format('Y-m-d'),
        ]);
    }
}

