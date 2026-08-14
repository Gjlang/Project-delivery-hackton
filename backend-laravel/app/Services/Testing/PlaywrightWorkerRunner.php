<?php

namespace App\Services\Testing;

interface PlaywrightWorkerRunner
{
    /**
     * Run the worker with the given input payload and return its decoded
     * `results` array, or null if the worker could not produce valid output.
     */
    public function run(array $input): ?array;
}
