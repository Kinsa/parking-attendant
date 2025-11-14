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

    private function returnInvalidDateResponse(string $queryParameter): JsonResponse
    {
        $response = new JsonResponse();
        $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        $response->setData(['message' => "Invalid $queryParameter format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates."]);

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
        #[MapQueryParameter] string $vrm = '',
        #[MapQueryParameter] string $datetime = '',
        #[MapQueryParameter] string $window = '120',
        #[MapQueryParameter] string $query_from = '',
        #[MapQueryParameter] string $query_to = '',
    ): JsonResponse {
        // Set default values for date parameters if not provided
        if (empty($datetime)) {
            $calculateParkingSessionsFrom = new \DateTimeImmutable('now');
        } else {
            if (!self::isValidDateTime($datetime)) {
                return self::returnInvalidDateResponse('datetime');
            } else {
                try {
                    $calculateParkingSessionsFrom = new \DateTimeImmutable($datetime);
                } catch (\Exception $e) {
                    return self::returnInvalidDateResponse('datetime');
                }
            }
        }

        // Calculate parking duration window
        if (!is_numeric($window) || (int) $window <= 0) {
            return self::returnInvalidWindowResponse();
        } else {
            try {
                $parkingWindow = new \DateInterval('PT'.$window.'M');
            } catch (\Exception $e) {
                return self::returnInvalidWindowResponse();
            }
        }

        // From and to
        if (empty($query_from)) {
            $query_from_dt = null;
        } else {
            if (!self::isValidDateTime($query_from)) {
                return self::returnInvalidDateResponse('query_from');
            } else {
                try {
                    $query_from_dt = new \DateTimeImmutable($query_from);
                } catch (\Exception $e) {
                    return self::returnInvalidDateResponse('query_from');
                }
            }
        }

        if (empty($query_to)) {
            $query_to_dt = null;
        } else {
            if (!self::isValidDateTime($query_to)) {
                return self::returnInvalidDateResponse('query_to');
            } else {
                try {
                    $query_to_dt = new \DateTimeImmutable($query_to);
                } catch (\Exception $e) {
                    return self::returnInvalidDateResponse('query_to');
                }
            }
        }

        if (isset($query_from_dt) && !isset($query_to_dt) || !isset($query_from_dt) && isset($query_to_dt)) {
            $response = new JsonResponse();
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setData(['message' => 'Both query_from and query_to must be provided together.']);

            return $response;
        }

        if ($query_from_dt && $query_to_dt && $query_from_dt > $query_to_dt) {
            $response = new JsonResponse();
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setData(['message' => 'query_from must be earlier than or equal to query_to.']);

            return $response;
        }

        if ($query_from_dt && $query_to_dt && ($calculateParkingSessionsFrom < $query_from_dt || $calculateParkingSessionsFrom > $query_to_dt)) {
            $response = new JsonResponse();
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setData(['message' => 'datetime must be later than or equal to query_from and earlier than or equal to query_to.']);

            return $response;
        }

        // create a new Response object
        $response = new JsonResponse();

        $vehicleRepository = $this->doctrine;

        if (!empty($vrm)) {
            $matches = $vehicleRepository->findByVrm($vrm, $query_from_dt, $query_to_dt);
            $response->setStatusCode(Response::HTTP_OK);
            if (empty($matches)) {
                $response->setData([
                    'message' => 'No matches for VRM found.',
                    'results' => [
                        [
                            'vrm' => $vrm,
                            'time_in' => null,
                            'session' => 'none',
                            'session_end' => null,
                        ],
                    ],
                ]);
            } else {
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

                    // Parking is expired if current time is past the session end time
                    $is_expired = $calculateParkingSessionsFrom > $session_end;

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
                    'message' => 'A VRM is required.',
                ],
            );
        }

        return $response;
    }
}
