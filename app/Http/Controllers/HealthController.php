<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Dependency check for load balancers and uptime monitors. Reports only ok/fail per component,
     * never versions, hostnames or error text, so it is safe to expose.
     */
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health:probe', 1, 10);

                return Cache::get('health:probe') === 1;
            }),
            'queue_backlog' => $this->check(fn () => $this->failedJobsBelow(100)),
        ];

        $ok = ! in_array(false, $checks, true);

        return response()->json(['status' => $ok ? 'ok' : 'degraded', 'checks' => $checks], $ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return $probe() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function failedJobsBelow(int $limit): bool
    {
        return DB::table('failed_jobs')->count() < $limit;
    }
}
