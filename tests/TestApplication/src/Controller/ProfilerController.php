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
use MongoDB\Bundle\Tests\Functional\DataCollector\ProfilerPageTest;
use MongoDB\Client;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

use function count;

/** @see ProfilerPageTest */
#[Route('/profiler')]
final class ProfilerController
{
    #[Route('/cursor-iteration')]
    public function cursorIteration(
        #[AutowireClient]
        Client $client,
    ): JsonResponse {
        $collection = $client->selectCollection('profiler', 'cursor');
        $collection->drop();
        $collection->insertMany([['value' => 1], ['value' => 2], ['value' => 3]]);

        // Fetch each document in its own batch to trigger getMore commands
        $documents = $collection->find([], ['batchSize' => 1])->toArray();

        return new JsonResponse(['count' => count($documents)]);
    }
}
