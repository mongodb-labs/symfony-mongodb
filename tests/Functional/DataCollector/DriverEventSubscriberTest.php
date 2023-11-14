<?php

declare(strict_types=1);

namespace MongoDB\Bundle\Tests\Functional\DataCollector;

use MongoDB\Bundle\DataCollector\CommandEventCollector;
use MongoDB\Bundle\DataCollector\DriverEventSubscriber;
use MongoDB\Client;
use MongoDB\Driver\Command;
use MongoDB\Driver\Exception\ServerException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;

class DriverEventSubscriberTest extends TestCase
{
    private const CLIENT_ID = 123;

    private CommandEventCollector $collector;
    private Stopwatch $stopwatch;

    public function setUp(): void
    {
        $this->collector = new Stubs\CommandEventCollectorStub();
        $this->stopwatch = new Stopwatch();
    }

    public function testCommandSucceeded(): void
    {
        $this->getClient()->selectCollection('database1', 'collection1')->find();

        // The 2 events are commandStarted and commandSucceeded
        $this->assertCount(2, $this->collector->events);

        // ClientId
        $this->assertSame(self::CLIENT_ID, $this->collector->events[0]['clientId']);
        $this->assertSame(self::CLIENT_ID, $this->collector->events[1]['clientId']);

        // RequestId
        $this->assertSame($this->collector->events[0]['requestId'], $this->collector->events[1]['requestId'], 'Same $requestId');

        // Data 1st event
        $this->assertSame('database1', $this->collector->events[0]['data']['databaseName']);
        $this->assertSame('find', $this->collector->events[0]['data']['commandName']);
        $this->assertArrayHasKey('command', $this->collector->events[0]['data']);
        $this->assertArrayHasKey('backtrace', $this->collector->events[0]['data']);

        // Data 2nd event
        $this->assertArrayHasKey('durationMicros', $this->collector->events[1]['data']);
        $this->assertArrayNotHasKey('backtrace', $this->collector->events[1]['data']);
    }

    public function testCommandFailed(): void
    {
        try {
            $this->getClient()->getManager()->executeCommand('database1', new Command(['invalid' => 'command']));
            $this->fail('Expected exception to be thrown');
        } catch (ServerException $e) {
            $message = $e->getMessage();
        }

        // The 2 events are commandStarted and commandFailed
        $this->assertCount(2, $this->collector->events);

        // ClientId
        $this->assertSame(self::CLIENT_ID, $this->collector->events[0]['clientId']);
        $this->assertSame(self::CLIENT_ID, $this->collector->events[1]['clientId']);

        // RequestId
        $this->assertSame($this->collector->events[0]['requestId'], $this->collector->events[1]['requestId'], 'Same $requestId');

        // Data 1st event
        $this->assertSame('database1', $this->collector->events[0]['data']['databaseName']);
        $this->assertSame('invalid', $this->collector->events[0]['data']['commandName']);
        $this->assertArrayHasKey('command', $this->collector->events[0]['data']);
        $this->assertArrayHasKey('backtrace', $this->collector->events[0]['data']);

        // Data 2nd event
        $this->assertArrayHasKey('durationMicros', $this->collector->events[1]['data']);
        $this->assertArrayHasKey('error', $this->collector->events[1]['data']);
        $this->assertStringContainsString($message, $this->collector->events[1]['data']['error']);
        $this->assertArrayNotHasKey('backtrace', $this->collector->events[1]['data']);
    }

    public function getClient(): Client
    {
        $subscriber = new DriverEventSubscriber(self::CLIENT_ID, $this->collector, $this->stopwatch);

        $client = new Client($_SERVER['MONGODB_PRIMARY_URL'] ?? 'mongodb://localhost:27017');
        $client->getManager()->addSubscriber($subscriber);

        return $client;
    }
}
