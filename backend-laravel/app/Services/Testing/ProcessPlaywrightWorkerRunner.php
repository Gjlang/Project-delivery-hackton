<?php

namespace App\Services\Testing;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * Invokes the Node/Playwright worker as a child process: JSON in via stdin,
 * JSON out via stdout. This is the only place in the app that shells out to
 * Node -- the worker is never exposed as a network service.
 */
class ProcessPlaywrightWorkerRunner implements PlaywrightWorkerRunner
{
    public function run(array $input): ?array
    {
        $workerDir = base_path('../playwright-worker');
        $entry = $workerDir.'/dist/index.js';

        if (! file_exists($entry)) {
            Log::error('Playwright worker build not found', ['expected_path' => $entry]);

            return null;
        }

        $ruleCount = max(count($input['rules'] ?? []), 1);
        $browserCount = max(count($input['browsers'] ?? []), 1);
        $timeoutSeconds = (int) ceil((($input['timeout_ms'] ?? 15000) * $ruleCount * $browserCount) / 1000) + 60;

        $process = new Process(['node', $entry], $workerDir);
        $process->setInput(json_encode($input));
        $process->setTimeout($timeoutSeconds);

        try {
            $process->run();
        } catch (\Throwable $e) {
            Log::error('Playwright worker process threw', ['error' => $e->getMessage()]);

            return null;
        }

        $stdout = $process->getOutput();
        $decoded = json_decode($stdout, true);

        if (! $process->isSuccessful() && ! is_array($decoded)) {
            Log::error('Playwright worker failed', ['exit_code' => $process->getExitCode(), 'stderr' => $process->getErrorOutput()]);

            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['results'])) {
            Log::error('Playwright worker returned unparseable output', ['stdout_sample' => substr($stdout, 0, 500), 'stderr' => $process->getErrorOutput()]);

            return null;
        }

        return $decoded['results'];
    }
}
