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

namespace MongoDB\Bundle\Tests\Unit\Caster;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Decimal128;
use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;
use MongoDB\BSON\Iterator;
use MongoDB\BSON\Javascript;
use MongoDB\BSON\MaxKey;
use MongoDB\BSON\MinKey;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\PackedArray;
use MongoDB\BSON\Regex;
use MongoDB\BSON\Timestamp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\BSON\VectorType;
use MongoDB\Bundle\Caster\MongoDBCaster;
use MongoDB\Bundle\MongoDBBundle;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\BulkWriteCommand;
use MongoDB\Driver\Command;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Query;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\ServerApi;
use MongoDB\Driver\WriteConcern;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;

use function class_exists;
use function str_repeat;

final class MongoDBCasterTest extends TestCase
{
    public function testBootRegistersCasters(): void
    {
        (new MongoDBBundle())->boot();

        self::assertArrayHasKey(Document::class, AbstractCloner::$defaultCasters);
        self::assertArrayHasKey(PackedArray::class, AbstractCloner::$defaultCasters);
        self::assertArrayHasKey(Manager::class, AbstractCloner::$defaultCasters);
    }

    public function testDocumentIsDecoded(): void
    {
        $dump = self::dump(Document::fromPHP([
            'string' => 'value',
            'int' => 42,
            'nested' => Document::fromPHP(['foo' => 'bar']),
            'list' => PackedArray::fromPHP([1, 2]),
        ]));

        self::assertStringContainsString(Document::class, $dump);
        self::assertStringContainsString('+string: "value"', $dump);
        self::assertStringContainsString('+int: 42', $dump);
        self::assertStringContainsString('"foo" => "bar"', $dump);
        self::assertStringContainsString('+list:', $dump);
        self::assertStringNotContainsString('"data"', $dump);
    }

    public function testPackedArrayIsDecoded(): void
    {
        $dump = self::dump(PackedArray::fromPHP(['foo', 'bar']));

        self::assertStringContainsString(PackedArray::class, $dump);
        self::assertStringContainsString('+0: "foo"', $dump);
        self::assertStringContainsString('+1: "bar"', $dump);
        self::assertStringNotContainsString('"data"', $dump);
    }

    public function testDocumentIsDecodedRecursively(): void
    {
        $dump = self::dump(Document::fromPHP([
            'date' => new UTCDateTime(1700000000000),
            'id' => new ObjectId('6553e87d888e85bbe80e36d0'),
            'nested' => Document::fromPHP([
                'list' => PackedArray::fromPHP([Document::fromPHP(['foo' => 'bar'])]),
            ]),
        ]));

        self::assertStringContainsString('date: 2023-11-14 22:13:20.000000 UTC', $dump);
        self::assertStringContainsString('+"oid": "6553e87d888e85bbe80e36d0"', $dump);
        self::assertStringContainsString('"foo" => "bar"', $dump);
    }

    public function testUTCDateTime(): void
    {
        $dump = self::dump(new UTCDateTime(1));

        self::assertStringContainsString('+"milliseconds": "1"', $dump);
        self::assertStringContainsString('date: 1970-01-01 00:00:00.001000 UTC', $dump);
    }

    public function testInt64(): void
    {
        $dump = self::dump(new Int64(1));

        self::assertStringContainsString('integer: 1', $dump);
    }

    public function testTimestamp(): void
    {
        $dump = self::dump(new Timestamp(1, 2));

        self::assertStringContainsString('+"increment": "1"', $dump);
        self::assertStringContainsString('+"timestamp": "2"', $dump);
        self::assertStringContainsString('date: 1970-01-01 00:00:02 UTC', $dump);
    }

    public function testBinary(): void
    {
        $dump = self::dump(new Binary('foo', Binary::TYPE_GENERIC));

        self::assertStringContainsString('+"data": "foo"', $dump);
        self::assertStringContainsString('type: Generic', $dump);
    }

    /** @dataProvider provideBinaryTypes */
    public function testBinaryTypeName(int $type, string $expected): void
    {
        self::assertStringContainsString('type: ' . $expected, self::dump(new Binary(str_repeat('a', 16), $type)));
    }

    /** @return iterable<string, array{int, string}> */
    public static function provideBinaryTypes(): iterable
    {
        yield 'generic' => [Binary::TYPE_GENERIC, 'Generic'];
        yield 'old binary' => [Binary::TYPE_OLD_BINARY, 'Old binary'];
        yield 'UUID' => [Binary::TYPE_UUID, 'UUID'];
        yield 'MD5' => [Binary::TYPE_MD5, 'MD5'];
        yield 'user defined' => [Binary::TYPE_USER_DEFINED, 'User defined'];
    }

    public function testBulkWriteOperationCount(): void
    {
        $bulk = new BulkWrite();
        $bulk->insert(['foo' => 'bar']);
        $bulk->update(['foo' => 'bar'], ['$set' => ['foo' => 'baz']]);
        $bulk->delete(['foo' => 'baz']);

        self::assertStringContainsString('operationCount: 3', self::dump($bulk));
    }

    public function testBulkWriteCommandOperationCount(): void
    {
        if (! class_exists(BulkWriteCommand::class)) {
            self::markTestSkipped('MongoDB\Driver\BulkWriteCommand requires ext-mongodb 2.0');
        }

        $bulk = new BulkWriteCommand();
        $bulk->insertOne('test.collection', ['foo' => 'bar']);
        $bulk->updateOne('test.collection', ['foo' => 'bar'], ['$set' => ['foo' => 'baz']]);

        self::assertStringContainsString('operationCount: 2', self::dump($bulk));
    }

    public function testManager(): void
    {
        $dump = self::dump(new Manager('mongodb://localhost:27017'));

        self::assertStringContainsString(Manager::class, $dump);
        self::assertStringContainsString('readPreference', $dump);
        self::assertStringContainsString('readConcern', $dump);
        self::assertStringContainsString('writeConcern', $dump);
    }

    /** @dataProvider provideObjectsWithDefaultCaster */
    public function testObjectsWithDefaultCaster(object $object, string $expected): void
    {
        self::assertStringContainsString($expected, self::dump($object));
    }

    /** @return iterable<string, array{object, string}> */
    public static function provideObjectsWithDefaultCaster(): iterable
    {
        yield 'ReadPreference' => [new ReadPreference('primary'), 'mode'];
        yield 'ReadConcern' => [new ReadConcern('local'), 'level'];
        yield 'WriteConcern' => [new WriteConcern('majority', 1000), 'wtimeout'];
        yield 'ServerApi' => [new ServerApi('1'), 'version'];
        yield 'Regex' => [new Regex('foo', 'i'), 'pattern'];
        yield 'ObjectId' => [new ObjectId('6553e87d888e85bbe80e36d0'), 'oid'];
        yield 'Decimal128' => [new Decimal128('1.2'), 'dec'];
        yield 'Javascript' => [new Javascript('function (a) { return a; }'), 'code'];
        yield 'MaxKey' => [new MaxKey(), MaxKey::class];
        yield 'MinKey' => [new MinKey(), MinKey::class];

        if (class_exists(VectorType::class)) {
            yield 'VectorType' => [VectorType::Float32, 'Float32'];
        }

        yield 'Document iterator' => [Document::fromPHP(['foo' => 'bar'])->getIterator(), Iterator::class];
        yield 'Command' => [new Command(['ping' => 1]), Command::class];
        yield 'Query' => [new Query(['foo' => 'bar']), Query::class];
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
