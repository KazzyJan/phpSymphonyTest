<?php

namespace App\Controller;

use App\Entity\Url;
use App\Repository\UrlRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

use Symfony\Component\HttpFoundation\Response;


class UrlController extends AbstractController
{

    /**
     * @Route("/urls", name="api_urls", methods={"GET"})
     */
    public function index(UrlRepository $urlRepository)
    {
        $urls = $urlRepository->findAll();

        $data = [];
        foreach ($urls as $url) {
            $data[] = [
                'url' => $url->getUrl(),
                'createdAt' => $url->getCreatedDate()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data);
    }
    /**
     * @Route("/encode-url", name="encode_url")
     */
    public function encodeUrl(Request $request, UrlRepository $urlRepository): JsonResponse
    {
        $url = new Url();
        $url->setUrl($request->get('url'));
        $urlString = $url->getUrl();

        $existingUrl = $urlRepository->findOneBy(['url' => $urlString]);
        if ($existingUrl) {
            return $this->json([
                'hash' => $existingUrl->getHash(),
                'message' => 'URL already exist'
            ]);
        }

        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($url);
        $entityManager->flush();

        return $this->json([
            'hash' => $url->getHash()
        ]);
    }

    /**
     * @Route("/decode-url", name="decode_url")
     */
    public function decodeUrl(Request $request): JsonResponse
    {
        $lifetime = 300;
        $realTime = time();

        /** @var UrlRepository $urlRepository */
        $urlRepository = $this->getDoctrine()->getRepository(Url::class);
        $url = $urlRepository->findOneByHash($request->get('hash'));
        if (empty ($url)) {
            return $this->json([
                'error' => 'Non-existent hash.'
            ]);
        }
        else if ($url->getCreatedDate()->getTimestamp() < $realTime-$lifetime) {
            return $this->json([
                'error' => 'The urls lifetime has expired.'
            ]);
        }
        return $this->json([
            'url' => $url->getUrl(),
            'remaining life time' => $url->getCreatedDate()->getTimestamp() - ($realTime-$lifetime),
        ]);
    }

    /**
     * @Route("/urls", name="api_urlsPOST", methods={"POST"})
     */
    public function store(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data)) {
            return new JsonResponse(['error' => 'No data provided'], 400);
        }

        $filePath = '/var/www/url-shortener.loc/var/urls.txt';

        $file = fopen($filePath, 'a');

        if ($file === false) {
            throw new \RuntimeException('Unable to open or create the file: ' . $filePath);
        }

        if ($file === false) {
            return new JsonResponse(['error' => 'Unable to open the file'], 500);
        }

        foreach ($data as $urlData) {
            $line = "URL: " . $urlData['url'] . ", Created At: " . $urlData['createdAt'] . "\n";
            fwrite($file, $line);
        }

        fclose($file);

        return new JsonResponse(['status' => 'URLs saved to file successfully']);
    }

    /**
     * @Route("/encode-form", name="encode_form")
     */
    public function encodeForm(): Response
    {
        $html = '
        <form action="/encode-url" method="POST">
            <label for="url">Enter URL:</label>
            <input type="text" id="url" name="url">
            <button type="submit">Encode URL</button>
        </form>
    ';

        return new Response($html);
    }

    /**
     * @Route("/gourl", name="gourl")
     */
    public function gourl(Request $request)
    {
        /** @var UrlRepository $urlRepository */
        $urlRepository = $this->getDoctrine()->getRepository(Url::class);
        $url = $urlRepository->findOneByHash($request->get('hash'));
        if (empty ($url)) {
            return $this->json([
                'error' => 'Non-existent hash.'
            ]);
        }
        return $this->redirect($url->getUrl());
    }
}
