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

namespace MongoDB\Bundle\Caster;

use MongoDB\BSON\Binary;
use MongoDB\BSON\Document;
use MongoDB\BSON\Int64;
use MongoDB\BSON\PackedArray;
use MongoDB\BSON\Timestamp;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\BulkWriteCommand;
use MongoDB\Driver\BulkWriteCommandResult;
use MongoDB\Driver\Cursor;
use MongoDB\Driver\Manager;
use MongoDB\Driver\Server;
use MongoDB\Driver\Session;
use MongoDB\Driver\TopologyDescription;
use ReflectionClass;
use Symfony\Component\VarDumper\Caster\Caster;
use Symfony\Component\VarDumper\Caster\ConstStub;
use Throwable;

use function explode;
use function implode;
use function is_int;
use function method_exists;
use function phpversion;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function ucfirst;
use function version_compare;

use const DATE_ATOM;
use const PHP_INT_SIZE;

/**
 * Casts classes of the MongoDB extension to a readable array representation.
 *
 * @internal
 */
final class MongoDBCaster
{
    /** Type map used to decode BSON documents and arrays into plain PHP arrays. */
    private const TYPEMAP = ['root' => 'array', 'document' => 'array', 'array' => 'array'];

    /** Words that must keep their original case in binary type names. */
    private const BINARY_TYPE_WORDS = ['MD5' => 'MD5', 'UUID' => 'UUID'];

    /** Words expanded in server type names. */
    private const SERVER_TYPE_WORDS = ['RS' => 'Replica set'];

    /** @var array<int, string>|null */
    private static array|null $binaryTypeNames = null;

    /** @var array<int, string>|null */
    private static array|null $serverTypeNames = null;

    private static bool|null $cursorIdAsInt64 = null;

    /** @return array<class-string, callable> */
    public static function getCasters(): array
    {
        return [
            Document::class => self::castDocument(...),
            PackedArray::class => self::castPackedArray(...),
            Int64::class => self::castInt64(...),
            Timestamp::class => self::castTimestamp(...),
            UTCDateTime::class => self::castUTCDateTime(...),
            Binary::class => self::castBinary(...),
            Manager::class => self::castManager(...),
            Server::class => self::castServer(...),
            Session::class => self::castSession(...),
            Cursor::class => self::castCursor(...),
            BulkWrite::class => self::castBulkWrite(...),
            BulkWriteCommand::class => self::castBulkWriteCommand(...),
            BulkWriteCommandResult::class => self::castBulkWriteCommandResult(...),
            TopologyDescription::class => self::castTopologyDescription(...),
        ];
    }

    /**
     * Replaces the Base64 encoded BSON payload by the decoded document fields.
     */
    public static function castDocument(Document $document, array $a): array
    {
        unset($a[Caster::PREFIX_DYNAMIC . 'data']);

        return $document->toPHP(self::TYPEMAP) + $a;
    }

    /**
     * Replaces the Base64 encoded BSON payload by the decoded array items.
     */
    public static function castPackedArray(PackedArray $packedArray, array $a): array
    {
        unset($a[Caster::PREFIX_DYNAMIC . 'data']);

        return $packedArray->toPHP(self::TYPEMAP) + $a;
    }

    public static function castInt64(Int64 $int64, array $a): array
    {
        $value = (string) $int64;

        unset($a[Caster::PREFIX_DYNAMIC . 'integer']);

        // On 32-bit platforms, keep the exact value as a string.
        $a[Caster::PREFIX_VIRTUAL . 'integer'] = PHP_INT_SIZE >= 8 ? (int) $value : $value;

        return $a;
    }

    public static function castTimestamp(Timestamp $timestamp, array $a): array
    {
        $date = (new UTCDateTime($timestamp->getTimestamp() * 1000))->toDateTimeImmutable();

        $a[Caster::PREFIX_VIRTUAL . 'date'] = new ConstStub($date->format('Y-m-d H:i:s') . ' UTC', $date->format(DATE_ATOM));

        return $a;
    }

    public static function castUTCDateTime(UTCDateTime $utcDateTime, array $a): array
    {
        try {
            $date = $utcDateTime->toDateTimeImmutable();
        } catch (Throwable) {
            return $a;
        }

        $a[Caster::PREFIX_VIRTUAL . 'date'] = new ConstStub($date->format('Y-m-d H:i:s.u') . ' UTC', $date->format(DATE_ATOM));

        return $a;
    }

    public static function castBinary(Binary $binary, array $a): array
    {
        $type = $binary->getType();
        $typeNames = self::$binaryTypeNames ??= self::getTypeNames(Binary::class, self::BINARY_TYPE_WORDS);

        unset($a[Caster::PREFIX_DYNAMIC . 'type']);

        $a[Caster::PREFIX_VIRTUAL . 'type'] = new ConstStub($typeNames[$type] ?? (string) $type, $type);

        return $a;
    }

    public static function castManager(Manager $manager, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $a[$prefix . 'readPreference'] = $manager->getReadPreference();
        $a[$prefix . 'readConcern'] = $manager->getReadConcern();
        $a[$prefix . 'writeConcern'] = $manager->getWriteConcern();

        $encryptedFieldsMap = method_exists($manager, 'getEncryptedFieldsMap') ? $manager->getEncryptedFieldsMap() : null;
        if ($encryptedFieldsMap !== null) {
            $a[$prefix . 'encryptedFieldsMap'] = $encryptedFieldsMap;
        }

        $servers = $manager->getServers();
        if ($servers !== []) {
            $a[$prefix . 'servers'] = $servers;
        }

        return $a;
    }

