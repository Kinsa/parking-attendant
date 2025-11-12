<?php

namespace App\Controller;

use App\Repository\VehicleRepository;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

class SearchController extends AbstractController
{
    public function __construct(private VehicleRepository $doctrine)
    {
    }

    private static function isValidDateTime(string $datetime): bool
    {
        $format = 'Y-m-d H:i:s';
        $dateTime = \DateTime::createFromFormat($format, $datetime);

        // Check if the datetime was created successfully AND
        // the formatted version matches the input (catches invalid dates)
        return $dateTime && $dateTime->format($format) === $datetime;
    }

    private function returnInvalidDateResponse(): JsonResponse
    {
        $response = new JsonResponse();
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $response->setData(['message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.', 'results' => []]);
        return $response;
    }

    #[Route('/search', name: 'search')]
    public function search(
        #[MapQueryParameter] string $plate = '',
        #[MapQueryParameter] string $datetime = '',
        #[MapQueryParameter] int $window = 120,
    ): JsonResponse {

        if (empty($datetime)) {
            // Set default values for date parameters if not provided
            $calculateExpiredParkingFrom = new \DateTimeImmutable('now');
        } else {
            if (!self::isValidDateTime($datetime)) {
                return self::returnInvalidDateResponse();
            } else {
                try {
                    $calculateExpiredParkingFrom = new \DateTimeImmutable($datetime);
                } catch (\Exception $e) {
                    return self::returnInvalidDateResponse();
                }
            }
        }

        // Calculate parking duration window
        $parkingWindow = new \DateInterval('PT'.$window.'M');

        $latestSafeParkedTime = $calculateExpiredParkingFrom->sub($parkingWindow);

        // create a new Response object
        $response = new JsonResponse();

        $vehicleRepository = $this->doctrine;

        if (!empty($plate)) {
            $matches = $vehicleRepository->findByPlate($plate);
            if (empty($matches)) {
                $response->setStatusCode(Response::HTTP_NOT_FOUND);
                $response->setData([
                    'message' => 'No results found.',
                    'results' => [
                        [
                            'license_plate' => $plate,
                            'time_in' => null,
                            'expired' => true,
                            'expiration_time' => null,
                        ],
                    ],
                ]);
            } else {
                $response->setStatusCode(Response::HTTP_OK);
                $message = count($matches).' ';
                $message .= 1 === count($matches) ? 'result' : 'results';
                $message .= ' found.';
                for ($i = 0; $i < count($matches); ++$i) {
                    $time_in = new \DateTimeImmutable($matches[$i]['time_in']);
                    $time_in_str = $time_in->format('Y-m-d H:i:s');
                    $expired_at = $time_in->add($parkingWindow); // Adds parking window to time_in

                    // Parking is expired if the expiration time is before the search window end
                    $is_expired = $expired_at < $latestSafeParkedTime;

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
            $response->setData(
                [
                    'message' => 'A vehicle license plate is required via the plate query string. e.g. `plate=AA%201234AB`.',
                    'results' => [],
                ],
            );
        }

        return $response;
    }
}
