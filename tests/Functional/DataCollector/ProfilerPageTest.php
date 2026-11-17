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

namespace MongoDB\Bundle\Tests\Functional\DataCollector;

use MongoDB\Bundle\DataCollector\MongoDBDataCollector;
use MongoDB\Bundle\Tests\Functional\FunctionalTestCase;

final class ProfilerPageTest extends FunctionalTestCase
{
    public function testProfilerPanelShowsCursorStatistics(): void
    {
        $client = self::createClient();
        $client->enableProfiler();

        $client->request('GET', '/profiler/cursor-iteration');
        $this->assertResponseIsSuccessful();

        $profile = $client->getProfile();
        $this->assertNotFalse($profile);

        $collector = $profile->getCollector('mongodb');
        $this->assertInstanceOf(MongoDBDataCollector::class, $collector);

        $getMoreCount = null;
        foreach ($collector->getRequests() as $requests) {
            foreach ($requests as $request) {
                if (($request['getMoreCount'] ?? 0) > 0) {
                    $getMoreCount = $request['getMoreCount'];
                }
            }
        }

        $this->assertNotNull($getMoreCount, 'The profiler did not collect any getMore command.');
        $this->assertSame($getMoreCount, $collector->getGetMoreCount());

        $client->request('GET', '/_profiler/' . $profile->getToken() . '?panel=mongodb');

        $this->assertResponseIsSuccessful();

        $content = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString($getMoreCount . ' getMore command', $content);
        $this->assertStringContainsString('View getMore commands', $content);
    }
}