    public static function castServer(Server $server, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;
        $type = $server->getType();
        $typeNames = self::$serverTypeNames ??= self::getTypeNames(Server::class, self::SERVER_TYPE_WORDS);

        $a[$prefix . 'host'] = $server->getHost();
        $a[$prefix . 'port'] = $server->getPort();
        $a[$prefix . 'type'] = new ConstStub($typeNames[$type] ?? (string) $type, $type);

        $latency = $server->getLatency();
        if ($latency !== null) {
            $a[$prefix . 'latency'] = $latency;
        }

        $tags = $server->getTags();
        if ($tags !== []) {
            $a[$prefix . 'tags'] = $tags;
        }

        return $a;
    }

    public static function castSession(Session $session, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $a[$prefix . 'logicalSessionId'] = $session->getLogicalSessionId();
        $a[$prefix . 'transactionState'] = $session->getTransactionState();
        $a[$prefix . 'isInTransaction'] = $session->isInTransaction();
        $a[$prefix . 'isDirty'] = $session->isDirty();

        $server = $session->getServer();
        if ($server !== null) {
            $a[$prefix . 'server'] = $server;
        }

        $operationTime = $session->getOperationTime();
        if ($operationTime !== null) {
            $a[$prefix . 'operationTime'] = $operationTime;
        }

        $clusterTime = $session->getClusterTime();
        if ($clusterTime !== null) {
            $a[$prefix . 'clusterTime'] = $clusterTime;
        }

        $transactionOptions = $session->getTransactionOptions();
        if ($transactionOptions !== null) {
            $a[$prefix . 'transactionOptions'] = $transactionOptions;
        }

        return $a;
    }

    public static function castCursor(Cursor $cursor, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        // ext-mongodb 1.x returns a CursorId, deprecated in favor of Int64.
        self::$cursorIdAsInt64 ??= version_compare((string) phpversion('mongodb'), '2.0.0', '<');

        $a[$prefix . 'id'] = self::$cursorIdAsInt64 ? $cursor->getId(true) : $cursor->getId();
        $a[$prefix . 'server'] = $cursor->getServer();
        $a[$prefix . 'isDead'] = $cursor->isDead();

        return $a;
    }

    public static function castBulkWrite(BulkWrite $bulkWrite, array $a): array
    {
        $a[Caster::PREFIX_VIRTUAL . 'operationCount'] = $bulkWrite->count();

        return $a;
    }

    public static function castBulkWriteCommand(BulkWriteCommand $bulkWriteCommand, array $a): array
    {
        $a[Caster::PREFIX_VIRTUAL . 'operationCount'] = $bulkWriteCommand->count();

        return $a;
    }

    public static function castBulkWriteCommandResult(BulkWriteCommandResult $result, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        $isAcknowledged = $result->isAcknowledged();
        $a[$prefix . 'isAcknowledged'] = $isAcknowledged;

        if (! $isAcknowledged) {
            return $a;
        }

        $a[$prefix . 'insertedCount'] = $result->getInsertedCount();
        $a[$prefix . 'matchedCount'] = $result->getMatchedCount();
        $a[$prefix . 'modifiedCount'] = $result->getModifiedCount();
        $a[$prefix . 'upsertedCount'] = $result->getUpsertedCount();
        $a[$prefix . 'deletedCount'] = $result->getDeletedCount();

        $insertResults = $result->getInsertResults();
        if ($insertResults !== null) {
            $a[$prefix . 'insertResults'] = $insertResults;
        }

        $updateResults = $result->getUpdateResults();
        if ($updateResults !== null) {
            $a[$prefix . 'updateResults'] = $updateResults;
        }

        $deleteResults = $result->getDeleteResults();
        if ($deleteResults !== null) {
            $a[$prefix . 'deleteResults'] = $deleteResults;
        }

        return $a;
    }

    public static function castTopologyDescription(TopologyDescription $topologyDescription, array $a): array
    {
        $prefix = Caster::PREFIX_VIRTUAL;

        unset(
            $a[Caster::PREFIX_DYNAMIC . 'type'],
            $a[Caster::PREFIX_DYNAMIC . 'servers'],
        );

        $a[$prefix . 'type'] = $topologyDescription->getType();
        $a[$prefix . 'hasWritableServer'] = $topologyDescription->hasWritableServer();
        $a[$prefix . 'servers'] = $topologyDescription->getServers();

        return $a;
    }

    /**
     * Builds a map of the TYPE_* constants of a class to a readable label.
     *
     * @param class-string          $class
     * @param array<string, string> $words Words to preserve or expand instead of lowercasing them.
     *
     * @return array<int, string>
     */
    private static function getTypeNames(string $class, array $words): array
    {
        $names = [];

        foreach ((new ReflectionClass($class))->getConstants() as $constant => $value) {
            if (! is_int($value) || ! str_starts_with($constant, 'TYPE_')) {
                continue;
            }

            $labelWords = explode('_', substr($constant, strlen('TYPE_')));
            foreach ($labelWords as $index => $word) {
                $labelWords[$index] = $words[$word] ?? strtolower($word);
            }

            $labelWords[0] = ucfirst($labelWords[0]);
            $names[$value] = implode(' ', $labelWords);
        }

        return $names;
    }
}
