<?php

namespace BuildWithSmk\TenantPermission\Exceptions;

use InvalidArgumentException;

class PermissionDoesNotExist extends InvalidArgumentException
{
    public static function named(string $name, string $guard): static
    {
        return new static("There is no permission named `{$name}` for guard `{$guard}`.");
    }
}
