<?php

declare(strict_types=1);

/**
 * Copyright 2023-present MongoDB, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace MongoDB\Bundle\Tests\Functional;

use MongoDB\Bundle\DependencyInjection\MongoDBExtension;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Browser\Test\HasBrowser;

use function assert;

class FunctionalTestCase extends WebTestCase
{
    use HasBrowser;

    public const CLIENT_ID_PRIMARY = 'primary';
    public const CLIENT_ID_SECONDARY = 'secondary';
    public const DB_CUSTOMER_GOOGLE = 'google';
    public const DB_CUSTOMER_AZURE = 'azure';
    public const COLLECTION_USERS = 'users';
    public const COLLECTION_FILES = 'files';
    private const CLIENTS = [
        self::CLIENT_ID_PRIMARY,
        self::CLIENT_ID_SECONDARY,
    ];
    private const DATABASES = [
        self::DB_CUSTOMER_GOOGLE,
        self::DB_CUSTOMER_AZURE,
    ];

    protected function tearDown(): void
    {
        foreach (self::CLIENTS as $client) {
            foreach (self::DATABASES as $database) {
                $this->dropDatabase($client, $database);
            }
        }

        parent::tearDown();
    }

    public function assertNoDocuments(string $clientId, string $database, string $collection): void
    {
        $this->assertNumberOfDocuments(0, $clientId, $database, $collection);
    }

    public function assertNumberOfDocuments(int $expected, string $clientId, string $database, string $collection): void
    {
        $client = self::getContainer()->get(MongoDBExtension::createClientServiceId($clientId));
        assert($client instanceof Client);
        $db = $client->selectDatabase($database);
        assert($db instanceof Database);
        $collection = $db->selectCollection($collection);
        assert($collection instanceof Collection);

        $this->assertSame($expected, $collection->countDocuments());
    }

    private function dropDatabase(string $clientId, string $database): void
    {
        $client = self::getContainer()->get(MongoDBExtension::createClientServiceId($clientId));
        assert($client instanceof Client);
        $db = $client->selectDatabase($database);
        assert($db instanceof Database);
        $db->drop();
    }
}
