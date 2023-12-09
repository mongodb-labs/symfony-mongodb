<?php

namespace MongoDB\Bundle\Tests\Unit\Attribute;

use Generator;
use MongoDB\BSON\Document;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;

abstract class AttributeTestCase extends TestCase
{
    public static function provideOptions(): Generator
    {
        $codec = new class implements DocumentCodec {
            use DecodeIfSupported;
            use EncodeIfSupported;

            public function canDecode($value): bool
            {
                return $value instanceof Document;
            }

            public function canEncode($value): bool
            {
                return $value instanceof Document;
            }

            public function decode($value): Document
            {
                return $value;
            }

            public function encode($value): Document
            {
                return $value;
            }
        };

        $options = [
            'codec' => $codec,
            'typeMap' => ['root' => 'array'],
            'writeConcern' => new WriteConcern(0),
            'readConcern' => new ReadConcern('majority'),
            'readPreference' => new ReadPreference('primary'),
        ];

        foreach ($options as $option => $value) {
            yield sprintf('%s option: null', $option) => [
                'attributeArguments' => [$option => null],
                'expectedOptions' => [],
            ];

            yield sprintf('%s option: instance', $option) => [
                'attributeArguments' => [$option => $value],
                'expectedOptions' => [$option => $value],
            ];

            yield sprintf('%s option: reference', $option) => [
                'attributeArguments' => [$option => sprintf('%s_service', $option)],
                'expectedOptions' => [$option => new Reference(sprintf('%s_service', $option))],
            ];
        }
    }
}
