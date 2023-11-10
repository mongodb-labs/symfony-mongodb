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

namespace MongoDB\Bundle\Tests\Functional\Attribute;

use Generator;
use MongoDB\Bundle\Tests\Functional\FunctionalTestCase;
use MongoDB\Bundle\Tests\TestApplication\Controller\AutowireCollectionController;

/** @covers \MongoDB\Bundle\Attribute\AutowireCollection */
final class AutowireCollectionTest extends FunctionalTestCase
{
    /** @dataProvider autowireCollectionProvider */
    public function testAutowireCollectionAttribute(string $url, string $client, string $database, string $collection): void
    {
        $this->assertNoDocuments($client, $database, $collection);

        $this->browser()
            ->get($url)
            ->assertSuccessful();

        $this->assertNumberOfDocuments(1, $client, $database, $collection);
    }

    /** @return Generator<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function autowireCollectionProvider(): iterable
    {
        /** @see AutowireCollectionController::withoutArguments() */
        yield 'without-arguments' => ['/autowire-collection/without-arguments', self::CLIENT_ID_PRIMARY, self::DB_CUSTOMER_GOOGLE, self::COLLECTION_USERS];

        /** @see AutowireCollectionController::withCollectionOption() */
        yield 'with-collection-option' => ['/autowire-collection/with-collection-option', self::CLIENT_ID_PRIMARY, self::DB_CUSTOMER_GOOGLE, self::COLLECTION_USERS];

        /** @see AutowireCollectionController::withDatabaseAndClientOption() */
        yield 'with-database-and-client-option' => ['/autowire-collection/with-database-and-client-option', self::CLIENT_ID_SECONDARY, self::DB_CUSTOMER_AZURE, self::COLLECTION_USERS];
    }
}
