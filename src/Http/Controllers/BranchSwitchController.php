<?php

namespace BuildWithSmk\TenantPermission\Http\Controllers;

use BuildWithSmk\TenantPermission\Middleware\ResolveBranch;
use BuildWithSmk\TenantPermission\PermissionRegistrar;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

/**
 * POST branch_id (0 = "All branches") to switch the current branch. Only
 * branches the resolver allows for the user are accepted.
 */
class BranchSwitchController extends BaseController
{
    public function __invoke(Request $request, PermissionRegistrar $registrar): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['branch_id' => ['required', 'integer', 'min:0']]);
        $requested = (int) $data['branch_id'];

        [$selected, $accessible] = ResolveBranch::resolveFor($registrar, $request->user(), $requested);

        abort_unless($selected === $requested, 403, 'You cannot switch to that branch.');

        $request->session()->put(config('permission.branches.session_key', 'permission_branch_id'), $selected);
        $registrar->setAccessibleBranchIds($accessible)->setCurrentBranchId($selected);

        if ($request->expectsJson()) {
            return response()->json(['branch_id' => $selected]);
        }

        return redirect()->back();
    }
}
