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

use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Bundle\Tests\Functional\Attribute\AutowireCollectionTest;
use MongoDB\Bundle\Tests\Functional\FunctionalTestCase;
use MongoDB\Collection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/** @see AutowireCollectionTest */
#[Route('/autowire-collection')]
final class AutowireCollectionController extends AbstractMongoDBController
{
    #[Route('/without-arguments')]
    public function withoutArguments(
        #[AutowireCollection]
        Collection $users,
    ): JsonResponse {
        $this->insertDocumentForCollection($users);

        return new JsonResponse();
    }

    #[Route('/with-collection-option')]
    public function withCollectionOption(
        #[AutowireCollection(collection: FunctionalTestCase::COLLECTION_USERS)]
        Collection $collection,
    ): JsonResponse {
        $this->insertDocumentForCollection($collection);

        return new JsonResponse();
    }

    #[Route('/with-database-and-client-option')]
    public function withDatabaseAndClientOption(
        #[AutowireCollection(database: FunctionalTestCase::DB_CUSTOMER_AZURE, client: FunctionalTestCase::CLIENT_ID_SECONDARY)]
        Collection $users,
    ): JsonResponse {
        $this->insertDocumentForClient(FunctionalTestCase::CLIENT_ID_SECONDARY, FunctionalTestCase::DB_CUSTOMER_AZURE, $users);

        return new JsonResponse();
    }
}
