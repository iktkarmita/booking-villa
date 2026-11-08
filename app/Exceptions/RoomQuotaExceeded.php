<?php

namespace App\Exceptions;

use DomainException;

/** Dilempar saat mengaktifkan kamar melebihi kuota paket langganan. */
class RoomQuotaExceeded extends DomainException
{
}
