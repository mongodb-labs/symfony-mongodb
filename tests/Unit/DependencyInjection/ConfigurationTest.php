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

namespace MongoDB\Bundle\Tests\Unit\DependencyInjection;

use MongoDB\Bundle\DependencyInjection\Configuration;
use MongoDB\Driver\ServerApi;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Yaml\Yaml;

/** @covers \MongoDB\Bundle\DependencyInjection\Configuration */
final class ConfigurationTest extends TestCase
{
    public function testProcess(): void
    {
        $configs = [[
            'default_client' => 'default',
            'clients' => [
                [
                    'id' => 'default',
                    'uri' => 'mongodb://localhost:27017',
                    'uri_options' => ['readPreference' => 'primary'],
                ],
                [
                    'id' => 'secondary',
                    'uri' => 'mongodb://localhost:27018',
                    'driver_options' => ['serverApi' => new ServerApi((string) ServerApi::V1)],
                ],
            ],
        ],
        ];

        $config = $this->process($configs);

        $this->assertArrayHasKey('clients', $config);
        $clients = $config['clients'];

        $this->assertCount(2, $clients);

        $this->assertArrayHasKey('default', $clients);
        $this->assertSame('mongodb://localhost:27017', $clients['default']['uri']);
        $this->assertSame(['readPreference' => 'primary'], $clients['default']['uri_options']);
        $this->assertSame([], $clients['default']['driver_options']);

        $this->assertArrayHasKey('secondary', $clients);
        $this->assertSame('mongodb://localhost:27018', $clients['secondary']['uri']);
        $this->assertSame([], $clients['secondary']['uri_options']);
        $this->assertEquals(['serverApi' => new ServerApi((string) ServerApi::V1)], $clients['secondary']['driver_options']);
    }

    public function testProcessWithYamlFile(): void
    {
        $yaml = Yaml::parse(<<<'YAML'
default_client: default
clients:
    default:
        uri: mongodb://localhost:27017
        uri_options:
            readPreference: primary
    secondary:
        uri: mongodb://localhost:27018
        driver_options:
            serverApi: v1
YAML);

        $config = $this->process([$yaml]);

        $this->assertArrayHasKey('clients', $config);
        $clients = $config['clients'];

        $this->assertCount(2, $clients);

        $this->assertArrayHasKey('default', $clients);
        $this->assertSame('mongodb://localhost:27017', $clients['default']['uri']);
        $this->assertSame(['readPreference' => 'primary'], $clients['default']['uri_options']);
        $this->assertSame([], $clients['default']['driver_options']);

        $this->assertArrayHasKey('secondary', $clients);
        $this->assertSame('mongodb://localhost:27018', $clients['secondary']['uri']);
        $this->assertSame([], $clients['secondary']['uri_options']);
        $this->assertSame(['serverApi' => 'v1'], $clients['secondary']['driver_options']);
    }

    public function testProcessWithYamlFileWithoutUriKey(): void
    {
        $yaml = Yaml::parse(<<<'YAML'
default_client: default
clients:
    default: mongodb://localhost:27017
    secondary: mongodb://localhost:27018
YAML);

        $config = $this->process([$yaml]);

        $this->assertArrayHasKey('clients', $config);
        $clients = $config['clients'];

        $this->assertCount(2, $clients);

        $this->assertArrayHasKey('default', $clients);
        $this->assertSame('mongodb://localhost:27017', $clients['default']['uri']);

        $this->assertArrayHasKey('secondary', $clients);
        $this->assertSame('mongodb://localhost:27018', $clients['secondary']['uri']);
    }

    public function testProcessWithYamlThrowsExceptionIfManyClientsAndDefaultClientNotDefined(): void
    {
        $yaml = Yaml::parse(<<<'YAML'
clients:
    default: mongodb://localhost:27017
    secondary: mongodb://localhost:27018
YAML);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "mongodb": You must define a "default_client" when more than one client is defined.');

        $this->process([$yaml]);
    }

    public function testProcessWithYamlDoesNotRequireDefaultClientIfOnlyOneClient(): void
    {
        $yaml = Yaml::parse(<<<'YAML'
clients:
    default: mongodb://localhost:27017
YAML);

        $config = $this->process([$yaml]);

        $this->assertArrayHasKey('clients', $config);
        $clients = $config['clients'];

        $this->assertCount(1, $clients);

        $this->assertArrayHasKey('default', $clients);
        $this->assertSame('mongodb://localhost:27017', $clients['default']['uri']);
    }

    public function testProcessWithYamlThrowsExceptionIfManyClientsAndDefaultClientDoesNotMatch(): void
    {
        $yaml = Yaml::parse(<<<'YAML'
default_client: FOO
clients:
    default: mongodb://localhost:27017
    secondary: mongodb://localhost:27018
YAML);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "mongodb": The "default_client" "FOO" is not defined in "clients" section.');

        $this->process([$yaml]);
    }

    private function process(array $configs): array
    {
        $processor = new Processor();

        return $processor->processConfiguration(new Configuration(), $configs);
    }
}
