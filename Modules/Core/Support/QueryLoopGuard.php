<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Catches a query run inside a loop: the same statement (only its values differ) sent more than
 * a few times while one request is answered. Fix it with eager loading (with / load), one
 * whereIn, or a bulk insert / update. Throws where developers and tests run, only logs in production.
 */
final class QueryLoopGuard
{
    public const LIMIT = 5;

    /** @var array<string, int> */
    private array $seen = [];

    private bool $watching = false;

    public function listen(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (! $this->watching) {
                return;
            }

            // The cache and session stores are read by the framework itself, not by our code.
            if (preg_match('/\\b(from|into|update)\\s+"?(cache|cache_locks|sessions)"?/i', $query->sql)) {
                return;
            }

            $count = $this->seen[$query->sql] = ($this->seen[$query->sql] ?? 0) + 1;

            if ($count !== self::LIMIT + 1) {
                return;
            }

            $message = 'Query in a loop ('.self::LIMIT.'+ times in one request): '.$query->sql;

            if (app()->isProduction()) {
                Log::warning($message);

                return;
            }

            throw new LogicException($message);
        });
    }

    /** Counts the queries of one request: start before it is handled, stop when it is answered. */
    public function around(callable $request): mixed
    {
        $this->seen = [];
        $this->watching = true;

        try {
            return $request();
        } finally {
            $this->watching = false;
        }
    }
}
