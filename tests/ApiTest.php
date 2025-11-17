<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Vehicle;

class ApiTest extends ApiTestCase
{
    private static $VRM = 'AA 1234AB';
    private static $SIMILAR_VRM = 'AA I234A8';
    private $TIME_IN;
    private $API_PATH = '/api/v1/vehicle';

    protected static ?bool $alwaysBootKernel = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize TIME_IN with current time minus 1 hour for each test
        $date = new \DateTimeImmutable('-1 hour');
        $this->TIME_IN = $date->format('Y-m-d H:i:s');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($date);

        $entityManager->persist($vehicle);
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        // Clear the table after each test
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $conn = $entityManager->getConnection();
        $sql = 'DELETE FROM vehicle';
        $conn->executeQuery($sql);

        parent::tearDown();
    }

    /**
     * Test that a request without the VRM parameter returns a bad request response.
     */
    public function testNoPlateProvided(): void
    {
        static::createClient()->request('GET', $this->API_PATH);

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['message' => 'A VRM is required.']);
    }

    /**
     * Test that a VRM search for a VRM not in the database returns a found response with session set as `none`.
     */
    public function testNoResultsFound(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => 'BATH'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => 'No matches for VRM found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => 'BATH',
                    'session_start' => null,
                    'session' => 'none',
                    'session_end' => null,
                ],
            ],
        ]);
    }

    /**
     * Test that a VRM-only search returns an exact match when one exists.
     */
    public function testMatchFound(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $this->TIME_IN,
                    'session' => 'partial',
                    'session_end' => (new \DateTimeImmutable($this->TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test that a similar VRM search returns matching results (tests fuzzy search).
     */
    public function testSimilarMatchFound(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$SIMILAR_VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $this->TIME_IN,
                    'session' => 'partial',
                    'session_end' => (new \DateTimeImmutable($this->TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test partial lookup with a complete value input for lookup but only partial value stored.
     */
    public function testPartialVRMRecorded(): void
    {
        $fullVRM = 'ZZ 7689XY';
        $partialVRM = substr($fullVRM, 2, 6);
        $tenMinutesAgo = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm($partialVRM);
        $vehicle->setTimeIn($tenMinutesAgo);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => $fullVRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => $partialVRM,
                    'session_start' => $tenMinutesAgo->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test similar lookup with a complete value input for lookup but only partial value stored further confusing things by swapping a letter for a number.
     */
    public function testSimilarPartialVRMRecordedSwapNumberAndLetter(): void
    {
        $fullVRM = 'ZZ 7689XY';
        $partialVRM = substr($fullVRM, 2, 6); // stores ` 7689` in the database
        $tenMinutesAgo = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm($partialVRM);
        $vehicle->setTimeIn($tenMinutesAgo);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => 'ZZ 76B9XY'], // '8' replaced with 'B'
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => $partialVRM,
                    'session_start' => $tenMinutesAgo->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * If the same car was in the lot yesterday, that session should be excluded from results when there is a partial session.
     */
    public function testSameCarOutsideOfTimeframeIsExcluded(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($yesterday);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $this->TIME_IN,
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test the same VRM, yesterday, when queried with yesterday's date.
     */
    public function testSpecificDateQuery(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($yesterday);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$VRM, 'query_to' => $yesterday->format('Y-m-d H:i:s')],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $yesterday->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test the same car, left and returned within the current window includes the latest value only (they paid on entry).
     */
    public function testSameCarTwiceWithinWindow(): void
    {
        $tenMinutesAgo = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($tenMinutesAgo);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $tenMinutesAgo->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test the same car, with a 48 hour window returns 3 partial sessions.
     */
    public function test48HourWindow(): void
    {
        $entityManager = self::getContainer()->get('doctrine')->getManager();

        $tenMinutesAgo = new \DateTimeImmutable('-10 minutes');
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($tenMinutesAgo);
        $entityManager->persist($vehicle);

        $yesterday = new \DateTimeImmutable('-1 day');
        $vehicle2 = new Vehicle();
        $vehicle2->setVrm(self::$VRM);
        $vehicle2->setTimeIn($yesterday);

        $entityManager->persist($vehicle2);

        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$VRM, 'window' => 2880],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $tenMinutesAgo->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test a bad window format.
     */
    public function testBadWindow(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => self::$VRM, 'window' => 'zebra'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid window format or value. Window must be a positive integer value.',
        ]);
    }

    /**
     * Test query_from must be earlier than or equal to query_to.
     */
    public function testQueryFromLaterThanQueryTo(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-03 00:00:00',
                'query_to' => '2024-01-02 00:00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'query_from must be earlier than or equal to query_to.',
        ]);
    }

    /**
     * Test query_from must be a datetime string.
     */
    public function testQueryFromWithBadDate(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => 'not-a-date',
                'query_to' => '2024-01-02 00:00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid query_from format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test query_to must be a datetime string.
     */
    public function testQueryToWithBadDate(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-01 00:00:00',
                'query_to' => 'not-a-date',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid query_to format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test query_from must represent a valid date.
     */
    public function testQueryFromWithInvalidDate(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2023-12-32 00:00:00',
                'query_to' => '2024-01-02 00:00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid query_from format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test query_to must represent a valid date.
     */
    public function testQueryToWithInvalidDate(): void
    {
        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-01 00:00:00',
                'query_to' => '2024-02-30 00:00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid query_to format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test valid query specifying date_from and date_to.
     */
    public function testDateFromAndDateTo(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($yesterday);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        $yesterday_start = new \DateTime('-1 day');
        $yesterday_start->setTime(0, 0, 0);

        $yesterday_end = new \DateTime($yesterday_start->format('Y-m-d'));
        $yesterday_end->setTime(23, 59, 59);

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => $yesterday_start->format('Y-m-d H:i:s'),
                'query_to' => $yesterday_end->format('Y-m-d H:i:s'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session' => 'full',
                ],
            ],
        ]);
    }

    /**
     * Test that setting a date range limits the results to the relevant vehicle.
     */
    public function testSpecificDateQueryWithDateRange(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($yesterday);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => $yesterday->format('Y-m-d 00:00:00'),
                'query_to' => $yesterday->format('Y-m-d H:i:s'),
            ],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'session_start' => $yesterday->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test session value across days.
     */
    public function testSessionValueAcrossDays(): void
    {
        $timeIn = new \DateTimeImmutable('2025-11-11 23:00:00');
        $now = new \DateTimeImmutable('2025-11-12 02:00:00');
        $vrm = 'ASDF';

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm($vrm);
        $vehicle->setTimeIn($timeIn);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => $vrm, 'query_to' => $now->format('Y-m-d H:i:s')],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => $vrm,
                    'session_start' => $timeIn->format('Y-m-d H:i:s'),
                    'session' => 'full',
                ],
            ],
        ]);
    }

    /**
     * This specific plate caused an issue. The vrmPattern should be `^MA[0OQ]6[ ]?GL[0OQ]$`.
     */
    public function testRegexPatternMatching(): void
    {
        $vrm = 'MA06 GLO';
        $tenMinutesAgo = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm($vrm);
        $vehicle->setTimeIn($tenMinutesAgo);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => $vrm],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => $vrm,
                ],
            ],
        ]);
    }

    /**
     * VRM can only contain letters and numbers, optionally formatted with spaces.
     */
    public function testVRMPattern(): void
    {
        $vrm = 'MA06_GLO';
        $tenMinutesAgo = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm($vrm);
        $vehicle->setTimeIn($tenMinutesAgo);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', $this->API_PATH, [
            'query' => ['vrm' => $vrm],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['message' => 'Invalid VRM format. VRM must only contain letters and numbers. Spaces are allowed.']);
    }
}
