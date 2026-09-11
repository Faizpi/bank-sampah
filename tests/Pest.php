<?php

declare(strict_types=1);

use Tests\TestCase;

pest()
    ->extend(TestCase::class)
    ->in('Feature');

pest()
    ->group('unit')
    ->in('Unit');
