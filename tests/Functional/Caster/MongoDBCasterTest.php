<?php

declare(strict_types=1);

/**
 * Copyright 2026-present MongoDB, Inc.
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

namespace MongoDB\Bundle\Tests\Functional\Caster;

use MongoDB\BSON\ObjectId;
use MongoDB\Bundle\Caster\MongoDBCaster;
use MongoDB\Driver\BulkWriteCommand;
use MongoDB\Driver\BulkWriteCommandResult;
use MongoDB\Driver\Command;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Exception\ServerException;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Monitoring\SDAMSubscriber;
use MongoDB\Driver\Monitoring\ServerChangedEvent;
use MongoDB\Driver\Monitoring\ServerClosedEvent;
use MongoDB\Driver\Monitoring\ServerHeartbeatFailedEvent;
use MongoDB\Driver\Monitoring\ServerHeartbeatStartedEvent;
use MongoDB\Driver\Monitoring\ServerHeartbeatSucceededEvent;
use MongoDB\Driver\Monitoring\ServerOpeningEvent;
use MongoDB\Driver\Monitoring\TopologyChangedEvent;
use MongoDB\Driver\Monitoring\TopologyClosedEvent;
use MongoDB\Driver\Monitoring\TopologyOpeningEvent;
use MongoDB\Driver\Query;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\Server;
use MongoDB\Driver\ServerDescription;
use MongoDB\Driver\Session;
use MongoDB\Driver\TopologyDescription;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function class_exists;
use function str_contains;
use function uniqid;

final class MongoDBCasterTest extends TestCase
{
    private Manager $manager;

    protected function setUp(): void
    {
        $this->manager = new Manager($_SERVER['MONGODB_PRIMARY_URL'], [], ['serverSelectionTimeoutMS' => 2000]);
    }

    public function testServer(): void
    {
        $server = $this->manager->selectServer(new ReadPreference('primary'));

        $dump = self::dump($server);

        self::assertStringContainsString(Server::class, $dump);
        self::assertStringContainsString('host: ', $dump);
        self::assertStringContainsString('port: ', $dump);
        self::assertStringContainsString('type: ', $dump);
    }

    public function testManagerWithServers(): void
    {
        $this->manager->executeCommand('admin', new Command(['ping' => 1]));

        $dump = self::dump($this->manager);

        self::assertStringContainsString('readPreference', $dump);
        self::assertStringContainsString('readConcern', $dump);
        self::assertStringContainsString('writeConcern', $dump);
        self::assertStringContainsString('servers', $dump);
        self::assertStringContainsString(Server::class, $dump);
    }

    public function testSession(): void
    {
        $session = $this->manager->startSession();

        $dump = self::dump($session);

        self::assertStringContainsString(Session::class, $dump);
        self::assertStringContainsString('logicalSessionId', $dump);
        self::assertStringContainsString('transactionState: "none"', $dump);

        $session->endSession();
    }

    public function testCursor(): void
    {
        $cursor = $this->manager->executeQuery('test.caster', new Query([]));

        $dump = self::dump($cursor);

        self::assertStringContainsString(Cursor::class, $dump);
        self::assertStringContainsString('id: ', $dump);
        self::assertStringContainsString('server: ', $dump);
        self::assertStringContainsString('isDead: ', $dump);
    }

    public function testTopologyDescription(): void
    {
        $uri = $_SERVER['MONGODB_PRIMARY_URL'] . (str_contains($_SERVER['MONGODB_PRIMARY_URL'], '?') ? '&' : '?') . 'appName=' . uniqid('caster-', true);
        $manager = new Manager($uri, [], ['serverSelectionTimeoutMS' => 2000]);

        $recorder = new class implements SDAMSubscriber {
            public TopologyDescription|null $topologyDescription = null;

            private function record(TopologyDescription $topologyDescription): void
            {
                // Keep the latest description, once the server has been discovered.
                $this->topologyDescription = $topologyDescription;
            }

            public function topologyChanged(TopologyChangedEvent $event): void
            {
                $this->record($event->getNewDescription());
            }

            public function topologyOpening(TopologyOpeningEvent $event): void
            {
            }

            public function topologyClosed(TopologyClosedEvent $event): void
            {
            }

            public function serverOpening(ServerOpeningEvent $event): void
            {
            }

            public function serverClosed(ServerClosedEvent $event): void
            {
            }

            public function serverChanged(ServerChangedEvent $event): void
            {
            }

            public function serverHeartbeatStarted(ServerHeartbeatStartedEvent $event): void
            {
            }

            public function serverHeartbeatSucceeded(ServerHeartbeatSucceededEvent $event): void
            {
            }

            public function serverHeartbeatFailed(ServerHeartbeatFailedEvent $event): void
            {
            }
        };

        $manager->addSubscriber($recorder);
        $manager->executeCommand('admin', new Command(['ping' => 1]));

        self::assertNotNull($recorder->topologyDescription);

        $dump = self::dump($recorder->topologyDescription);

        self::assertStringContainsString(TopologyDescription::class, $dump);
        self::assertStringContainsString('type: ', $dump);
        self::assertStringContainsString('servers', $dump);
        self::assertStringContainsString(ServerDescription::class, $dump);
    }

    public function testBulkWriteCommandResult(): void
    {
        if (! class_exists(BulkWriteCommand::class)) {
            self::markTestSkipped('MongoDB\Driver\BulkWriteCommand requires ext-mongodb 2.0');
        }

        $bulk = new BulkWriteCommand();
        $bulk->insertOne('test.caster', ['_id' => new ObjectId(), 'foo' => 'bar']);

        try {
            $result = $this->manager->executeBulkWriteCommand($bulk);
        } catch (ServerException $e) {
            self::markTestSkipped('The server does not support the bulkWrite command: ' . $e->getMessage());
        }

        $dump = self::dump($result);

        self::assertStringContainsString(BulkWriteCommandResult::class, $dump);
        self::assertStringContainsString('isAcknowledged: true', $dump);
        self::assertStringContainsString('insertedCount: 1', $dump);
    }

    private static function dump(mixed $value): string
    {
        $cloner = new VarCloner();
        $cloner->addCasters(MongoDBCaster::getCasters());

        $dumper = new CliDumper();
        $dumper->setColors(false);

        return $dumper->dump($cloner->cloneVar($value), true);
    }
}
