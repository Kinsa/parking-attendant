<?php

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Entity\Vehicle;

class VehicleSearchTest extends ApiTestCase
{
    private static $PLATE = 'AA 1234AB';
    private static $SIMILAR_PLATE = 'AA I2BAAB';
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
        $vehicle->setVrm(self::$PLATE);
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
     * Test that a request without the plate parameter returns a bad request response.
     */
    public function testNoPlateProvided(): void
    {
        static::createClient()->request('GET', '/search');

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains(['message' => 'A vehicle license plate is required.']);
    }

    /**
     * Test that a license plate search for a plate not in the database
     * returns a not found response.
     */
    public function testNoResultsFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE.'XYZ'],
        ]);

        $this->assertResponseStatusCodeSame(404);
        $this->assertJsonContains(['message' => 'No results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE.'XYZ',
                    'time_in' => null,
                    'expired' => true,
                    'expiration_time' => null,
                ],
            ],
        ]);
    }

    /**
     * Test that a license plate search returns matching results.
     *
     * Searches for an exact match of a plate and expects to find
     * the full matching vehicle record.
     */
    public function testMatchFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                    'expiration_time' => (new \DateTimeImmutable(self::$TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test that a similar license plate search returns matching results.
     */
    public function testSimilarMatchFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$SIMILAR_PLATE],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                    'expiration_time' => (new \DateTimeImmutable(self::$TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test that a partial license plate search returns matching results.
     */
    public function testPartialSimilarMatchFound(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => substr(self::$PLATE, 0, 8)],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '1 result found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                    'expiration_time' => (new \DateTimeImmutable(self::$TIME_IN))->add(new \DateInterval('PT2H'))->format('Y-m-d H:i:s'),
                ],
            ],
        ]);
    }

    /**
     * Test the same car, yesterday, is returned expired then but not for today.
     */
    public function testSameCarOutsideOfTimeframeIsExcluded(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$PLATE);
        $vehicle->setTimeIn($yesterday);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '2 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                ],
                [
                    'vrm' => self::$PLATE,
                    'time_in' => $yesterday->format('Y-m-d H:i:s'),
                    'expired' => true,
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
            'query' => ['plate' => self::$PLATE, 'datetime' => 'xylophone'],
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
            'query' => ['plate' => self::$PLATE, 'datetime' => $yesterday->format('Y-m-d')],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test datetime query with a bad date.
     */
    public function testSpecificDateQueryWithBadDate(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE, 'datetime' => '2025-02-30 12:00:00'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid datetime format or invalid date/time values. Use YYYY-MM-DD HH:MM:SS with valid dates.',
        ]);
    }

    /**
     * Test the same car, yesterday, when queried with yesterday's date.
     */
    public function testSpecificDateQuery(): void
    {
        $yesterday = new \DateTimeImmutable('-1 day');

        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE, 'datetime' => $yesterday->format('Y-m-d H:i:s')],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '2 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                ],
                [
                    'vrm' => self::$PLATE,
                    'time_in' => $yesterday->format('Y-m-d H:i:s'),
                    'expired' => false,
                ],
            ],
        ]);
    }

    /**
     * Test the same car, left and returned within the window is included for both entrances.
     * Remember, the car from the previous test still exists in the database.
     */
    public function testSameCarTwiceWithinWindow(): void
    {
        $ten_minutes_ago = new \DateTimeImmutable('-10 minutes');

        $entityManager = self::getContainer()->get('doctrine')->getManager();
        $vehicle = new Vehicle();
        $vehicle->setVrm(self::$PLATE);
        $vehicle->setTimeIn($ten_minutes_ago);

        $entityManager->persist($vehicle);
        $entityManager->flush();

        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '3 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => $ten_minutes_ago->format('Y-m-d H:i:s'),
                    'expired' => false,
                ],
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                ],
                [
                    'vrm' => self::$PLATE,
                    'expired' => true,
                ],
            ],
        ]);
    }

    /**
     * Test the same car, with a 48 hour window.
     */
    public function test48HourWindow(): void
    {
        $ten_minutes_ago = new \DateTimeImmutable('-10 minutes');

        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE, 'window' => 2880],
        ]);
        $this->assertResponseStatusCodeSame(200);
        $this->assertJsonContains(['message' => '3 results found.']);
        $this->assertJsonContains([
            'results' => [
                [
                    'vrm' => self::$PLATE,
                    'time_in' => $ten_minutes_ago->format('Y-m-d H:i:s'),
                    'expired' => false,
                ],
                [
                    'vrm' => self::$PLATE,
                    'time_in' => self::$TIME_IN,
                    'expired' => false,
                ],
                [
                    'vrm' => self::$PLATE,
                    'expired' => false,
                ],
            ],
        ]);
    }

    /**
     * Test a bad window format. MapQueryParameter automatically handles this and returns a 404.
     */
    public function testBadWindow(): void
    {
        static::createClient()->request('GET', '/search', [
            'query' => ['plate' => self::$PLATE, 'window' => 'zebra'],
        ]);
        $this->assertResponseStatusCodeSame(400);
        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonContains([
            'message' => 'Invalid window format or value. Window must be a positive integer value.',
        ]);
    }
}
