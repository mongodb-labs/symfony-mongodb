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

namespace MongoDB\Bundle\Tests\TestApplication\Controller;

use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Database;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

use function assert;
use function is_string;

/**
 * Provides shortcuts for MongoDB related features.
 */
abstract class AbstractMongoDBController extends AbstractController
{
    /** @var Client[] */
    private array $clients;

    /**
     * We need to do this, because when we only have the collection in tests for `#[AutowireCollection]`
     * we don't get the information about which client was used.
     */
    public function __construct(
        Client $primaryClient,
        Client $secondaryClient,
    ) {
        $this->clients = [
            'primary' => $primaryClient,
            'secondary' => $secondaryClient,
        ];
    }

    final public function insertDocumentForClient(Client|string $client, ?string $database, Collection|string $collection): void
    {
        if (is_string($client)) {
            $client = $this->clients[$client];
        }

        assert($client instanceof Client);

        if (
            $database === null
            && $collection instanceof Collection
        ) {
            $database = $collection->getDatabaseName();
        }

        assert($database !== null);

        $db = $client->selectDatabase($database);
        $db->drop();

        if (! $collection instanceof Collection) {
            $collection = $db->selectCollection($collection);
        }

        $collection->insertOne(['foo' => 'bar']);
    }

    final public function insertDocumentForDatabase(Database $database, string $collection): void
    {
        $database->drop();
        $collection = $database->selectCollection($collection);
        $collection->insertOne(['foo' => 'bar']);
    }

    final public function insertDocumentForCollection(Collection $collection): void
    {
        $collection->drop();
        $collection->insertOne(['foo' => 'bar']);
    }
}
