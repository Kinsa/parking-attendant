<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Vehicle;

class VehicleSearchTest extends ApiTestCase
{
    private static $VRM = 'AA 1234AB';
    private static $SIMILAR_VRM = 'AA I2BAAB';
    private static $TIME_IN;

    protected static ?bool $alwaysBootKernel = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Initialize TIME_IN with current time minus 1 hour
        $date = new \DateTimeImmutable('-1 hour');
        self::$TIME_IN = $date->format('Y-m-d H:i:s');

        // Boot kernel to get container
        self::bootKernel();

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($date);

        $entityManager->persist($vehicle);
        $entityManager->flush();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        // Clear the table after all tests
        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $conn = $entityManager->getConnection();
        $sql = 'DELETE FROM vehicle';
        $conn->executeQuery($sql);
    }

    /**
     * Test that a request without the VRM parameter returns a bad request response.
     */
    public function testNoPlateProvided(): void
    {
        static::createClient()->request('GET', '/search');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['message' => 'A VRN is required.']);
    }

    /**
     * Test that a VRM search for a VRM not in the database returns a found response with session set as `none`.
     */
    public function testNoResultsFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM.'XYZ'],
        ]);

        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => 'No matches for VRN found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM.'XYZ',
                    'time_in' => null,
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
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                    'session_end' => (new \DateTimeImmutable(self::$TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test that a similar VRM search returns matching results (tests fuzzy search).
     */
    public function testSimilarMatchFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$SIMILAR_VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                    'session_end' => (new \DateTimeImmutable(self::$TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test that a partial VRM search returns matching results (tests wildcard search).
     */
    public function testPartialSimilarMatchFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => substr(self::$VRM, 0, 8)],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                    'session_end' => (new \DateTimeImmutable(self::$TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test the same VRN, yesterday, is returned with a full session for yesterday and a partial session for today.
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

        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '2 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                ],
                [
                    'vrm' => self::$VRM,
                    'time_in' => $yesterday->format('Y-m-d H:i:s'),
                    'session' => 'full',
                ],
            ],
        ]);
    }

    /**
     * Test a bad datetime query.
     */
    public function testBadSpecificDateQuery(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'datetime' => 'xylophone'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test a partial datetime query.
     */
    public function testPartialSpecificDateQuery(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'datetime' => $yesterday->format('Y-m-d')],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test datetime query with a correctly formatted but invalid date.
     */
    public function testSpecificDateQueryWithBadDate(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'datetime' => '2025-02-30 12:00:00'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test the same VRM, yesterday, when queried with yesterday's date.
     */
    public function testSpecificDateQuery(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'datetime' => $yesterday->format('Y-m-d H:i:s')],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '2 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                ],
                [
                    'vrm' => self::$VRM,
                    'time_in' => $yesterday->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
            ],
        ]);
    }

    /**
     * Test the same car, left and returned within the current window is included for both entrances with partial sessions.
     * Remember, the car from the previous test still exists in the database.
     */
    public function testSameCarTwiceWithinWindow(): void
    {
        $ten_minutes_ago = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$VRM);
        $vehicle->setTimeIn($ten_minutes_ago);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '3 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => $ten_minutes_ago->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                ],
                [
                    'vrm' => self::$VRM,
                    'session' => 'full',
                ],
            ],
        ]);
    }

    /**
     * Test the same car, with a 48 hour window returns 3 partial sessions.
     */
    public function test48HourWindow(): void
    {
        $ten_minutes_ago = new \DateTimeImmutable('-10 minutes');

        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'window' => 2880],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '3 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$VRM,
                    'time_in' => $ten_minutes_ago->format('Y-m-d H:i:s'),
                    'session' => 'partial',
                ],
                [
                    'vrm' => self::$VRM,
                    'time_in' => self::$TIME_IN,
                    'session' => 'partial',
                ],
                [
                    'vrm' => self::$VRM,
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
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'window' => 'zebra'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid window format or value. Window must be a positive integer value.',
        ]);
    }

    /**
     * Test both query_from and query_to must be provided together.
     */
    public function testQueryFromWithoutQueryTo(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'query_from' => '2024-01-01 00:00:00'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Both query_from and query_to must be provided together.',
        ]);
    }

    public function testQueryToWithoutQueryFrom(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['vrm' => self::$VRM, 'query_to' => '2024-01-02 00:00:00'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Both query_from and query_to must be provided together.',
        ]);
    }

    /**
     * Test query_from must be earlier than or equal to query to.
     */
    public function testQueryFromLaterThanQueryTo(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-03 00:00:00',
                'query_to' => '2024-01-02 00:00:00',
                'datetime' => '2024-01-01 12:00:00',
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
        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => 'not-a-date',
                'query_to' => '2024-01-02 00:00:00',
                'datetime' => '2024-01-01 12:00:00',
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
        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-01 00:00:00',
                'query_to' => 'not-a-date',
                'datetime' => '2024-01-15 12:00:00',
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
        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2023-12-32 00:00:00',
                'query_to' => '2024-01-02 00:00:00',
                'datetime' => '2024-01-01 12:00:00',
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
        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-01 00:00:00',
                'query_to' => '2024-02-30 00:00:00',
                'datetime' => '2024-01-15 12:00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid query_to format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test valid date_from and date_to but datetime outside the range between them.
     * Using current datetime as 'now' for this test.
     */
    public function testQueryFromWindowDoesNotIncludeDatetime(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => '2024-01-01 00:00:00',
                'query_to' => '2024-02-20 00:00:00',
            ],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'datetime must be later than or equal to query_from and earlier than or equal to query_to.',
        ]);
    }

    /**
     * Test valid query specifying date_from and date_to.
     */
    public function testDateFromAndDateTo(): void
    {
        $yesterday_start = new \DateTime('-1 day');
        $yesterday_start->setTime(0, 0, 0);

        $yesterday_end = new \DateTime($yesterday_start->format('Y-m-d'));
        $yesterday_end->setTime(23, 59, 59);

        static::createClient()->request('GET', '/search', [
            'query' => [
                'vrm' => self::$VRM,
                'query_from' => $yesterday_start->format('Y-m-d H:i:s'),
                'query_to' => $yesterday_end->format('Y-m-d H:i:s'),
                'datetime' => $yesterday_end->format('Y-m-d H:i:s'),
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

}