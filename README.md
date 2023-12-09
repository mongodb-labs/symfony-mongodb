# Symfony Bundle for MongoDB (EXPERIMENTAL)

This bundle provides integration of the [MongoDB library](https://github.com/mongodb/mongo-php-library)
into Symfony. It is designed as a lightweight alternative to the [Doctrine MongoDB ODM](https://github.com/doctrine/mongodb-odm),
providing only an integration to interact with MongoDB without providing all features of an ODM.

## Installation

Install the bundle with composer:

```bash
composer require mongodb/symfony-bundle
```

If you have `symfony/flex` installed, the bundle is automatically enabled.
Otherwise, you need to enable it manually by adding it to the `bundles.php` file:

```php
// config/bundles.php

<?php

return [
    // ...
    MongoDB\Bundle\MongoDBBundle::class => ['all' => true],
];
```

## Configuration

Configuration is done in the `config/packages/mongodb.yaml` file. To get started, you need to configure at least one
client:

```yaml
# config/packages/mongodb.yaml
mongodb:
  clients:
    default: 'mongodb://localhost:27017'
```

The `id` is used to reference the client in the service container. The `uri` is the connection string to connect to. For
security reasons, it is recommended to read this value from your local environment and referencing it through an
environment variable in the container:

```yaml
mongodb:
  clients:
    default: '%env(MONGODB_URI)%'
```

You can also specify additional options for the client:

```yaml
mongodb:
  clients:
    default:
      uri: '%env(MONGODB_URI)%'
      default_database: #...
      uri_options: #...
      driver_options: #...
```

The `uri_options` and `driver_options` are passed directly to the underlying MongoDB driver.
See the [documentation](https://www.php.net/manual/en/mongodb-driver-manager.construct.php) for available options.

If you want to configure multiple clients, you can do so by adding additional clients to the configuration:

```yaml
mongodb:
  default_client: default
  clients:
    default: '%env(MONGODB_URI)%'
    second: '%env(SECOND_MONGODB_URI)%'
```
> [!NOTE]
> If you add multiple clients, you need to specify the `default_client` option to specify which
> client should be used as default!

## Client Usage

For each client, a service is registered in the container with the `mongodb.client.{id}` service id.

> [!NOTE]
> The MongoDB driver only establishes a connection to MongoDB when it is actually needed, so a
> client can be injected into services without causing network traffic.

With autowiring enabled, you can inject the client into your services like this:

```php
use MongoDB\Client;

class MyService
{
    public function __construct(
        private Client $client,
    ) {}
}
```

If you register multiple clients, you can autowire them by using the client name with a `Client` suffix as parameter
name:

```php
use MongoDB\Bundle\Attribute\AutowireClient;
use MongoDB\Client;

class MyService
{
    public function __construct(
        // Will autowire the client with the id "second"
        private Client $secondClient,
    ) {}
}
```

or by using the `#[AutowireClient]` attribute:

```php
use MongoDB\Bundle\Attribute\AutowireClient;
use MongoDB\Client;

class MyService
{
    public function __construct(
        #[AutowireClient('second')]
        private Client $client,
    ) {}
}
```

## Database Usage

The client service provides access to databases and collections. You can access a database by calling the
`selectDatabase` method, passing the database name and potential options:

```php
use MongoDB\Client;
use MongoDB\Database;

class MyService
{
    private Database $database;

    public function __construct(
        Client $client,
    ) {
        $this->database = $client->selectDatabase('myDatabase');
    }
}
```

An alternative to this is using the `#[AutowireDatabase]` attribute, referencing the database name:

```php
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Database;

class MyService
{
    public function __construct(
        #[AutowireDatabase('myDatabase')]
        private Database $database,
    ) {}
}
```

If you don't specify a database name in the attribute, the default database name (specified in the `default_database`
configuration option) will be used. If you did not define a default database, the database name has to be specified in
the attribute.

If you have more than one client defined, you can also reference the client:

```php
use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Database;

class MyService
{
    public function __construct(
        #[AutowireDatabase(database: 'myDatabase', client: 'second')]
        private Database $database,
    ) {}
}
```

## Collection Usage

To inject a collection, you can either call the `selectCollection` method on a `Client` or `Database` instance.
For convenience, the `#[AutowireCollection]` attribute provides a quicker alternative:

```php
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Collection;

class MyService
{
    public function __construct(
        #[AutowireCollection(
            database: 'myDatabase',
            collection: 'myCollection'
        )]
        private Collection $collection,
    ) {}
}
```

You can also omit the `collection` option if the property name matches the collection name.
In the following example the collection name is `myCollection`, inferred from the property name:

```php
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Collection;

class MyService
{
    public function __construct(
        #[AutowireCollection(
            database: 'myDatabase',
        )]
        private Collection $myCollection,
    ) {}
}
```

If you have more than one client defined, you can also reference the client:

```php
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Collection;

class MyService
{
    public function __construct(
        #[AutowireCollection(
            database: 'myDatabase',
            client: 'second',
        )]
        private Collection $myCollection,
    ) {}
}
```

By specifiying the `default_database` option in the configuration, you can omit the `database` option in the
`AutowireCollection` attribute:

```diff
mongodb:
  clients:
    default:
      uri: '%env(MONGODB_URI)%'
+      default_database: 'myDatabase'
```

```php
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Collection;

class MyService
{
    public function __construct(
        #[AutowireCollection]
        private Collection $myCollection,
    ) {}
}
```

## Specifying options

When using the `AutowireDatabase` or `AutowireCollection` attributes, you can specify additional options for the
resulting instances. You can pass the following options:
|| Option || Accepted type ||
| `codec` | `DocumentCodec` instance |
| `typeMap`| `array` containing type map information |
| `readPreference` | `MongoDB\Driver\ReadPreference` instance |
| `writeConcern` | `MongoDB\Driver\writeConcern` instance |
| `readConcern` | `MongoDB\Driver\ReadConcern` instance |

In addition to passing an instance, you can also pass a service reference by specifying a string for the given option:

```php
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Collection;
use MongoDB\Driver\ReadPreference;

class MyService
{
    public function __construct(
        #[AutowireCollection(codec: Codec::class, readPreference: new ReadPreference('secondary'))]
        private Collection $myCollection,
    ) {}
}
```
