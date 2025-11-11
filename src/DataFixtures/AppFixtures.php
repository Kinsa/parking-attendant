<?php

namespace App\DataFixtures;

use App\Entity\Vehicle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create 20 vehicle entries with random license plates and time_in values yesterday
        for ($i = 0; $i < 20; $i++) {
            $randDate = new \DateTime();
            $randDate->setTime(mt_rand(0, 23), mt_rand(0, 59));
            $randDate->modify('-1 day');
            $date = \DateTimeImmutable::createFromFormat("Y-m-d H:i:s", date("Y-m-d H:i:s", $randDate->getTimestamp()));
            $vehicle = new Vehicle();
            $vehicle->setLicensePlate('MA' . substr(rand(1990,2025), -2) . ' ' . chr(rand(65,90)) . chr(rand(65,90)) . chr(rand(65,90)) );
            $vehicle->setTimeIn($date);
            $manager->persist($vehicle);
        }

        $manager->flush();
    }
}
