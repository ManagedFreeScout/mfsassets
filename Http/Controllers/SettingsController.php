<?php

namespace Modules\MFSAssets\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Providers\MFSAssetsServiceProvider as Provider;
use Modules\MFSAssets\Services\LicenseService;

/** Manage → Settings → MFS Assets (admins only, see routes.php). */
class SettingsController extends Controller
{
    public function save(Request $request)
    {
        \Option::set(Provider::OPTION_OUR_ORG, (int) $request->input('our_org'));
        $prefix = preg_replace('/[^A-Za-z0-9_.-]/', '', (string) $request->input('tag_prefix'));
        \Option::set(Asset::OPTION_TAG_PREFIX, mb_substr($prefix, 0, 20));
        \Option::set(Provider::OPTION_REPORT_MAILBOX, (int) $request->input('report_mailbox'));
        \Session::flash('flash_success_floating', __('Settings saved.'));
        return redirect()->route('settings', ['section' => Provider::MODULE_ALIAS]);
    }

    // Licence, same as MFS Essentials (card #194).

    public function manageLicense(Request $request)
    {
        $service = app(LicenseService::class);
        if ($service->getLicenseStatus()['status'] === 'no_table') {
            return response()->json(['status' => 'error', 'message' => __('License table does not exist. Please run migrations first.')]);
        }
        $action = $request->input('action');
        $key = $request->input('license_key');
        if (empty($key) && $action !== 'deactivate') {
            return response()->json(['status' => 'error', 'message' => __('License key is required.')]);
        }
        if ($action === 'activate') {
            $result = $service->activateLicense($key);
        } elseif ($action === 'deactivate') {
            $result = $service->deactivateLicense($service->getLicenseStatus()['license_key'] ?? $key);
        } else {
            return response()->json(['status' => 'error', 'message' => __('Invalid action.')]);
        }
        return response()->json(['status' => $result['success'] ? 'success' : 'error', 'message' => $result['message']]);
    }

    public function handleModuleLicenseAction(Request $request)
    {
        if ($request->input('module_alias') !== Provider::MODULE_ALIAS) {
            return response()->json(['success' => false, 'message' => __('Invalid module')]);
        }
        $key = $request->input('license_key');
        if (empty($key) && $request->input('action') !== 'deactivate') {
            return response()->json(['success' => false, 'message' => __('License key is required')]);
        }
        return response()->json(app(LicenseService::class)->performAction($request->input('action'), $key));
    }
}
