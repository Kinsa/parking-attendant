This project contains a means of tracking vehicle entries into a parking lot and assessing whether they have overstayed their session.

## API Documentation

### GET /search

Query vehicle parking records by VRM (Vehicle Registration Mark).

#### Required Parameters
- `vrm` (string) - Vehicle Registration Mark to search for

#### Optional Parameters
- `datetime` (string) - Reference datetime for session calculation
  - Format: `YYYY-MM-DD HH:MM:SS`
  - Default: Current time
  - Example: `2024-11-13 14:30:00`

- `window` (integer) - Parking duration window in minutes
  - Default: `120`
  - Must be a positive integer

- `query_from` (string) - Limit results to entries after this datetime
  - Format: `YYYY-MM-DD HH:MM:SS`
  - Must be provided together with `query_to`
  - Must be earlier than or equal to `query_to`

- `query_to` (string) - Limit results to entries before this datetime
  - Format: `YYYY-MM-DD HH:MM:SS`
  - Must be provided together with `query_from`
  - Must be later than or equal to `query_from`

**Note:** When using `query_from` and `query_to`, the `datetime` parameter must fall within this range.

#### Example Requests

```bash
# Basic search
curl "http://localhost:8000/search?vrm=AB12CDE"

# Search with custom datetime and window
curl "http://localhost:8000/search?vrm=AB12CDE&datetime=2024-11-13 14:30:00&window=180"

# Search within a specific time range
curl "http://localhost:8000/search?vrm=AB12CDE&query_from=2024-11-13 10:00:00&query_to=2024-11-13 16:00:00"
```

#### Response Format

**Success (200 OK):**
```json
{
  "message": "1 result found.",
  "results": [
    {
      "vrm": "AB12CDE",
      "time_in": "2024-11-13 12:00:00",
      "session": "partial",
      "session_end": "2024-11-13 14:00:00"
    }
  ]
}
```

**No match (200 OK):**
```json
{
  "message": "No matches for VRN found.",
  "results": [
    {
      "vrm": "AB12CDE",
      "time_in": null,
      "session": "none",
      "session_end": null
    }
  ]
}
```

**Error (400 Bad Request):**
```json
{
  "message": "A VRN is required."
}
```

#### Session Status Values
- `partial` - Vehicle is currently within the parking window
- `full` - Vehicle's parking session has expired
- `none` - No parking record found for the VRM

### Production Fixture Data

+----+----------+---------------------+
| id | vrm      | time_in             |
+----+----------+---------------------+
|  1 | MA06 GLQ | 2025-11-11 19:46:00 |
|  2 | MA25 KHT | 2025-11-11 04:23:00 |
|  3 | MA15 BSL | 2025-11-11 19:55:00 |
|  4 | MA04 TVZ | 2025-11-11 00:30:00 |
|  5 | MA10 JPJ | 2025-11-11 23:23:00 |
|  6 | MA93 GEG | 2025-11-11 05:32:00 |
|  7 | MA16 GXX | 2025-11-11 15:49:00 |
|  8 | MA94 TEJ | 2025-11-11 17:16:00 |
|  9 | MA97 GRA | 2025-11-11 06:07:00 |
| 10 | MA01 POV | 2025-11-11 07:28:00 |
| 11 | MA04 OCM | 2025-11-11 06:45:00 |
| 12 | MA06 MWU | 2025-11-11 10:25:00 |
| 13 | MA22 MUZ | 2025-11-11 02:56:00 |
| 14 | MA19 ZZW | 2025-11-11 11:25:00 |
| 15 | MA90 AMH | 2025-11-11 15:37:00 |
| 16 | MA94 IWT | 2025-11-11 22:11:00 |
| 17 | MA13 NFV | 2025-11-11 12:07:00 |
| 18 | MA10 VQY | 2025-11-11 00:29:00 |
| 19 | MA97 PPO | 2025-11-11 15:56:00 |
| 20 | MA92 KKE | 2025-11-11 01:32:00 |
+----+----------+---------------------+

## Database

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

## Use Cases

