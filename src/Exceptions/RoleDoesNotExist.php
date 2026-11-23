<?php

namespace Smk\Permission\Exceptions;

use InvalidArgumentException;

class RoleDoesNotExist extends InvalidArgumentException
{
    public static function named(string $name, int $tenantId, string $guard): static
    {
        return new static("There is no role named `{$name}` in tenant scope `{$tenantId}` for guard `{$guard}`.");
    }
}
