This project contains a means of tracking vehicle entries into a parking lot and querying that data:

- by plate number to see how long the vehicle has been parked
- by a datetime range to see which vehicles were parked within that date time

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

The Symfony project was initially configured to build the minimal framework via `symfony new my_project_directory --version="7.3.x"`.

### Modeling

Doctrine was added [following the directions](https://symfony.com/doc/current/doctrine.html) and an Entity class for Vehicle was setup in the database. As an MVP it contains fields for `id`, `license_plate` (string), and `time_in` (immutable datetime)

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

Which seemed like a good time to add some tests

