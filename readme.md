This project contains a means of tracking vehicle entries into a parking lot and querying that data:


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

## Steps taken

The Symfony project was initially configured to build the minimal framework via `symfony new my_project_directory --version="7.3.x"`.

### Modeling

Doctrine was added [following the directions](https://symfony.com/doc/current/doctrine.html) and an Entity class for Vehicle was setup in the database. As an MVP it contains fields for `id`, `license_plate` (string), and `time_in` (immutable datetime) (I initially called it Vehicles, mixing up my singular/plural best practices; I then refactored to make it singular)

#### Fixture

For sample data the [DoctrineFixturesBundle](https://symfony.com/bundles/DoctrineFixturesBundle/current/index.html) package was added and a fixture was setup to mock some entries

I looked up the date format for license plates in the UK on Google

I looked up the PHP way to get the last 2 chars of a string (`[-2:]` in python) in GitHub Copilot chat

I looked up the PHP way to get a random character between A and Z [on StackOverflow](https://stackoverflow.com/a/31441519)

I looked up [the PHP DateTime class](https://www.php.net/manual/en/datetime.modify.php) to see how I might modify the date back a day

I looked up creating a random time for a date [on StackOverflow](https://stackoverflow.com/a/58259320)

I loaded that fixture with `php bin/console doctrine:fixtures:load` and verified in PHPMyAdmin that I had 20 vehicles with random license plates and timestamps from yesterday

### The API

I Googled creating a REST API in Symfony knowing I would need a controller and routes and found https://quickstarts.postman.com/guide/php-symfony-API/index.html?index=..%2F..index#3

I smoke tested that by running the server and loading the URL in Postman

I then dug into the documentation on Doctrine until I found [Querying with SQL](https://symfony.com/doc/current/doctrine.html#querying-with-sql)

And revising my controller to allow query strings: https://symfony.com/blog/new-in-symfony-6-3-query-parameters-mapper, https://symfony.com/doc/current/controller.html#mapping-query-parameters-individually

And then I had to figure out how to call my Registry method in my controller: https://stackoverflow.com/a/70211792

And smoke tested it again in Postman

Which seemed like a good time to add some programmatic tests before getting into fuzzy matching with [SOUNDEX()](https://stackoverflow.com/questions/369755/how-do-i-do-a-fuzzy-match-of-company-names-in-mysql-for-auto-complete)

I ran `symfony console make:test` to create a test and chose API Test from the menu. The scaffolding code referenced JSON and knowing I needed to go there anyway, I Googled how to encode a JSON response and found https://symfony.com/doc/current/components/http_foundation.html#creating-a-json-response

I then tried to run them and realised I needed to install the Test package: `composer require --dev symfony/test-pack` per https://symfony.com/doc/current/testing.html

And I needed to modify my docker config. The error I was getting indicated that my user didn't have privileges for the test database. I knew I needed to GRANT ALL on * but wasn't sure how to do that with docker so I provided the docker-compose file to Claude and asked it how I would modify it so that my user had access to all databases. It then showed me how to create a SQL file to be run when bringing up the container to run the GRANT command

I then had to remigrate and re load the fixture:

```bash
# sh
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load
```

I then was able to create the database and tables with the commands from https://symfony.com/doc/current/testing.html and re-run `./bin/phpunit`

My test wasn't running though. I realised the package for ApiTest wasn't installed even though I had installed the testing package. After Googling a bit, I asked Claude where it came from and it pointed me to `composer require api-platform/api-pack` which lined up to what I had been seeing about assertJsonContains() in the google search results. I installed that and got tests to run

I had to ask Claude how to fix the deprecation error. It added `protected static ?bool $alwaysBootKernel = true;`

With that in place I was able to add tests for a bad request (lacking the plate query parameter) and a plate not found (passing a plate not in the database - easy since at this point nothing is in the database). 

To test an okay response, I needed to populate the test database with a fixture via a test factory. Going back to the API Pack tutorial: Use the Factory to make test data (tutorial at https://api-platform.com/docs/symfony/testing/#creating-data-fixtures):

Installed `composer require --dev foundry orm-fixtures`

Created a factory: `bin/console make:factory 'App\Entity\Vehicle'` which  created: `src/Factory/VehicleFactory.php`

Realised at this point I was getting ahead of myself, while I will need a factory eventually, what I need right now is a single entry. Went back and added a setUp method to the test to do so. I went back to the entity docs to review creating an object in the ORM: https://symfony.com/doc/current/doctrine.html#persisting-objects-to-the-database and I refreshed via AI how to create a datetime from a string in PHP. GitHub autopilot autocomplete filled in `$entityManager = self::getContainer()->get('doctrine')->getManager();` as I was trying to work out how to initiate the correct object.

I could see in the database that the test vehicle was getting added but not getting removed so I added a tear down method as well to clean the database.

Continuing to debug, I found some syntax errors. I finally asked AI for help:

"does this tear down method run after my test? i'm getting no results found with it in place" and "i have set up and tear down methods. the setup method runs before each test. how can i have it run once before the test suite? how can i run teh tear down method after the test suite."

It then made some changes.

With a bit of fine tuning of the message I have passing tests.

I then worked out the syntax for asserting the response I was expecting. I had to dig into assertJsonContains to figure out the syntax and then from PHPUnit I was expecting to have to have an exact match. Since the ID changes I was getting a failure. I asked AI "this fails because the id changes. can i set a wildcard or just test for license_plate and time_in maybe as 2 separate tests?" and it told me to just remove the `id` parameter. And we once again have passing tests.

Now we are actually programming and not just figuring out config. Modified the SQL query to use soundex which I had researched as the best method given the problem of cameras sometimes changing characters in plates when recording (a bit of dirt on a 4 makes an A, an I and a 1 or O and 0 are too similar). Updated tests and verified this worked as expected. 

This taught me a bit about the SOUNDEX algorithm. My testNoMatchesFound test continued to fail even though it had an exact match for the first set of characters.

Commit at this point: 7b0010468f597f4e2e3f9405bae9ef18d08a0854

Wanting to address this... a partial match should return... I googled combining queries (and refreshed on UNION ALL) and then asked AI to help rewrite the query after manually trying it. 

My SQL was:

```
(SELECT * FROM vehicle v
WHERE v.license_plate SOUNDS LIKE :license_plate)
UNION ALL
(SELECT * FROM vehicle v
WHERE v.license_plate LIKE CONCAT(:license_plate, "%"))
ORDER BY license_plate ASC
```

I asked "i want to combine a soundex search with sounds like and a partial search with like into a single set of results. this code causes an error. is it syntax or concept" and it said "The issue is syntax. When using UNION, you can't reference column aliases from the subqueries in the outer ORDER BY. You need to order within each subquery or use a different approach."

This led to my exact match test failing. After reviewing the output, I saw that the combined results included the plate twice. I needed to add DISTINCT to make it unique.

Commit at this point: 0455d40f796ddc9d28728b55645d478cf013db43

With the plate query working as expected, I set to work on adding in the date.

I used AI to sort out some syntax issues. Doctrine needed the DateTimeImmutable objects to be passed as strings which caused me some confusion since the objects in the database are DateTimeImmutable objects.

I opted to set a default window of 2 hours if none is provided to the query.

Commit at this point: e2ac708a385dfb1b535bea1dddf85f212fa3e965

Now I need to test bigger windows and multiple objects

First I made the date dynamic so the test would pass in the future. I used AI to help with the syntax because initially I was trying to set the datetime in the default value for the class property which was causing an error. 

Commit at this point: 1e2d6ad319fec52dda3dd7385833752ed4619ef5

At this point the API is returning data where we could say "that car is safe" which is sort of the opposite of what we want. What we really want to do is to enter a plate and see a safe or not safe response.

Modified the query to remove the date parameters and moved the validation logic into the controller

Modified the query to return plates in reverse chronological order

Updated the API response to indicate expired as a boolean and expiration_time as a nullable (to handle non-entries) datetime

I had to lookup how to work out the timedelta to figure out the expiration date. I asked AI "how do i find the difference between $date_end and $date_start and the unit so i can apply that to the modify() method of a different date time"

Tests are once again passing; it seems to work with the default values. Next up is to test it with a custom window for example "What cars were in the lot that needed to be ticketed yesterday at 12:10pm

It seems that the API in being refactored could be simplified though. 

Commit at this point: 812469e801369f12e3a490c308bce09a40571184

Date start and date end are now quite confusing. It would make more sense to pass a window in minutes and a date to calculate from. 

I refactored the API to take a datetime object as `at` and a time window in minutes as `window`. I asked AI how to help me with the DateInterval object now that I am not subracting date start from date end to calculate the window which gave me `$parking_window = new \DateInterval('PT' . $window . 'M');` which is feeling similar to Python's time delta.

With that little bit of refactoring, all the tests still pass because I haven't yet tested the custom window parameters.

Commit at this point: b27f1a0b69b056c14fcfb1546a5ffa37c15421ad

### At this point I received the brief

Up until now I was working on what I knew from the call.

Quickly outlining next steps:

- [x] Test the custom window parameters
- [x] Rename 'plate' to 'VRM'
- [ ] Reinstate optional date parameters to limit the queried response to a specific period
  - [x] add query_from and query_to query parameters
  - [x] convert the strings to datetime objects; verify formatting
    - [ ] test
  - [x] verify that datetime is within query_from and query_to or raise an error
    - [ ] test
  - [x] update $matches = $vehicleRepository->findByPlate($vrm); to pass from and to along to the repository
  - [x] update the SQL query to conditionally handle those parameters
- [x] Refactor 'expired' to indicate 'partial session', 'full session', or 'no session' (with 'expired' equaling 'full session' and 'no session' being the no math return)
- [ ] Revisit timezones - datetime query parameter should probably account for this and we need to handle it
- [ ] Document how to use