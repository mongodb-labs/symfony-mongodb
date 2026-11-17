<?php

declare(strict_types=1);

namespace MongoDB\Bundle\Tests\Functional\DataCollector;

use ArrayIterator;
use MongoDB\Bundle\DataCollector\MongoDBDataCollector;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

use function array_column;
use function array_keys;
use function array_sum;
use function serialize;
use function unserialize;

class MongoDBDataCollectorTest extends TestCase
{
    public function testCollectMultipleClients(): void
    {
        $client1 = new Client($_SERVER['MONGODB_PRIMARY_URL']);
        $client2 = new Client($_SERVER['MONGODB_PRIMARY_URL']);
        $stopwatch = new Stopwatch();

        $dataCollector = new MongoDBDataCollector($stopwatch, new ArrayIterator(['client1' => $client1, 'client2' => $client2]));

        $client1Id = $dataCollector->getClientInternalId($client1);
        $client2Id = $dataCollector->getClientInternalId($client2);

        // Successful command on Client 1
        $dataCollector->collectCommandEvent($client1Id, 'request1', [
            'clientName' => 'client1',
            'databaseName' => 'database1',
            'commandName' => 'find',
            'command' => ['find' => 'collection1', 'filter' => []],
        ]);
        $dataCollector->collectCommandEvent($client1Id, 'request1', ['durationMicros' => 1000]);

        // Error on Client 1
        $dataCollector->collectCommandEvent($client1Id, 'request2', [
            'clientName' => 'client1',
            'databaseName' => 'database1',
            'commandName' => 'insert',
            'command' => ['insert' => 'collection1', 'documents' => []],
        ]);
        $dataCollector->collectCommandEvent($client1Id, 'request2', [
            'durationMicros' => 500,
            'error' => 'Error message',
        ]);

        // Successful command on Client 2
        $dataCollector->collectCommandEvent($client2Id, 'request3', [
            'clientName' => 'client2',
            'databaseName' => 'database2',
            'commandName' => 'aggregate',
            'command' => ['aggregate' => 'collection2', 'pipeline' => []],
        ]);
        $dataCollector->collectCommandEvent($client2Id, 'request3', ['durationMicros' => 800]);

        $dataCollector->lateCollect();

        // Data is serialized and unserialized by the profiler
        $dataCollector = unserialize(serialize($dataCollector));

        $this->assertSame('mongodb', $dataCollector->getName());
        $this->assertCount(2, $dataCollector->getClients());
        $this->assertSame(2300, $dataCollector->getTime());
        $this->assertSame(3, $dataCollector->getRequestCount());
        $this->assertSame(1, $dataCollector->getErrorCount());

        $requests = $dataCollector->getRequests();
        $this->assertSame(['client1', 'client2'], array_keys($requests));
        $this->assertSame([
            'request1' => [
                'clientName' => 'client1',
                'databaseName' => 'database1',
                'commandName' => 'find',
                'command' => ['find' => 'collection1', 'filter' => []],
                'durationMicros' => 1000,
            ],
            'request2' => [
                'clientName' => 'client1',
                'databaseName' => 'database1',
                'commandName' => 'insert',
                'command' => ['insert' => 'collection1', 'documents' => []],
                'durationMicros' => 500,
                'error' => 'Error message',
            ],
        ], $requests['client1']);
        $this->assertSame([
            'request3' => [
                'clientName' => 'client2',
                'databaseName' => 'database2',
                'commandName' => 'aggregate',
                'command' => ['aggregate' => 'collection2', 'pipeline' => []],
                'durationMicros' => 800,
            ],
        ], $requests['client2']);

        $clients = $dataCollector->getClients();
        $this->assertSame(['client1', 'client2'], array_keys($clients));
        $this->assertSame(['serverBuildInfo', 'clientInfo'], array_keys($clients['client1']));
    }

    public function testGetMoreCommandsAreGroupedWithTheRequestThatOpenedTheCursor(): void
    {
        $client = new Client($_SERVER['MONGODB_PRIMARY_URL']);

        $dataCollector = new MongoDBDataCollector(new Stopwatch(), new ArrayIterator(['client' => $client]));
        $clientId = $dataCollector->getClientInternalId($client);

        // The find command opens a cursor
        $dataCollector->collectCommandEvent($clientId, 'request1', [
            'databaseName' => 'database1',
            'commandName' => 'find',
            'command' => ['find' => 'collection1', 'filter' => []],
        ]);
        $dataCollector->collectCommandEvent($clientId, 'request1', [
            'durationMicros' => 1000,
            'cursorId' => 42,
        ]);

        // Two getMore commands fetch the following batches
        foreach (['request2' => 500, 'request3' => 300] as $requestId => $durationMicros) {
            $dataCollector->collectCommandEvent($clientId, $requestId, [
                'databaseName' => 'database1',
                'commandName' => 'getMore',
                'command' => ['getMore' => 42, 'collection' => 'collection1'],
                'cursorId' => 42,
            ]);
            $dataCollector->collectCommandEvent($clientId, $requestId, [
                'durationMicros' => $durationMicros,
                'cursorId' => 42,
            ]);
        }

        $dataCollector->lateCollect();

        $this->assertSame(1, $dataCollector->getRequestCount());
        $this->assertSame(2, $dataCollector->getGetMoreCount());
        $this->assertSame(1800, $dataCollector->getTime());

        $requests = $dataCollector->getRequests()['client'];
        $this->assertArrayHasKey('request1', $requests);
        $this->assertArrayNotHasKey('request2', $requests);
        $this->assertArrayNotHasKey('request3', $requests);

        $request = $requests['request1'];
        $this->assertSame('find', $request['commandName']);
        $this->assertSame(1800, $request['durationMicros']);
        $this->assertSame(2, $request['getMoreCount']);
        $this->assertSame(800, $request['getMoreDurationMicros']);
        $this->assertSame([
            ['durationMicros' => 500],
            ['durationMicros' => 300],
        ], $request['getMoreCommands']);
    }

    public function testCursorIterationIsGroupedInTheProfiler(): void
    {
        $client = new Client($_SERVER['MONGODB_PRIMARY_URL']);
        $collection = $client->selectCollection('mongodb_bundle_tests', 'cursor_statistics');
        $collection->drop();
        $collection->insertMany([['value' => 1], ['value' => 2], ['value' => 3]]);

        $dataCollector = new MongoDBDataCollector(null, new ArrayIterator(['client' => $client]));
        $dataCollector->configureClient($client);

        try {
            $collection->find([], ['batchSize' => 1])->toArray();
            $dataCollector->lateCollect();
        } finally {
            $collection->drop();
        }

        $findRequest = null;
        foreach ($dataCollector->getRequests()['client'] as $request) {
            $this->assertNotSame('getMore', $request['commandName']);

            if ($request['commandName'] !== 'find') {
                continue;
            }

            $findRequest = $request;
        }

        $this->assertNotNull($findRequest);
        $this->assertGreaterThanOrEqual(1, $findRequest['getMoreCount']);
        $this->assertCount($findRequest['getMoreCount'], $findRequest['getMoreCommands']);
        $this->assertSame(
            $findRequest['getMoreDurationMicros'],
            array_sum(array_column($findRequest['getMoreCommands'], 'durationMicros')),
        );
    }
}