As a traffic warden managing a lot with the default 2 hour parking window, I manually input a VRM mark into the system, I want to know if the vehicle's session has expired or not.

The system uses the current date time and a 2 hour window as defaults, requiring a minimum of the VRM mark. The response indicates if the session is expired ('full') or if no match can be found ('none') or if the session is still open ('partial').

--

As a traffic warden managing a lot with the default 2 hour parking window, I manually input a VRM mark into the system. The license plate is dirty and an 'O' looks like a '0'. I want to know if the vehicle's session has expired or not and wish to manually verify the plate.

The system uses fuzzy matching to look up similar values. The response includes the VRM value captured for manual verification.

--

As a traffic warden managing a lot with the default 2 hour parking window, I manually input a VRM mark into the system. The driver entered at a weird angle in bad light and the camera only captured a partial. I want to know if the vehicle's session has expired or not and wish to manually verify the plate.

The system uses wildcard matching to look up partial values. The response includes the VRM value captured for manual verification.

--

As a traffic warden managing a lot with user-set parking session times, I want to know if the vehicle's session has expired or not. 

I manually input a VRM mark into the system as well as the session duration the driver has paid for. 

The system accepts a parameter for a custom parking window. 

--

As a traffic warden managing a lot, a customer is challenging a ticket. I need to be able to query a specific date to see if the session was full or not.

The system accepts a parameter for a custom datetime. 

--

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

### 1. Initial Setup (`187b79b`)
- Created minimal Symfony 7.3.x framework
- Installed Doctrine ORM for database management (`8f7a74b`)

### 2. Data Modeling (`749373b`)
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

