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

use MongoDB\Bundle\Attribute\AutowireClient;
use MongoDB\Bundle\Tests\Functional\Attribute\AutowireClientTest;
use MongoDB\Bundle\Tests\Functional\FunctionalTestCase;
use MongoDB\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

/** @see AutowireClientTest */
#[Route('/autowire-client')]
final class AutowireClientController extends AbstractMongoDBController
{
    #[Route('/via-alias')]
    public function viaAlias(
        Client $client,
    ): JsonResponse {
        $this->insertDocumentForClient($client, FunctionalTestCase::DB_CUSTOMER_GOOGLE, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }

    #[Route('/without-arguments')]
    public function withoutArguments(
        #[AutowireClient]
        Client $client,
    ): JsonResponse {
        $this->insertDocumentForClient($client, FunctionalTestCase::DB_CUSTOMER_GOOGLE, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }

    #[Route('/with-custom-client')]
    public function withCustomClient(
        #[AutowireClient(client: FunctionalTestCase::CLIENT_ID_SECONDARY)]
        Client $client,
    ): JsonResponse {
        $this->insertDocumentForClient($client, FunctionalTestCase::DB_CUSTOMER_GOOGLE, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }

    #[Route('/via-named-client')]
    public function viaNamedClient(
        Client $secondaryClient,
    ): JsonResponse {
        $this->insertDocumentForClient($secondaryClient, FunctionalTestCase::DB_CUSTOMER_GOOGLE, FunctionalTestCase::COLLECTION_USERS);

        return new JsonResponse();
    }

    #[Route('/with-unknown-client')]
    public function withUnknownClient(
        #[AutowireClient(client: 'foo-bar-baz')]
        Client $client,
    ): JsonResponse {
        return new JsonResponse();
    }
}
