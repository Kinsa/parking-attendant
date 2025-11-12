<?php

namespace App\Controller;

use App\Repository\VehicleRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

class SearchController extends AbstractController
{
    public function __construct(private VehicleRepository $doctrine) {}

    #[Route('/search', name: 'search')]
    public function search(
        #[MapQueryParameter] string $plate = '',
        #[MapQueryParameter] ?DateTime $at = null,
        #[MapQueryParameter] int $window = 120,
    ): JsonResponse
    {
        // Set default values for date parameters if not provided
        if ($at === null) {
            $date_end = new \DateTimeImmutable('now');
        }

        // Calculate parking duration window
        $parking_window = new \DateInterval('PT' . $window . 'M');

        $date_start = $date_end->sub($parking_window);

        // create a new Response object
        $response = new JsonResponse();

        $vehicleRepository = $this->doctrine;

        if (isset($plate) && !empty($plate)) {
            $matches = $vehicleRepository->findByPlate($plate);
            if (!$matches || empty($matches)) {
                $response->setStatusCode(Response::HTTP_NOT_FOUND);
                $response->setData(['message'=>'No results found.', 'results'=>[[
                    'license_plate' => $plate,
                    'time_in' => null,
                    'expired' => true,
                    'expiration_time' => null,
                ]]]);
            } else {
                $response->setStatusCode(Response::HTTP_OK);
                $message = count($matches) . " ";
                $message .= count($matches) === 1 ? 'result' : 'results';
                $message .= " found.";
                for ($i = 0; $i < count($matches); $i++) {
                    $time_in = new \DateTimeImmutable($matches[$i]['time_in']);
                    $time_in_str = $time_in->format('Y-m-d H:i:s');
                    $expired_at = $time_in->add($parking_window); // Adds parking window to time_in

                    // Parking is expired if the expiration time is before the search window end
                    $is_expired = $expired_at < $date_start;

                    $matches[$i] = [
                        'license_plate' => $matches[$i]['license_plate'],
                        'time_in' => $time_in_str,
                        'expired' => $is_expired,
                        'expiration_time' => $expired_at->format('Y-m-d H:i:s'),
                    ];
                }
                $response->setData(['message' => $message, 'results' => $matches]);
            }
        } else {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setData(['message'=>'A vehicle license plate is required via the plate query string. e.g. `plate=AA%201234AB`.', 'results'=>[]]);
        }

        // set the response content type to application/json (not plain text for JSON)
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
