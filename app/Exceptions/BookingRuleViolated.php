<?php

namespace App\Exceptions;

use DomainException;

/** Permintaan booking melanggar aturan (min. malam, kapasitas, tanggal lampau, dll). */
class BookingRuleViolated extends DomainException
{
    /** @param array<string,mixed> $context */
    public function __construct(public readonly string $reason, public readonly array $context = [])
    {
        parent::__construct(__('site.errors.'.$reason, $context));
    }
}
