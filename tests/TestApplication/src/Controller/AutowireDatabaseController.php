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

use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Bundle\Tests\Functional\Attribute\AutowireClientTest;
use MongoDB\Bundle\Tests\Functional\Attribute\AutowireDatabaseTest;
use MongoDB\Bundle\Tests\Functional\FunctionalTestCase;
use MongoDB\Database;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/** @see AutowireDatabaseTest */
#[Route('/autowire-database')]
final class AutowireDatabaseController extends AbstractMongoDBController
{
    /** @see AutowireDatabaseTest::testWithoutArguments() */
    #[Route('/without-arguments')]
    public function withoutArguments(
        #[AutowireDatabase]
        Database $google,
    ): JsonResponse {
        $this->insertDocumentForDatabase($google, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }

    /** @see AutowireClientTest::testWithCustomClientSetViaOptions() */
    #[Route('/with-custom-client')]
    public function withCustomClient(
        #[AutowireDatabase(database: 'google', client: FunctionalTestCase::CLIENT_ID_SECONDARY)]
        Database $database,
    ): JsonResponse {
        $this->insertDocumentForDatabase($database, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }

    /** @see AutowireClientTest::testWithCustomClientSetViaOptions() */
    #[Route('/with-custom-database')]
    public function withCustomDatabase(
        #[AutowireDatabase(database: FunctionalTestCase::DB_CUSTOMER_AZURE)]
        Database $unknown,
    ): JsonResponse {
        $this->insertDocumentForDatabase($unknown, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }
}
