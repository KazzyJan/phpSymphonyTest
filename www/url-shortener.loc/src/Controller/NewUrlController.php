<?php

namespace App\Controller;

use App\Repository\NewUrlRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NewUrlController extends AbstractController
{
    private $newUrlRepository;

    public function __construct(NewUrlRepository $newUrlRepository)
    {
        $this->newUrlRepository = $newUrlRepository;
    }

    /**
     * @Route("/api/urls", name="api_newUrls", methods={"POST"})
     */
    public function createUrl(Request $request, NewUrlRepository $newUrlRepository): JsonResponse
    {
        $contentType = $request->headers->get('Content-Type');

        if ($contentType === 'application/json') {
            $data = json_decode($request->getContent(), true);
            $url = $data['url'] ?? null;
            $createdAt = $data['createdAt'] ?? null;
        } else {
            $url = $request->request->get('url');
            $createdAt = $request->request->get('createdAt');
        }

        if ($url && $createdAt) {
            try {

                $createdAt = new \DateTimeImmutable($createdAt);

                $newUrlRepository->saveUrl($url, $createdAt);

                return new JsonResponse(['status' => 'URL created', 'url' => $url, 'createdAt' => $createdAt->format('Y-m-d H:i:s')], 201);
            } catch (\Exception $e) {
                return new JsonResponse(['error' => 'Failed to save URL: ' . $e->getMessage()], 500);
            }
        }

        return new JsonResponse(['error' => 'Invalid data'], 400);
    }

    /**
     * @Route("/api/url-form", name="urlApi_form")
     */
    public function urlForm(): Response
    {
        $html = '
    <form action="/api/urls" method="POST">
        <label for="url">Enter URL:</label>
        <input type="text" id="url" name="url" required>
        <label for="createdAt">Created At:</label>
        <input type="datetime-local" id="createdAt" name="createdAt" required>
        <button type="submit">Submit URL</button>
    </form>
    ';

        return new Response($html);
    }

    /**
     * @Route("/api/urls/statistics", methods={"GET"})
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $domain = $request->query->get('domain');

        $uniqueUrlsCount = $this->newUrlRepository->countUniqueUrlsBetween(new \DateTime($startDate), new \DateTime($endDate));
        $uniqueDomainCount = $this->newUrlRepository->countUniqueUrlsByDomain($domain);

        return new JsonResponse([
            'uniqueUrlsCount' => $uniqueUrlsCount,
            'uniqueDomainCount' => $uniqueDomainCount,
        ]);
    }
}
