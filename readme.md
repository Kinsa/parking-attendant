# Parking Attendant

This project contains a means of tracking vehicle entries into a parking lot and assessing whether they have overstayed their session.

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

### GET /api

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
curl "http://localhost:8000/api?vrm=AB12CDE"

# Search with custom datetime and window
curl "http://localhost:8000/api?vrm=AB12CDE&query_to=2024-11-13 14:30:00&window=180"

# Search within a specific time range
curl "http://localhost:8000/api?vrm=AB12CDE&query_from=2024-11-13 10:00:00&query_to=2024-11-13 16:00:00"
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
- If tests are run out of order, they fail: revised setup and tear down from class based to test based so each test can be run in isolation
- AI assistance: Explained `LIKE` query order in/out, Directed AI agent that I wanted to change setupBeforeClass and tearDownAfterClass to do that for each test individually 

### 11. Bugfix 
- In testing the use cases against the database fixture, I ran into an unexpected result. I was seeing a partial session when I was expecting to see a full session when comparing across days. Initially I thought this was an issue with date comparisons. A string instead of a datetime object. Reviewing the code though that wasn't it. I added a failing test case and working back and forth with AI debugged it to a bad comparison. When I refactored from the date range determining the window, I was checking if they had parked before the window began, AI suggested that what I should really be checking is if they were still there after the session ended.
- AI assistance: debugging; suggested refactor

### 12. Levenshtein distance (`1c30b35f`)
- In testing the use cases against the database fixture, I discovered that `SOUNDS LIKE`, as the name implies, is auditory. Q and O don't sound anything like each other but a bit of mud on an O makes it a Q, or vice versa. I found https://lucidar.me/en/web-dev/levenshtein-distance-in-mysql/ and added the levenshtein function to my database. I then refactored the SQL query to utilise that instead of `SOUNDS LIKE`. This allowed me to enable the `testSimilarPartialVRMRecorded()` test which now passes. With a distance of 4, all tests passed, but the results were too dissimilar. I limited it to 3 which required me to disable the test that swapped an 8 and a B. Further tuning might be in order and likely regex matching (O/0, I/1, O/Q, B/8, etc.). It also returns too many responses with the right response sometimes buried.

### 13. Regex matching (`10d5de0f`, ``)
- To get around the issue of too many responses with the Levenshtein method, replaced it with an exact match with pattern recognition and then Levenshtein for any entries less than 9 digits using where clause; remove distance in response
- Remove wildcard support and related tests—I had this backwards to the scenario - unless we are using OCR for issuing tickets as well in which case it is still valid
- My initial strategy for regex was to use REPLACE to chain multiple REGEX matches, referencing https://www.devart.com/dbforge/mysql/studio/mysql-replace.html#:~:text=The%20REPLACE()%20function%20in,records%20with%20just%20one%20command, https://www.datacamp.com/doc/mysql/mysql-regexp, and https://stackoverflow.com/questions/5460364/mysql-multiple-replace-query
- AI assistance: Asked, "I have a database of vehicle registration marks captured by camera and processed by software so the value is stored. Sometimes the software mistakes an O for a 0 or Q or any combination there of or an 8 for a B or an I and a 1. The data is stored in MySQL and I am querying it with PHP. How can I most efficiently write a query that replaces the confusing characters with a regex that allows for the other possibilities." That got me to the code at `10d5de0f` which used PHP str_replace to build things out. Easy enough but the regex didn't work, it was modifying as it went so the loop was trying to change replacements already made. 
- AI assistance: How to get around the nested replacement issue? Two passes: one to replace the exact char with a really specific placeholder that won't exist in the code, then replace that with the regex pattern. At the same time it spotted some other issues in the code - case, the way spaces were being handled in the regex, and passing query_from and query_to along when we weren't using them
- AI assistance: With that change I had 3 failing tests—two of those had to do with wildcard support and one with a character change of 4 to A. When adding 4/A to the pattern resulted in all the tests failing, I had to dig in again. AI suggested I was getting too many matches - the opposite of what I was seeing - but also suggested that I was making up confusions—OCR doesn't often confuse A and 4. https://communityhistoryarchives.com/100-common-ocr-letter-misinterpretations/ disagrees but there's no date on that or reference to the specific processing being used, and to really know we'd have to test our own OCR reader. AI also broke down the number of Levenshtein steps from 4 to A as just 1 so we could tune when we want to use Levenshtein to address the problem if we wanted.
- I'm actually getting more results now with the regex pattern substitution.

### 14. Address too many results
- If there is an exact match and the session is partial, return immediately instead of returning the full result set
- Simplify the query—remove datetime and leave query_from and query_to
- Revised the results to order by Levenshtein distance
- AI assistance: I did this yesterday but didn't get the sequence right. After digging around in Git logs I got close but not quite. I provided the SQL query to AI and asked it to correct the query.  

### Outstanding Items
- [ ] Timezone handling for datetime parameters in query (everything currently works so long as all the dates use the same timezone as the system timezone - but that means converting the datetime before submitting it)
