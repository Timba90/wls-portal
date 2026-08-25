<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

// Browser-Tests brauchen dieselbe Laravel-Testbasis wie die Feature-Tests:
// ohne sie gäbe es weder `actingAs()` noch eine gebootete Anwendung, gegen
// die der Browser überhaupt etwas aufrufen könnte.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');
