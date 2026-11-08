<?php

namespace Modules\MFSAssets\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Modules\MFSAssets\Services\LicenseService;

defined('MFSASSETS_MODULE') || define('MFSASSETS_MODULE', 'mfsassets');

/**
 * MFS Assets (card freescout-modules #426): asset management on top of MFS Organisations.
 * Organisations, departments and locations come from MFS Organisations; this module needs it.
 */
class MFSAssetsServiceProvider extends ServiceProvider
{
    const MODULE_ALIAS = 'mfsassets';

    // "Users are allowed to manage assets" (v1 item 13). Admins: settings, types, delete.
    const PERM_MANAGE = 'mfsassets.manage';

    // Settings (Manage → Settings → MFS Assets).
    const OPTION_OUR_ORG = 'mfsassets.our_org';
    const OPTION_REPORT_MAILBOX = 'mfsassets.report_mailbox';

    protected $defer = false;

    public function boot()
    {
        $this->mergeConfigFrom(__DIR__ . '/../Config/config.php', self::MODULE_ALIAS);
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', self::MODULE_ALIAS);
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');
        $this->registerLicenseHooks();
        $this->registerMenuAndPermissions();
        $this->registerAssets();
        if (class_exists('\Modules\MFSAssets\Providers\Hooks')) {
            Hooks::register();
        }
    }

    public function register()
    {
        $this->app->singleton(LicenseService::class, function () {
            return new LicenseService();
        });
    }

    /** MFS Organisations must be active: owners, departments and locations come from it. */
    public static function organisationsAvailable()
    {
        return \Module::isActive('mfsorganisations')
            && class_exists('\Modules\MFSOrganisations\Entities\Organisation')
            && \Schema::hasTable('mfsorg_locations');
    }

    /** On: licensed and MFS Organisations (with locations) available. */
    public static function enabled()
    {
        static $on = null;
        if ($on === null) {
            $on = LicenseService::isLicensed() && self::organisationsAvailable();
        }
        return $on;
    }

    public static function canManage($user = null)
    {
        $user = $user ?: Auth::user();
        return $user && ($user->isAdmin() || $user->hasPermission(self::PERM_MANAGE));
    }

    /** The organisation that is "ourselves": the default owner of new assets. */
    public static function ourOrgId()
    {
        return (int) \Option::get(self::OPTION_OUR_ORG, 0);
    }

    protected function registerLicenseHooks()
    {
        \Eventy::addFilter('settings.sections', function ($sections) {
            $sections[self::MODULE_ALIAS] = [
                'title'       => __('MFS Assets'),
                'icon'        => 'hdd',
                'order'       => 330,
                'description' => __('Licence, our own organisation, asset tags and the report form.'),
            ];
            return $sections;
        }, 15);

        \Eventy::addFilter('settings.section_settings', function ($settings, $section) {
            if ($section === self::MODULE_ALIAS) {
                $settings['license_status'] = app(LicenseService::class)->getLicenseStatus();
            }
            return $settings;
        }, 20, 2);

        \Eventy::addFilter('settings.view', function ($view, $section) {
            return $section === self::MODULE_ALIAS ? self::MODULE_ALIAS . '::settings.mfsassets' : $view;
        }, 20, 2);

        \Eventy::addFilter('modules.show_license', function ($show, $module) {
            return (isset($module['alias']) && $module['alias'] === self::MODULE_ALIAS) ? true : $show;
        }, 20, 2);

        \Eventy::addFilter('modules.license_info', function ($license_info, $module_alias) {
            if ($module_alias !== self::MODULE_ALIAS) {
                return $license_info;
            }
            $status = app(LicenseService::class)->getLicenseStatus();
            return [
                'license'      => $status['license_key'] ?? '',
                'activated'    => $status['valid'] ?? false,
                'status'       => $status['status'] ?? 'inactive',
                'expires_at'   => $status['expires_at'] ?? null,
                'license_type' => $status['license_type'] ?? null,
            ];
        }, 20, 2);

        \Eventy::addFilter('module.requires_license', function ($requires, $module) {
            return (isset($module['alias']) && $module['alias'] === self::MODULE_ALIAS) ? true : $requires;
        }, 20, 2);

        \Eventy::addFilter('schedule', function ($schedule) {
            // Licence re-check every 6 hours (as the other MFS modules).
            $schedule->call(function () {
                $service = app(LicenseService::class);
                $status  = $service->getLicenseStatus();
                if (!empty($status['license_key'])) {
                    $service->validateLicense($status['license_key']);
                }
            })->cron('15 */6 * * *');
            return $schedule;
        }, 20, 1);
    }

    /** Top menu "Assets" (v1 item 4) and the "Manage assets" permission. */
    protected function registerMenuAndPermissions()
    {
        \App\Misc\Helper::$menu['mfsassets'] = ['mfsassets.index', 'mfsassets.show', 'mfsassets.create', 'mfsassets.types'];

        \Eventy::addAction('menu.append', function () {
            if (!self::enabled() || !Auth::check()) {
                return;
            }
            echo '<li class="'.\App\Misc\Helper::menuSelectedHtml('mfsassets').'"><a href="'.route('mfsassets.index').'">'.e(__('Assets')).'</a></li>';
        });

        \Eventy::addFilter('user_permissions.list', function ($list) {
            $list[] = self::PERM_MANAGE;
            return $list;
        });

        \Eventy::addFilter('user_permissions.name', function ($name, $permission) {
            return $permission === self::PERM_MANAGE ? __('Users are allowed to manage assets') : $name;
        }, 20, 2);
    }

    protected function registerAssets()
    {
        \Eventy::addFilter('stylesheets', function ($items) {
            if (self::enabled()) {
                $items[] = \Module::getPublicPath(self::MODULE_ALIAS).'/css/mfsassets.css';
            }
            return $items;
        });
        \Eventy::addFilter('javascripts', function ($items) {
            if (self::enabled()) {
                $items[] = \Module::getPublicPath(self::MODULE_ALIAS).'/js/mfsassets.js';
            }
            return $items;
        });
    }
}
