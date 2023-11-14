<?php

declare(strict_types=1);

namespace MongoDB\Bundle\Tests\Functional\DataCollector;

use ArrayIterator;
use MongoDB\Bundle\DataCollector\MongoDBDataCollector;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

use function array_keys;
use function serialize;
use function spl_object_id;
use function unserialize;

class MongoDBDataCollectorTest extends TestCase
{
    public function testCollectMultipleClients(): void
    {
        $client1 = new Client($_SERVER['MONGODB_PRIMARY_URL']);
        $client2 = new Client($_SERVER['MONGODB_PRIMARY_URL']);
        $stopwatch = new Stopwatch();

        $dataCollector = new MongoDBDataCollector($stopwatch, new ArrayIterator(['client1' => $client1, 'client2' => $client2]));

        $client1Id = spl_object_id($client1);
        $client2Id = spl_object_id($client2);

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
}
