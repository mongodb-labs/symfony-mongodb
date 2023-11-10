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
use MongoDB\Bundle\Tests\TestApplication\Controller\AutowireClientController;

/** @covers \MongoDB\Bundle\Attribute\AutowireClient */
final class AutowireClientTest extends FunctionalTestCase
{
    /** @dataProvider autowireClientProvider */
    public function testAutowireClientAttribute(string $url, string $client, string $database, string $collection): void
    {
        $this->assertNoDocuments($client, $database, $collection);

        $this->browser()
            ->get($url)
            ->assertSuccessful();

        $this->assertNumberOfDocuments(1, $client, $database, $collection);
    }

    /** @return Generator<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function autowireClientProvider(): iterable
    {
        /** @see AutowireClientController::viaAlias() */
        yield 'via-alias' => ['/autowire-client/via-alias', self::CLIENT_ID_SECONDARY, self::DB_CUSTOMER_GOOGLE, self::COLLECTION_USERS];

        /** @see AutowireClientController::withoutArguments() */
        yield 'without-arguments' => ['/autowire-client/without-arguments', self::CLIENT_ID_PRIMARY, self::DB_CUSTOMER_GOOGLE, self::COLLECTION_USERS];

        /** @see AutowireClientController::withCustomClient() */
        yield 'with-custom-client' => ['/autowire-client/with-custom-client', self::CLIENT_ID_SECONDARY, self::DB_CUSTOMER_GOOGLE, self::COLLECTION_USERS];

        /** @see AutowireClientController::viaNamedClient() */
        yield 'via-named-client' => ['/autowire-client/via-named-client', self::CLIENT_ID_SECONDARY, self::DB_CUSTOMER_GOOGLE, self::COLLECTION_USERS];
    }

    /**
     * @see AutowireClientController::withUnknownClient()
     *
     * @testdox Uses #[AutowireClient] attribute with an unknown client.
     */
    public function testWithUnknownClient(): void
    {
        $this->browser()
            ->catchExceptions()
            ->get('/autowire-client/with-unknown-client')
            ->assertStatus(500);
    }
}
