<?php

namespace App\Modules\Billing\Exceptions;

use RuntimeException;

class QuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly string $resource,
        public readonly int $limit,
        public readonly int $usage,
    ) {
        parent::__construct(
            "Quota exceeded for [{$resource}]: current usage {$usage} >= plan limit {$limit}."
        );
    }
}
