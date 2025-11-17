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

    /*
     * Regex matching
     *
     *  Step 1: Convert to uppercase
     *  $pattern = 'AA 1234AB';  // becomes 'AA 1234AB'
     *
     *  Step 2: Replace spaces with optional space pattern
     *  $pattern = 'AA[ ]?1234AB';  // [ ]? means "optional space"
     *
     *  Step 3: Replace confusable chars with PLACEHOLDERS first
     *  // '1' → '___PLACEHOLDER_1___'
     *  // 'B' → '___PLACEHOLDER_B___'
     *  $pattern = 'AA[ ]?___PLACEHOLDER_1___234A___PLACEHOLDER_B___';
     *
     *  Step 4: Replace placeholders with character classes
     *  // No risk of nested replacements now!
     *  $pattern = 'AA[ ]?[1I]234A[8B]';
     *
     *  Step 5: Add anchors for full-string matching
     *  $pattern = '^AA[ ]?[1I]234A[8B]$';
     *
     *  Character Classes Explained
     *
     *  - [1I] = "match either 1 OR I" (one character)
     *  - [0OQ] = "match either 0 OR O OR Q"
     *  - [8B] = "match either 8 OR B"
     *  - [ ]? = "optionally match a space" (the ? means 0 or 1 occurrences)
     *
     *  So the pattern ^AA[ ]?[1I]234A[8B]$ will match:
     *  - AA 1234AB ✓ (exact match)
     *  - AA1234AB ✓ (no space)
     *  - AA I234AB ✓ (1→I substitution)
     *  - AA 1234A8 ✓ (B→8 substitution)
     *  - AAI2348 ✓ (no space, 1→I, B→8)
     */
    private function createFlexibleRegexPattern(string $vrm): string
    {
        // Convert to uppercase and normalize
        $pattern = strtoupper($vrm);

        // Replace spaces with optional space pattern
        $pattern = str_replace(' ', '[ ]?', $pattern);

        // Replace commonly confused OCR characters with character classes
        // Use a temporary placeholder to avoid replacing characters within the character classes
        $replacements = [
            '0' => '___0___',
            'O' => '___O___',
            'Q' => '___Q___',
            '1' => '___1___',
            'I' => '___I___',
            '8' => '___8___',
            'B' => '___B___',
            '5' => '___5___',
            'S' => '___S___',
            '2' => '___2___',
            'Z' => '___Z___',
        ];

        // First pass: replace with placeholders
        foreach ($replacements as $char => $placeholder) {
            $pattern = str_replace($char, $placeholder, $pattern);
        }

        // Second pass: replace placeholders with character classes
        $pattern = str_replace('___0___', '[0OQ]', $pattern);
        $pattern = str_replace('___O___', '[0OQ]', $pattern);
        $pattern = str_replace('___Q___', '[0OQ]', $pattern);
        $pattern = str_replace('___1___', '[1I]', $pattern);
        $pattern = str_replace('___I___', '[1I]', $pattern);
        $pattern = str_replace('___8___', '[8B]', $pattern);
        $pattern = str_replace('___B___', '[8B]', $pattern);
        $pattern = str_replace('___5___', '[5S]', $pattern);
        $pattern = str_replace('___S___', '[5S]', $pattern);
        $pattern = str_replace('___2___', '[2Z]', $pattern);
        $pattern = str_replace('___Z___', '[2Z]', $pattern);

        // Add anchors
        return '^'.$pattern.'$';
    }

    /**
     * @return [] Returns an array of Vehicle objects
     */
    public function findByVrm(string $vrm, \DateTimeImmutable $query_to, ?\DateTimeImmutable $query_from): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        $vrmPattern = $this->createFlexibleRegexPattern($vrm);

        $query_from_str = $query_from ? $query_from->format('Y-m-d H:i:s') : '';
        $query_to_str = $query_to->format('Y-m-d H:i:s');

        $dateCondition = !empty($query_from_str) ? ' AND v.time_in BETWEEN :query_from AND :query_to' : 'AND v.time_in <= :query_to';

        $sql = <<<SQL
            SELECT DISTINCT * FROM (
                (
                    SELECT *, levenshtein(:vrm, v.vrm) AS distance
                    FROM vehicle v
                    WHERE v.vrm REGEXP :vrmPattern {$dateCondition}
                )
                UNION ALL
                (
                    SELECT *, levenshtein(:vrm, v.vrm) AS distance
                    FROM vehicle v
                    WHERE levenshtein(:vrm, v.vrm) BETWEEN 0 AND 4 {$dateCondition}
                    AND CHAR_LENGTH(v.vrm) < 8
                )
            ) AS combined_results
            ORDER BY distance ASC, time_in DESC
        SQL;

        $queryParameters = [
            'vrm' => $vrm,
            'vrmPattern' => $vrmPattern,
            'query_to' => $query_to_str,
        ];

        if (!empty($query_from_str)) {
            $queryParameters['query_from'] = $query_from_str;
        }

        $resultSet = $conn->executeQuery($sql, $queryParameters);

        // returns an array of arrays (i.e. a raw data set)
        return $resultSet->fetchAllAssociative();
    }
}
