<?php

use BuildWithSmk\TenantPermission\Http\Controllers\PermissionController;
use BuildWithSmk\TenantPermission\Http\Controllers\RoleController;
use BuildWithSmk\TenantPermission\Http\Controllers\UserAccessController;
use Illuminate\Support\Facades\Route;

/*
| Loaded only when config('permission.ui.enabled') is true. Prefix, name
| prefix and middleware come from config('permission.ui').
*/

Route::get('/', fn () => redirect()->route(config('permission.ui.route_name_prefix', 'permission.ui.').'roles.index'))
    ->name('home');

Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
Route::get('roles/create', [RoleController::class, 'create'])->name('roles.create');
Route::post('roles', [RoleController::class, 'store'])->name('roles.store');
Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->whereNumber('role')->name('roles.edit');
Route::put('roles/{role}', [RoleController::class, 'update'])->whereNumber('role')->name('roles.update');
Route::delete('roles/{role}', [RoleController::class, 'destroy'])->whereNumber('role')->name('roles.destroy');

Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
Route::get('permissions/create', [PermissionController::class, 'create'])->name('permissions.create');
Route::post('permissions', [PermissionController::class, 'store'])->name('permissions.store');
Route::get('permissions/{permission}/edit', [PermissionController::class, 'edit'])->whereNumber('permission')->name('permissions.edit');
Route::put('permissions/{permission}', [PermissionController::class, 'update'])->whereNumber('permission')->name('permissions.update');
Route::delete('permissions/{permission}', [PermissionController::class, 'destroy'])->whereNumber('permission')->name('permissions.destroy');

Route::get('users', [UserAccessController::class, 'index'])->name('users.index');
Route::get('users/{user}', [UserAccessController::class, 'edit'])->name('users.edit');
Route::put('users/{user}', [UserAccessController::class, 'update'])->name('users.update');
