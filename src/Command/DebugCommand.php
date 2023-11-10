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

namespace MongoDB\Bundle\Command;

use ReflectionExtension;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ServiceLocator;

use function array_slice;
use function explode;
use function extension_loaded;
use function ob_get_clean;
use function ob_start;
use function sprintf;

#[AsCommand(
    name: 'mongodb:debug',
    description: 'Shows debug information about the MongoDB integration',
)]
final class DebugCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly ServiceLocator $clients,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->printExtensionInformation();
        $this->printClientInformation();

        // TODO: Add some information about the used attributes and their configs!

        return Command::SUCCESS;
    }

    private function printExtensionInformation(): void
    {
        $this->io->section('MongoDB Extension Information');

        if (! extension_loaded('mongodb')) {
            $this->io->error('The MongoDB extension is not loaded.');
            // TODO: Add helpful information on how to solve this

            return;
        }

        $extension = new ReflectionExtension('mongodb');

        ob_start();
        $extension->info();
        $info = explode("\n", ob_get_clean());

        $this->io->text(array_slice($info, 3));
    }

    private function printClientInformation(): void
    {
        $this->io->section('MongoDB Client Information');
        $this->io->text(sprintf('%d clients configured', $this->clients->count()));

        $table = $this->io->createTable();
        $table->setHeaders(['Service ID', 'URI']);

        foreach ($this->clients->getProvidedServices() as $serviceId => $class) {
            $client = $this->clients->get($serviceId);
            $table->addRow([$serviceId, $client->__debugInfo()['uri']]);
        }

        $table->render();
    }
}
