<?php

namespace App\Controller;

use App\Repository\VehicleRepository;
use Psr\Log\LoggerInterface;
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
        $response->setData(['message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.']);

        return $response;
    }

    private function returnInvalidWindowResponse(): JsonResponse
    {
        $response = new JsonResponse();
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $response->setData(['message' => 'Invalid window format or value. Window must be a positive integer value.']);

        return $response;
    }

    #[Route('/search', name: 'search')]
    public function search(
        LoggerInterface $logger,
        #[MapQueryParameter] string $plate = '',
        #[MapQueryParameter] string $datetime = '',
        #[MapQueryParameter] string $window = '120',
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
        if (!is_numeric($window) || (int) $window <= 0
        ) {
            return self::returnInvalidWindowResponse();
        } else {
            try {
                $parkingWindow = new \DateInterval('PT'.$window.'M');
                $latestSafeParkedTime = $calculateExpiredParkingFrom->sub($parkingWindow);
            } catch (\Exception $e) {
                return self::returnInvalidWindowResponse();
            }
        }

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
                            'vrm' => $plate,
                            'time_in' => null,
                            'session' => 'none',
                            'session_end' => null,
                        ],
                    ],
                ]);
            } else {
                $response->setStatusCode(Response::HTTP_OK);
                $message = count($matches).' ';
                $message .= 1 === count($matches) ? 'result' : 'results';
                $message .= ' found.';
                for ($i = 0; $i < count($matches); ++$i) {
                    try {
                        $time_in = new \DateTimeImmutable($matches[$i]['time_in']);
                    } catch (\Exception $e) {
                        $logger->critical('Error parsing time_in value', [
                            'time_in_value' => $matches[$i]['time_in'],
                            'entry_id' => $matches[$i]['id'],
                            'error' => $e->getMessage(),
                            'exception_class' => get_class($e),
                        ]);
                        continue;
                    }
                    $time_in_str = $time_in->format('Y-m-d H:i:s');
                    $session_end = $time_in->add($parkingWindow); // Adds parking window to time_in

                    // Parking is expired if the expiration time is before the search window end
                    $is_expired = $session_end < $latestSafeParkedTime;

                    $matches[$i] = [
                        'vrm' => $matches[$i]['vrm'],
                        'time_in' => $time_in_str,
                        'session' => $is_expired ? 'full' : 'partial',
                        'session_end' => $session_end->format('Y-m-d H:i:s'),
                    ];
                }
                $response->setData(['message' => $message, 'results' => $matches]);
            }
        } else {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setData(
                [
                    'message' => 'A vehicle license plate is required.',
                ],
            );
        }

        return $response;
    }
}
