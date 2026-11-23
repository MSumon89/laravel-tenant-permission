<?php

namespace Smk\Permission\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UnauthorizedException extends HttpException
{
    /** @var array<int, string> */
    private array $requiredPermissions = [];

    /** @var array<int, string> */
    private array $requiredRoles = [];

    /**
     * @param  array<int, string>  $permissions
     */
    public static function forPermissions(array $permissions): static
    {
        $exception = new static(403, 'User does not have any of the necessary permissions.');
        $exception->requiredPermissions = $permissions;

        return $exception;
    }

    /**
     * @param  array<int, string>  $roles
     */
    public static function forRoles(array $roles): static
    {
        $exception = new static(403, 'User does not have any of the necessary roles.');
        $exception->requiredRoles = $roles;

        return $exception;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredPermissions(): array
    {
        return $this->requiredPermissions;
    }

    /**
     * @return array<int, string>
     */
    public function getRequiredRoles(): array
    {
        return $this->requiredRoles;
    }
}
