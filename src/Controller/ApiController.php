<?php

namespace App\Controller;

use App\Repository\VehicleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Annotation\Route;

class ApiController extends AbstractController
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

    #[Route('/api/v1/vehicle', name: 'api_v1_vehicle')]
    public function api(
        LoggerInterface $logger,
        #[MapQueryParameter] string $vrm = '',
        #[MapQueryParameter] string $window = '120',
        #[MapQueryParameter] string $query_from = '',
        #[MapQueryParameter] string $query_to = '',
    ): JsonResponse {
        // Set default values for date parameters if not provided
        if (empty($query_to)) {
            $calculateParkingSessionsFrom = new \DateTimeImmutable('now');
        } else {
            if (!self::isValidDateTime($query_to)) {
                return self::returnInvalidDateResponse('query_to');
            } else {
                try {
                    $calculateParkingSessionsFrom = new \DateTimeImmutable($query_to);
                } catch (\Exception $e) {
                    return self::returnInvalidDateResponse('query_to');
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

        $response = new JsonResponse();
        if ($query_from_dt && $query_from_dt > $calculateParkingSessionsFrom) {
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            $response->setData(['message' => 'query_from must be earlier than or equal to query_to.']);

            return $response;
        }

        // create a new Response object

        $vehicleRepository = $this->doctrine;

        if (!empty($vrm)) {
            $vrmPattern = '/^[A-Za-z0-9\s]+$/';
            if (!preg_match($vrmPattern, $vrm)) {
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
                $response->setData(['message' => 'Invalid VRM format. VRM must only contain letters and numbers. Spaces are allowed.']);
                return $response;
            }

            $searchResultQueryResponse = $vehicleRepository->findByVrm($vrm, $calculateParkingSessionsFrom, $query_from_dt);
            $response->setStatusCode(Response::HTTP_OK);
            if (empty($searchResultQueryResponse)) {
                $response->setData([
                    'message' => 'No matches for VRM found.',
                    'results' => [
                        [
                            'vrm' => $vrm,
                            'session' => 'none',
                            'session_start' => null,
                            'session_end' => null,
                        ],
                    ],
                ]);
            } else {
                $searchResultArray = [];

                for ($i = 0; $i < count($searchResultQueryResponse); ++$i) {
                    try {
                        $time_in = new \DateTimeImmutable($searchResultQueryResponse[$i]['time_in']);
                    } catch (\Exception $e) {
                        $logger->critical('Error parsing time_in value', [
                            'time_in_value' => $searchResultQueryResponse[$i]['time_in'],
                            'entry_id' => $searchResultQueryResponse[$i]['id'],
                            'error' => $e->getMessage(),
                            'exception_class' => get_class($e),
                        ]);
                        continue;
                    }
                    $time_in_str = $time_in->format('Y-m-d H:i:s');
                    $session_end = $time_in->add($parkingWindow); // Adds parking window to time_in

                    // Parking is expired if current time is past the session end time
                    $isExpired = $calculateParkingSessionsFrom > $session_end;

                    if (!$isExpired && $searchResultQueryResponse[$i]['vrm'] === $vrm) {
                        // If we have a partial session and an exact VRM match, return only the single result
                        $searchResultArray = [[
                            'vrm' => $searchResultQueryResponse[$i]['vrm'],
                            'session' => $isExpired ? 'full' : 'partial',
                            'session_start' => $time_in_str,
                            'session_end' => $session_end->format('Y-m-d H:i:s'),
                            'distance' => $searchResultQueryResponse[$i]['distance'],
                        ]];
                        break;
                    } else {
                        $searchResultArray[$i] = [
                            'vrm' => $searchResultQueryResponse[$i]['vrm'],
                            'session' => $isExpired ? 'full' : 'partial',
                            'session_start' => $time_in_str,
                            'session_end' => $session_end->format('Y-m-d H:i:s'),
                            'distance' => $searchResultQueryResponse[$i]['distance'],
                        ];
                    }
                }

                $message = count($searchResultArray).' ';
                $message .= 1 === count($searchResultArray) ? 'result' : 'results';
                $message .= ' found.';

                $response->setData(['message' => $message, 'results' => $searchResultArray]);
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
