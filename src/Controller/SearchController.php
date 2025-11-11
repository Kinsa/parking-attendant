<?php

namespace App\Controller;

use App\Repository\VehicleRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class SearchController extends AbstractController
{
    public function __construct(private VehicleRepository $doctrine) {}

    #[Route('/search', name: 'search')]
    public function search(
        #[MapQueryParameter] string $plate = '',
        #[MapQueryParameter] DateTime $date_start = null,
        #[MapQueryParameter] DateTime $date_end = null,
    ): Response
    {
        // create a new Response object
        $response = new Response();

        $vehicleRepository = $this->doctrine;

        if (isset($plate)) {
            $matches = $vehicleRepository->findByPlate($plate);
            $response->setContent(print_r($matches, true));
        } else {
            $response->setContent('No plate provided.');
        }

        // make sure we send a 200 OK status
        $response->setStatusCode(Response::HTTP_OK);

        // set the response content type to plain text
        $response->headers->set('Content-Type', 'text/plain');

        // send the response with appropriate headers
        $response->send();
    }
}
