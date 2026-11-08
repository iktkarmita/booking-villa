<?php

namespace App\Exceptions;

use DomainException;

/** Dilempar saat tanggal yang diminta sudah terkunci (booking atau blokir). */
class DatesUnavailable extends DomainException
{
}
