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
            '0' => '___PLACEHOLDER_0___',
            'O' => '___PLACEHOLDER_O___',
            'Q' => '___PLACEHOLDER_Q___',
            '1' => '___PLACEHOLDER_1___',
            'I' => '___PLACEHOLDER_I___',
            '8' => '___PLACEHOLDER_8___',
            'B' => '___PLACEHOLDER_B___',
            '5' => '___PLACEHOLDER_5___',
            'S' => '___PLACEHOLDER_S___',
            '2' => '___PLACEHOLDER_2___',
            'Z' => '___PLACEHOLDER_Z___',
        ];

        // First pass: replace with placeholders
        foreach ($replacements as $char => $placeholder) {
            $pattern = str_replace($char, $placeholder, $pattern);
        }

        // Second pass: replace placeholders with character classes
        $pattern = str_replace('___PLACEHOLDER_0___', '[0OQ]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_O___', '[0OQ]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_Q___', '[0OQ]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_1___', '[1I]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_I___', '[1I]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_8___', '[8B]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_B___', '[8B]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_5___', '[5S]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_S___', '[5S]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_2___', '[2Z]', $pattern);
        $pattern = str_replace('___PLACEHOLDER_Z___', '[2Z]', $pattern);

        // Add anchors
        return '^'.$pattern.'$';
    }

    /**
     * @return [] Returns an array of Vehicle objects
     */
    public function findByVrm(string $vrm, ?\DateTimeImmutable $datetime, ?\DateTimeImmutable $query_from, ?\DateTimeImmutable $query_to): ?array
    {
        $conn = $this->getEntityManager()->getConnection();

        $vrmPattern = $this->createFlexibleRegexPattern($vrm);

        $datetime_str = $datetime->format('Y-m-d H:i:s');

        $query_from_str = $query_from ? $query_from->format('Y-m-d H:i:s') : '';
        $query_to_str = $query_to ? $query_to->format('Y-m-d H:i:s') : '';

        $dateCondition = !empty($query_from_str) && !empty($query_to_str) ? ' AND v.time_in BETWEEN :query_from AND :query_to' : '';

        $sql = <<<SQL
            SELECT DISTINCT * FROM (
                (
                    SELECT *, levenshtein(:vrm, v.vrm) AS distance
                    FROM vehicle v
                    WHERE v.vrm REGEXP :vrmPattern {$dateCondition}
                    AND v.time_in <= :datetime
                )
                UNION ALL
                (
                    SELECT *, levenshtein(:vrm, v.vrm) AS distance
                    FROM vehicle v
                    WHERE levenshtein(:vrm, v.vrm) BETWEEN 0 AND 4 {$dateCondition}
                    AND CHAR_LENGTH(v.vrm) < 9
                    AND v.time_in <= :datetime
                )
            ) AS combined_results
            ORDER BY time_in DESC
        SQL;

        $queryParameters = [
            'vrm' => $vrm,
            'vrmPattern' => $vrmPattern,
            'datetime' => $datetime_str,
        ];

        if (!empty($query_from_str) && !empty($query_to_str)) {
            $queryParameters['query_from'] = $query_from_str;
            $queryParameters['query_to'] = $query_to_str;
        }

        $resultSet = $conn->executeQuery($sql, $queryParameters);

        // returns an array of arrays (i.e. a raw data set)
        return $resultSet->fetchAllAssociative();
    }
}
