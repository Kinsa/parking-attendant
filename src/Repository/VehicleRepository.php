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

        $vrnPattern = str_replace(
            ['0', 'O', 'Q', '1', 'I', '8', 'B'],
            ['[0OQ]', '[0OQ]', '[0OQ]', '[1I]', '[1I]', '[8B]', '[8B]'],
            strtoupper($vrm)
        );

        $query_from_str = $query_from ? $query_from->format('Y-m-d H:i:s') : '';
        $query_to_str = $query_to ? $query_to->format('Y-m-d H:i:s') : '';

        $dateCondition = !empty($query_from_str) && !empty($query_to_str) ? ' AND v.time_in BETWEEN :query_from AND :query_to' : '';

        $sql = <<<SQL
            SELECT DISTINCT * FROM (
                (
                    SELECT *, levenshtein(:vrm, v.vrm) AS distance
                    FROM vehicle v
                    WHERE v.vrm REGEXP :vrnPattern {$dateCondition}
                )
                UNION ALL
                (
                    SELECT *, levenshtein(:vrm, v.vrm) AS distance
                    FROM vehicle v
                    WHERE levenshtein(:vrm, v.vrm) BETWEEN 0 AND 3 {$dateCondition}
                    AND CHAR_LENGTH(v.vrm) < 9
                )
            ) AS combined_results
            ORDER BY time_in DESC
        SQL;

        $resultSet = $conn->executeQuery($sql, [
            'vrm' => $vrm,
            'vrnPattern' => $vrnPattern,
            'query_from' => $query_from_str,
            'query_to' => $query_to_str,
        ]);

        // returns an array of arrays (i.e. a raw data set)
        return $resultSet->fetchAllAssociative();
    }
}
