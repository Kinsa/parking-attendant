# Parking Attendant Backend

This project demonstrates an API interface for querying vehicle entries into a parking lot and assessing whether they have overstayed their session.

## Example Use Cases

There are a series of use case scenarios which [are described and can be run in Postman](https://kinsacreative-9361599.postman.co/workspace/Kinsa-Creative's-Workspace~0b9e49cf-f915-49e3-833c-f71cae8edbe0/collection/49907170-7408272b-c732-468f-8f25-6613326e2065?action=share&creator=49907170). All that is required to access and run them is a free Postman account.

### Production Fixture Data

The production database contains the following entries. These can be queried using Postman.

```
+----+----------+---------------------+
| id | vrm      | time_in             |
+----+----------+---------------------+
| 18 | MA10 VQY | 2025-11-11 00:29:00 |
|  4 | MA04 TVZ | 2025-11-11 00:30:00 |
| 20 | MA92 KKE | 2025-11-11 01:32:00 |
| 13 | MA22 MUZ | 2025-11-11 02:56:00 |
|  2 | MA25 KHT | 2025-11-11 04:23:00 |
|  6 | MA93 GEG | 2025-11-11 05:32:00 |
|  9 | MA97 GRA | 2025-11-11 06:07:00 |
| 11 | MA04 OCM | 2025-11-11 06:45:00 |
| 10 | MA01 POV | 2025-11-11 07:28:00 |
| 12 | MA06 MWU | 2025-11-11 10:25:00 |
| 14 | MA19 ZZW | 2025-11-11 11:25:00 |
| 17 | MA13 NFV | 2025-11-11 12:07:00 |
| 15 | MA90 AMH | 2025-11-11 15:37:00 |
|  7 | MA16 GXX | 2025-11-11 15:49:00 |
| 19 | MA97 PPO | 2025-11-11 15:56:00 |
|  8 | MA94 TEJ | 2025-11-11 17:16:00 |
|  1 | MA06 GLQ | 2025-11-11 19:46:00 |
|  3 | MA15 BSL | 2025-11-11 19:55:00 |
| 21 | 16 GX    | 2025-11-11 20:41:00 |
| 16 | MA94 IWT | 2025-11-11 22:11:00 |
|  5 | MA10 JPJ | 2025-11-11 23:23:00 |
+----+----------+---------------------+
```

## API Documentation

### GET /api/v1/vehicle

Query vehicle parking records by VRM (Vehicle Registration Mark).

#### Required Parameters
- `vrm` (string) - Vehicle Registration Mark to search for

#### Optional Parameters
- `window` (integer) - Parking duration window in minutes
  - Default: `120`
  - Must be a positive integer

- `query_from` (string) - Limit results to entries after this datetime
  - Format: `YYYY-MM-DD HH:MM:SS`
  - Must be provided together with `query_to`
  - Must be earlier than or equal to `query_to`

- `query_to` (string) - Reference datetime for session calculation
  - Format: `YYYY-MM-DD HH:MM:SS`
  - Default: Current time
  - Example: `2024-11-13 14:30:00`

#### Example Requests

```bash
# Basic search
curl "http://localhost:8000/api/v1/vehicle?vrm=AB12CDE"

# Search with custom datetime and window
curl "http://localhost:8000/api/v1/vehicle?vrm=AB12CDE&query_to=2024-11-13 14:30:00&window=180"

# Search within a specific time range
curl "http://localhost:8000/api/v1/vehicle?vrm=AB12CDE&query_from=2024-11-13 10:00:00&query_to=2024-11-13 16:00:00"
```

#### Response Format

**Success (200 OK):**
```json
{
  "message": "1 result found.",
  "results": [
    {
      "vrm": "AB12CDE",
      "session": "partial",
      "session_start": "2024-11-13 12:00:00",
      "session_end": "2024-11-13 14:00:00"
    }
  ]
}
```

**No match (200 OK):**
```json
{
  "message": "No matches for VRM found.",
  "results": [
    {
      "vrm": "AB12CDE",
      "session": "none",
      "session_start": null,
      "session_end": null
    }
  ]
}
```

**Error (400 Bad Request):**
```json
{
  "message": "A VRM is required."
}
```

**Note:** If there is an exact match for the VRM and the session is partial, just that result is returned.

#### Session Status Values
- `partial` - Vehicle is currently within the parking window
- `full` - Vehicle's parking session has expired
- `none` - No parking record found for the VRM

## Development Database

Docker container running mariadb and phpMyAdmin.

To start:

```
docker-compose up -d
```

Then open your browser to `http://localhost:8080` and log in with (defined in the `docker-compose.yml` file):

- Email: admin@admin.com
- Password: admin123

To stop and remove the containers (preserving the data in the volume):

```
docker-compose down
```

To remove the database:

```
docker-compose down -v
```

To just stop (to restart later):

```
docker-compose stop
```

## Symfony

### Create the databases

```bash
#sh
php bin/console doctrine:database:create
php bin/console --env=test doctrine:database:create
```

### Setup columns in the databases

```bash
# sh
php bin/console doctrine:schema:create
php bin/console --env=test doctrine:schema:create
```

### Migrate the databases

```bash
#sh
php bin/console doctrine:migrations:migrate
php bin/console --env=test doctrine:migrations:migrate
```

### Load fixtures (non-test database only)

```bash
#sh
php bin/console doctrine:fixtures:load
```

### Run server

```bash
# sh
symfony server:start
```

### Run tests

```bash
# sh
php bin/phpunit
```

### Make and Run Migrations

```bash
#sh
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

## Development Log

### 1. Initial Setup ([`187b79b`](https://github.com/Kinsa/ParkingAttendantBackend/commit/187b79b))
- Created minimal Symfony 7.3.x framework
- Installed Doctrine ORM for database management ([`8f7a74b`](https://github.com/Kinsa/ParkingAttendantBackend/commit/8f7a74b))

### 2. Data Modeling ([`749373b`](https://github.com/Kinsa/ParkingAttendantBackend/commit/749373b))
- Created `Vehicle` entity with fields: `id`, `license_plate` (string), `time_in` (DateTimeImmutable)
- Initially named entity "Vehicles" (plural), later refactored to singular naming convention
- Installed [DoctrineFixturesBundle](https://symfony.com/bundles/DoctrineFixturesBundle/current/index.html) for test data
- Created fixtures to generate 20 sample vehicles with:
  - UK-format license plates (researched format on Google)
  - Random timestamps from previous day
  - Resources consulted:
    - [PHP string manipulation](https://github.com/copilot) for getting last 2 characters
    - [StackOverflow](https://stackoverflow.com/a/31441519) for random character generation
    - [PHP DateTime::modify()](https://www.php.net/manual/en/datetime.modify.php) for date manipulation
    - [StackOverflow](https://stackoverflow.com/a/58259320) for random time generation

### 3. Basic API Implementation ([`7edaf93`](https://github.com/Kinsa/ParkingAttendantBackend/commit/7edaf93), [`f0510e6`](https://github.com/Kinsa/ParkingAttendantBackend/commit/f0510e6))
- Created `SearchController` with `/search` endpoint
- Implemented initial plate search functionality in `VehicleRepository`
- Resources consulted:
  - [Postman Symfony API guide](https://quickstarts.postman.com/guide/php-symfony-API/index.html?index=..%2F..index#3)
  - [Symfony Doctrine SQL querying](https://symfony.com/doc/current/doctrine.html#querying-with-sql)
  - [Symfony query parameter mapping](https://symfony.com/blog/new-in-symfony-6-3-query-parameters-mapper)
  - [StackOverflow](https://stackoverflow.com/a/70211792) for calling Repository methods in controllers
- Smoke tested with Postman

### 4. Testing Infrastructure ([`0a08148`](https://github.com/Kinsa/ParkingAttendantBackend/commit/0a08148))
- Installed API Platform and test dependencies:
  - `composer require --dev symfony/test-pack`
  - `composer require api-platform/api-pack`
  - `composer require --dev foundry orm-fixtures`
- Created `VehicleSearchTest` API test suite
- Configured Docker database permissions for test database (used Claude AI for SQL GRANT configuration)
- Fixed deprecation warnings by adding `protected static ?bool $alwaysBootKernel = true;`
- Implemented setUp/tearDown methods for test isolation
- Resources consulted:
  - [Symfony JSON responses](https://symfony.com/doc/current/components/http_foundation.html#creating-a-json-response)
  - [Symfony testing documentation](https://symfony.com/doc/current/testing.html)
  - [API Platform testing guide](https://api-platform.com/docs/symfony/testing/#creating-data-fixtures)
- AI Assistance:
  - Configured PHPStorm test runner with KERNEL_CLASS ([`cfefea7`](https://github.com/Kinsa/ParkingAttendantBackend/commit/cfefea7)), refactoring tests from setup / tear down per-test to per-suite, docker config, api-pack installation, ignoring bits of the response when JSON response matching 

### 5. Fuzzy Search Implementation ([`7b00104`](https://github.com/Kinsa/ParkingAttendantBackend/commit/7b00104), [`0455d40`](https://github.com/Kinsa/ParkingAttendantBackend/commit/0455d40))
- Implemented SOUNDEX for fuzzy matching
- Combined SOUNDEX with partial LIKE matching using UNION query with DISTINCT
- Added reverse chronological ordering
- Lessons learned: SOUNDEX algorithm limitations with partial matches
- Resources consulted:
  - [MySQL Fuzzy Search StackOverflow](https://stackoverflow.com/questions/369755/how-do-i-do-a-fuzzy-match-of-company-names-in-mysql-for-auto-complete) 
  - [Querying SOUNDEX StackOverflow](https://stackoverflow.com/questions/29598065/how-to-query-soundex-in-mysql)
- AI assistance: Query syntax for UNION ordering

### 6. Date/Time Window Functionality ([`e2ac708`](https://github.com/Kinsa/ParkingAttendantBackend/commit/e2ac708) → [`b27f1a0`](https://github.com/Kinsa/ParkingAttendantBackend/commit/b27f1a0))
**Phase 1: Initial date filtering ([`e2ac708`](https://github.com/Kinsa/ParkingAttendantBackend/commit/e2ac708))**
- Added date range parameters to filter query results
- Resolved Doctrine DateTimeImmutable string conversion issues

**Phase 2: Dynamic test dates ([`1e2d6ad`](https://github.com/Kinsa/ParkingAttendantBackend/commit/1e2d6ad))**
- Made test dates dynamic for future-proof testing
- AI assistance: DateTime property initialisation syntax

**Phase 3: Session validation logic ([`812469e`](https://github.com/Kinsa/ParkingAttendantBackend/commit/812469e))**
- Moved date validation from query to controller
- Changed API response from "safe/unsafe" to session-based model
- Added `expired` boolean and `expiration_time` fields
- AI assistance: DateInterval/timedelta calculations

**Phase 4: API simplification ([`b27f1a0`](https://github.com/Kinsa/ParkingAttendantBackend/commit/b27f1a0))**
- Refactored parameters: `date_start`/`date_end` → `datetime` + `window` (minutes)
- Implemented DateInterval for parking window: `new \DateInterval('PT' . $window . 'M');`
- Simplified date calculations

### 7. Refinement Based on Requirements ([`dcf60fb`](https://github.com/Kinsa/ParkingAttendantBackend/commit/dcf60fb) → [`c53293c`](https://github.com/Kinsa/ParkingAttendantBackend/commit/c53293c))
**Code quality ([`dcf60fb`](https://github.com/Kinsa/ParkingAttendantBackend/commit/dcf60fb), [`e63808d`](https://github.com/Kinsa/ParkingAttendantBackend/commit/e63808d), [`d8dda0c`](https://github.com/Kinsa/ParkingAttendantBackend/commit/d8dda0c), [`88a9750`](https://github.com/Kinsa/ParkingAttendantBackend/commit/88a9750))**
- Formatted codebase, standardised variable naming
- Added .gitignore for Mac/IDE files
- Documented database setup and test procedures in README

**Datetime validation ([`8ad925e`](https://github.com/Kinsa/ParkingAttendantBackend/commit/8ad925e), [`57df273`](https://github.com/Kinsa/ParkingAttendantBackend/commit/57df273), [`ad0248e`](https://github.com/Kinsa/ParkingAttendantBackend/commit/ad0248e))**
- Replaced regex validation with `DateTime::createFromFormat()` (AI recommendation)
- Implemented `isValidDateTime()` helper method
- Added comprehensive datetime format tests

**Window validation ([`1a492c6`](https://github.com/Kinsa/ParkingAttendantBackend/commit/1a492c6))**
- Changed `window` parameter from int to string with validation
- Added type checking before casting to DateInterval
- AI assistance: Identified type requirement issue

**Terminology refactor ([`92e010e`](https://github.com/Kinsa/ParkingAttendantBackend/commit/92e010e), [`e2103f4`](https://github.com/Kinsa/ParkingAttendantBackend/commit/e2103f4))**
- Renamed "license plate" → "VRM" (Vehicle Registration Mark) throughout codebase
- Updated all tests and documentation

**Session status refactor ([`d4fbbff`](https://github.com/Kinsa/ParkingAttendantBackend/commit/d4fbbff), [`4f74591`](https://github.com/Kinsa/ParkingAttendantBackend/commit/4f74591))**
- Changed `expired` boolean → `session` enum: "partial"/"full"/"none"
- Changed 404 response → 200 with `session: none` for better UX

**Error handling ([`3b70cc9`](https://github.com/Kinsa/ParkingAttendantBackend/commit/3b70cc9))**
- Added structured logging for DateTimeImmutable parsing exceptions
- AI assistance: Improved error output structure

**Query refactoring ([`dc58777`](https://github.com/Kinsa/ParkingAttendantBackend/commit/dc58777), [`e7530a8`](https://github.com/Kinsa/ParkingAttendantBackend/commit/e7530a8))**
- Rewrote SQL query using HEREDOC for readability (AI suggested multiple options)
- Applied PHPStorm refactoring suggestions

### 8. Query Time Range Feature ([`5107364`](https://github.com/Kinsa/ParkingAttendantBackend/commit/5107364), [`c53293c`](https://github.com/Kinsa/ParkingAttendantBackend/commit/c53293c))
- Added `query_from` and `query_to` optional parameters
- Implemented validation:
  - Both parameters required together
  - `query_from` must be ≤ `query_to`
  - `datetime` must fall within query range
- Updated repository and SQL query to support conditional date filtering
- Updated tests for new parameters ([`057d802`](https://github.com/Kinsa/ParkingAttendantBackend/commit/057d802))

### 9. Documentation ([`057d802`](https://github.com/Kinsa/ParkingAttendantBackend/commit/057d802), [`5f8eb28`](https://github.com/Kinsa/ParkingAttendantBackend/commit/5f8eb28))
- Removed outdated search method references
- Added comprehensive API documentation with examples
- Documented all query parameters, response formats, and session statuses

### 10. Use Case Scenarios and Test Isolation ([`6f993c1`](https://github.com/Kinsa/ParkingAttendantBackend/commit/6f993c1), [`1a9303e`](https://github.com/Kinsa/ParkingAttendantBackend/commit/1a9303e))
- Added comprehensive use case scenarios to README as full workflows
- Fixed partial VRM search: updated query to return results when partial VRM is recorded but full VRM is searched
- Refactored test lifecycle from class-based (`setupBeforeClass`/`tearDownAfterClass`) to test-based (`setUp`/`tearDown`) for proper test isolation
- AI assistance: Explained `LIKE` query order behavior; directed refactoring from class-based to test-based setup/teardown

### 11. Session Expiration Logic Fix ([`70036cf`](https://github.com/Kinsa/ParkingAttendantBackend/commit/70036cf))
- Fixed incorrect session status when comparing entries across days
- Identified issue through failing test case: was checking if vehicle parked before window began
- Corrected logic to check if vehicle still present after session ended
- AI assistance: Debugging session comparison logic; suggested correct expiration check

### 12. Levenshtein Distance Implementation ([`1c30b35`](https://github.com/Kinsa/ParkingAttendantBackend/commit/1c30b35))
- Replaced `SOUNDS LIKE` with Levenshtein distance for better visual similarity matching
- Identified limitation: `SOUNDS LIKE` is auditory (Q/O fail to match despite visual similarity from dirt/OCR errors)
- Added MySQL Levenshtein function to database
- Tuned distance threshold to 3 (distance of 4 returned too many dissimilar results)
- Limitation: Returns too many results with correct match sometimes buried in response
- Resources consulted:
  - [soundex PHP function documentation](https://www.php.net/manual/en/function.soundex.php) 
  - [Levenshtein PHP function documentation](https://www.php.net/manual/en/function.levenshtein.php) 
  - [Lucidar MySQL Levenshtein Distance](https://lucidar.me/en/web-dev/levenshtein-distance-in-mysql/)

### 13. Regex Pattern Matching for OCR Errors ([`10d5de0`](https://github.com/Kinsa/ParkingAttendantBackend/commit/10d5de0), [`de50b17`](https://github.com/Kinsa/ParkingAttendantBackend/commit/de50b17), [`9e0f190`](https://github.com/Kinsa/ParkingAttendantBackend/commit/9e0f190))
**Initial implementation ([`10d5de0`](https://github.com/Kinsa/ParkingAttendantBackend/commit/10d5de0))**
- Attempted to address Levenshtein result overload with regex character substitution
- Replaced confusable characters with regex patterns (O/0/Q, I/1, B/8)
- Removed wildcard support and related tests
- Initial approach failed: nested replacements modified already-replaced characters
- Resources consulted:
  - [DevArt MySQL REPLACE function](https://www.devart.com/dbforge/mysql/studio/mysql-replace.html)
  - [DataCamp MySQL REGEXP](https://www.datacamp.com/doc/mysql/mysql-regexp)
  - [StackOverflow multiple REPLACE query](https://stackoverflow.com/questions/5460364/mysql-multiple-replace-query)
- AI assistance: Generated initial character substitution approach using `str_replace()`

**Pattern refinement ([`de50b17`](https://github.com/Kinsa/ParkingAttendantBackend/commit/de50b17))**
- Implemented two-pass replacement: unique placeholders first, then regex patterns
- Fixed case sensitivity, regex spacing, and parameter passing issues
- Addressed test failures related to wildcard removal and A/4 confusion
- Researched OCR character confusion patterns to validate substitution choices
- Resources consulted:
  - [Community History Archives OCR misinterpretations](https://communityhistoryarchives.com/100-common-ocr-letter-misinterpretations/)
- AI assistance: Suggested two-pass replacement strategy; identified additional code issues; noted A/4 has Levenshtein distance of 1 for potential threshold tuning

**Exact match optimisation ([`9e0f190`](https://github.com/Kinsa/ParkingAttendantBackend/commit/9e0f190))**
- Added early return for exact match with partial session
- Prevents buried correct results in large response sets

### 14. Result Set Optimisation ([`4af5ac3`](https://github.com/Kinsa/ParkingAttendantBackend/commit/4af5ac3), [`3bd1941`](https://github.com/Kinsa/ParkingAttendantBackend/commit/3bd1941), [`6281d65`](https://github.com/Kinsa/ParkingAttendantBackend/commit/6281d65))
- Removed `datetime` parameter; simplified to `query_from`/`query_to` only
- Added datetime filter to `findByVrm()` to limit results by entry time
- Ordered results by Levenshtein distance (closest matches first)
- Return only latest session when VRM has an exact match and session is partial
- AI assistance: Corrected SQL query syntax for proper parameter handling and result ordering  

### 15. Refactoring for Use with Frontend ([`88b6f2c`](https://github.com/Kinsa/ParkingAttendantBackend/commit/88b6f2c))
- Set the endpoint route to `/api/v1/vehicle` rather than `/search` to allow a frontend to sit at `/`, to version the API for future use, and to make the name more generic to allow for `POST` as well as `GET` requests
- [Prototyped front end](https://github.com/Kinsa/ParkingAttendantFrontend) to validate usability of ordering and fuzzy search 

### 16. Revise Regex Pattern Matching to Account for Character Order ([`30afd0f`](https://github.com/Kinsa/ParkingAttendantBackend/commit/30afd0f), [`9a1fb8d`](https://github.com/Kinsa/ParkingAttendantBackend/commit/9a1fb8d))
- When running through the Postman scenarios one last time I saw that I was getting an unexpected result for a plate I thought had been passing. Using Xdebug I identified that the order of characters which the REGEX did pattern substitution against was very important. Cleaned up the pattern to not include any alphanumeric characters other than the match and added tests.
- Added regex matching of the VRM itself
- AI assistance: validated the VRM regex

### Outstanding Items / Questions
- [ ] Raise an error if the VRM value contains anything other than A-Z, space, or 0-9 once capitalised
- [ ] Pagination - probably not necessary with date parameters?
- [ ] Ordering - API query to return responses purely by date?
- [ ] OpenAPI documentation
- [ ] Timezone handling for datetime parameters in query: everything currently works so long as all the dates use the same timezone as the system timezone - but that means converting the datetime before submitting it.
- [ ] Should the session window be tracked via a specific datetime object instead of minutes? Minutes work well for a short-term lot. In terms of flexibility, in a long-term lot, someone might purchase a month at a time. It might be more straight forward to track that with an end date instead of minutes.
- [ ] Custom / non-UK plate handling. Specific implications include that we are currently running Levenshtein if the match is less than 8 characters. The dataset should include some custom options.
