<?php

namespace App\Repository;

use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    //    /**
    //     * @return Vehicle[] Returns an array of Vehicle objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('v.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Vehicle
    //    {
    //        return $this->createQueryBuilder('v')
    //            ->andWhere('v.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }

    /**
     * @return [] Returns an array of Vehicle objects
     */
    public function findByVrm(string $vrm, ?\DateTimeImmutable $query_from, ?\DateTimeImmutable $query_to): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        $dateCondition = !empty($query_from) ? 'AND v.time_in BETWEEN :query_from AND :query_to' : '';

        $sql = <<<SQL
            SELECT DISTINCT * FROM (
                (SELECT * FROM vehicle v
                 WHERE v.vrm SOUNDS LIKE :vrm {$dateCondition})
                UNION ALL
                (SELECT * FROM vehicle v
                 WHERE v.vrm LIKE CONCAT(:vrm, "%") {$dateCondition})
            ) AS combined_results
            ORDER BY time_in DESC
        SQL;

        $resultSet = $conn->executeQuery($sql, [
            'vrm' => $vrm,
            'query_from' => $query_from,
            'query_to' => $query_to,
        ]);

        // returns an array of arrays (i.e. a raw data set)
        return $resultSet->fetchAllAssociative();
    }
}