### 3. Basic API Implementation (`7edaf93`, `f0510e6`)
- Created `SearchController` with `/search` endpoint
- Implemented initial plate search functionality in `VehicleRepository`
- Resources consulted:
  - [Postman Symfony API guide](https://quickstarts.postman.com/guide/php-symfony-API/index.html?index=..%2F..index#3)
  - [Symfony Doctrine SQL querying](https://symfony.com/doc/current/doctrine.html#querying-with-sql)
  - [Symfony query parameter mapping](https://symfony.com/blog/new-in-symfony-6-3-query-parameters-mapper)
  - [StackOverflow](https://stackoverflow.com/a/70211792) for calling Repository methods in controllers
- Smoke tested with Postman

### 4. Testing Infrastructure (`0a08148`)
- Installed API Platform and test dependencies:
  - `composer require --dev symfony/test-pack`
  - `composer require api-platform/api-pack`
  - `composer require --dev foundry orm-fixtures`
- Created `VehicleSearchTest` API test suite
- Configured Docker database permissions for test database (used Claude AI for SQL GRANT configuration)
- Fixed deprecation warnings by adding `protected static ?bool $alwaysBootKernel = true;`
- Configured PHPStorm test runner with KERNEL_CLASS (`cfefea7`)
- Implemented setUp/tearDown methods for test isolation
- Resources consulted:
  - [Symfony JSON responses](https://symfony.com/doc/current/components/http_foundation.html#creating-a-json-response)
  - [Symfony testing documentation](https://symfony.com/doc/current/testing.html)
  - [API Platform testing guide](https://api-platform.com/docs/symfony/testing/#creating-data-fixtures)

### 5. Fuzzy Search Implementation (`7b00104`, `0455d40`)
- Implemented SOUNDEX for fuzzy matching (handles OCR errors: I/1, O/0, dirty characters)
- Combined SOUNDEX with partial LIKE matching using UNION query with DISTINCT
- Added reverse chronological ordering
- Lessons learned: SOUNDEX algorithm limitations with partial matches
- AI assistance: Query syntax for UNION ordering

### 6. Date/Time Window Functionality (`e2ac708` → `b27f1a0`)
**Phase 1: Initial date filtering (`e2ac708`)**
- Added date range parameters to filter query results
- Resolved Doctrine DateTimeImmutable string conversion issues

**Phase 2: Dynamic test dates (`1e2d6ad`)**
- Made test dates dynamic for future-proof testing
- AI assistance: DateTime property initialization syntax

**Phase 3: Session validation logic (`812469e`)**
- Moved date validation from query to controller
- Changed API response from "safe/unsafe" to session-based model
- Added `expired` boolean and `expiration_time` fields
- AI assistance: DateInterval/timedelta calculations

**Phase 4: API simplification (`b27f1a0`)**
- Refactored parameters: `date_start`/`date_end` → `datetime` + `window` (minutes)
- Implemented DateInterval for parking window: `new \DateInterval('PT' . $window . 'M');`
- Simplified date calculations

### 7. Refinement Based on Requirements (`dcf60fb` → `c53293c`)
**Code quality (`dcf60fb`, `e63808d`, `d8dda0c`, `88a9750`)**
- Formatted codebase, standardized variable naming
- Added .gitignore for Mac/IDE files
- Documented database setup and test procedures in README

**Datetime validation (`8ad925e`, `57df273`, `ad0248e`)**
- Replaced regex validation with `DateTime::createFromFormat()` (AI recommendation)
- Implemented `isValidDateTime()` helper method
- Added comprehensive datetime format tests

**Window validation (`1a492c6`)**
- Changed `window` parameter from int to string with validation
- Added type checking before casting to DateInterval
- AI assistance: Identified type requirement issue

**Terminology refactor (`92e010e`, `e2103f4`)**
- Renamed "license plate" → "VRM" (Vehicle Registration Mark) throughout codebase
- Updated all tests and documentation

**Session status refactor (`d4fbbff`, `4f74591`)**
- Changed `expired` boolean → `session` enum: "partial"/"full"/"none"
- Changed 404 response → 200 with `session: none` for better UX

**Error handling (`3b70cc9`)**
- Added structured logging for DateTimeImmutable parsing exceptions
- AI assistance: Improved error output structure

**Query refactoring (`dc58777`, `e7530a8`)**
- Rewrote SQL query using HEREDOC for readability (AI suggested multiple options)
- Applied PHPStorm refactoring suggestions

### 8. Query Time Range Feature (`5107364`, `c53293c`)
- Added `query_from` and `query_to` optional parameters
- Implemented validation:
  - Both parameters required together
  - `query_from` must be ≤ `query_to`
  - `datetime` must fall within query range
- Updated repository and SQL query to support conditional date filtering
- Updated tests for new parameters (`057d802`)

### 9. Documentation (`057d802`, `5f8eb28`)
- Removed outdated search method references
- Added comprehensive API documentation with examples
- Documented all query parameters, response formats, and session statuses

### 10. Partials and Test Refactoring (`0334baf2`, `0266ce5`)
- Added use cases to readme as full scenarios
- The scenario for partials didn't actually match my test: revised test and updated the query so that if a partial VRM is recorded and a full VRM is searched for, responses are returned
- If tests are run out of order they fail: revised setup and tear down from class based to test based so each test can be run in isolation
- AI assistance: Explained `LIKE` query order in/out, Directed AI agent that I wanted to change setupBeforeClass and tearDownAfterClass to do that for each test individually 

### Outstanding Items
- [ ] Deploy for review
- [ ] Timezone handling for datetime parameters in query (everything currently works so long as all the dates use the same timezone as the system timezone - but that means converting the datetime before submitting it)
- [ ] `testSameCarTwiceWithinWindow()` illustrates someone trying to hack things by leaving and returning within the same  time frame; check each time_in against the previous in the loop, if it is less than the window duration, create an adjusted_time in that matches the original before evaluating the session completeness
- [ ] `testSimilarPartialVRMRecorded()` illustrates the limitations of the `SOUNDS LIKE` and `LIKE` matching queries. We probably need to implement a Levenshtein distance algorithm instead based on my Googling (we used this method for search in ElasticSearch at LeadMedia to good effect)
- [ ] Test at scale: how usable is the response if you have a busy lot or a lot of vehicles? Are we returning too many results now with our fuzzy matching; do we need to implement Levenshtein sooner than later? Do we need more complex ranking of the response that isn't just closest to the date but best match?
