<?php

namespace App\Tenancy;

use RuntimeException;

final class MissingTenantException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No agency is set for this request. Tenant-owned data cannot be read or written.');
    }
}
