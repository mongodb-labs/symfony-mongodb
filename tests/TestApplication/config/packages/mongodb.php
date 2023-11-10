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

use MongoDB\Bundle\Tests\Functional\FunctionalTestCase;

$container->loadFromExtension('mongodb', [
    'default_client' => 'primary',
    'clients' => [
        [
            'id' => 'primary',
            'uri' => '%env(MONGODB_PRIMARY_URL)%',
            'default_database' => FunctionalTestCase::DB_CUSTOMER_GOOGLE,
        ],
        [
            'id' => 'secondary',
            'uri' => '%env(MONGODB_PRIMARY_URL)%', // TODO: change to secondary!!!
            // has no default_database
        ],
    ],
]);
