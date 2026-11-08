<?php

namespace App\Enums;

enum TenantRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';
}
