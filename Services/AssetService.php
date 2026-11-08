<?php

namespace Modules\MFSAssets\Services;

use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Entities\AssetType;
use Modules\MFSAssets\Entities\History;
use Modules\MFSOrganisations\Entities\Department;
use Modules\MFSOrganisations\Entities\Location;
use Modules\MFSOrganisations\Entities\Organisation;

/**
 * One place that checks and saves an asset, for the screens and the API alike (card #426), and
 * writes the history: created, assigned (changed hands), moved, status_changed, updated.
 */
class AssetService
{
    // History actions; the API/webhooks use the same names (asset.assigned, asset.moved, ...).
    const TRACKED = [
        'assigned'       => ['assigned_email', 'assigned_department_id'],
        'moved'          => ['location_id'],
        'status_changed' => ['status'],
    ];

    /**
     * $in uses the form/API field names (snake_case): name, type_id, status, owner_id, assigned_to
     * ("email:x@y", "department:5" or "none"), location_id, serial, supplier, purchase_date,
     * purchase_price, warranty_end, notes, fields[key]. Only keys present are changed ($partial).
     * Returns [errors(field => message), saved?].
     */
    /** Organisations this user may see; no user = the API's main key = all (as in MFS Organisations). */
    public static function orgs($user)
    {
        return $user ? Organisation::visibleTo($user) : Organisation::query();
    }

    public static function save(Asset $asset, array $in, $user, $partial = false)
    {
        $errors = [];
        $isNew = !$asset->exists;
        $has = function ($k) use ($in, $partial) {
            return !$partial || array_key_exists($k, $in);
        };

        if ($has('name')) {
            $asset->name = mb_substr(trim((string) ($in['name'] ?? '')), 0, 191);
            if ($asset->name === '') {
                $errors['name'] = __('Name is required');
            }
        }
        if ($has('type_id')) {
            $type_id = (int) ($in['type_id'] ?? 0);
            $asset->type_id = ($type_id && AssetType::where('id', $type_id)->exists()) ? $type_id : null;
            if ($type_id && !$asset->type_id) {
                $errors['type_id'] = __('Unknown type');
            }
        }
        if ($has('status')) {
            $status = (string) ($in['status'] ?? 'in_use');
            if (!in_array($status, Asset::STATUSES)) {
                $errors['status'] = __('Status must be one of: :list', ['list' => implode(', ', Asset::STATUSES)]);
            } else {
                $asset->status = $status;
            }
        }
        if ($has('owner_id')) {
            $owner = (int) ($in['owner_id'] ?? 0);
            $visible = $owner ? self::orgs($user)->where('id', $owner)->exists() : false;
            if (!$owner || !$visible) {
                $errors['owner_id'] = __('Choose the owner: an organisation you can see');
            } else {
                $asset->owner_id = $owner;
            }
        }
        if ($has('assigned_to')) {
            $to = trim((string) ($in['assigned_to'] ?? 'none'));
            if (stripos($to, 'email:') === 0) {
                $email = strtolower(trim(substr($to, 6)));
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors['assigned_to'] = __('Not a valid e-mail address');
                } else {
                    $asset->assigned_email = $email;
                    $asset->assigned_department_id = null;
                }
            } elseif (stripos($to, 'department:') === 0) {
                $dept = (int) substr($to, 11);
                if (!$dept || !Department::where('id', $dept)->where('organisation_id', $asset->owner_id)->exists()) {
                    $errors['assigned_to'] = __('The department must belong to the owner');
                } else {
                    $asset->assigned_department_id = $dept;
                    $asset->assigned_email = null;
                }
            } else {
                // Nobody: shared, on purpose.
                $asset->assigned_email = null;
                $asset->assigned_department_id = null;
            }
        }
        if ($has('location_id')) {
            $loc = (int) ($in['location_id'] ?? 0);
            if ($loc) {
                $l = Location::find($loc);
                $ok = $l && self::orgs($user)->where('id', $l->organisation_id)->exists();
                if (!$ok) {
                    $errors['location_id'] = __('Unknown location');
                } else {
                    $asset->location_id = $loc;
                }
            } else {
                $asset->location_id = null;
            }
        }
        foreach (['serial' => 191, 'supplier' => 191, 'notes' => 20000] as $k => $max) {
            if ($has($k)) {
                $v = trim((string) ($in[$k] ?? ''));
                $asset->$k = $v === '' ? null : mb_substr($v, 0, $max);
            }
        }
        foreach (['purchase_date', 'warranty_end'] as $k) {
            if ($has($k)) {
                $v = trim((string) ($in[$k] ?? ''));
                if ($v === '') {
                    $asset->$k = null;
                } else {
                    try {
                        $asset->$k = \Carbon\Carbon::parse($v)->toDateString();
                    } catch (\Exception $e) {
                        $errors[$k] = __('Not a valid date');
                    }
                }
            }
        }
        if ($has('purchase_price')) {
            $v = str_replace(',', '.', trim((string) ($in['purchase_price'] ?? '')));
            if ($v === '') {
                $asset->purchase_price = null;
            } elseif (!is_numeric($v) || $v < 0 || $v > 9999999999) {
                $errors['purchase_price'] = __('Not a valid price');
            } else {
                // Same notation as the database ("1249.50"), so an unchanged price is not a change.
                $asset->purchase_price = number_format(round((float) $v, 2), 2, '.', '');
            }
        }
        if (array_key_exists('fields', $in) && is_array($in['fields'])) {
            $type = $asset->type_id ? AssetType::find($asset->type_id) : null;
            $values = $asset->values();
            foreach ($type ? $type->fieldList() : [] as $f) {
                if (!array_key_exists($f['key'], $in['fields']) && $f['type'] != 'checkbox') {
                    continue;
                }
                $v = $in['fields'][$f['key']] ?? null;
                if ($f['type'] == 'checkbox') {
                    $values[$f['key']] = filter_var($v, FILTER_VALIDATE_BOOLEAN);
                } elseif ($f['type'] == 'select') {
                    $values[$f['key']] = in_array($v, $f['options'] ?? [], true) ? $v : null;
                } elseif ($f['type'] == 'number') {
                    $v = str_replace(',', '.', trim((string) $v));
                    $values[$f['key']] = $v === '' ? null : (is_numeric($v) ? $v + 0 : null);
                } elseif ($f['type'] == 'date') {
                    $values[$f['key']] = trim((string) $v) === '' ? null : (strtotime($v) ? date('Y-m-d', strtotime($v)) : null);
                } else {
                    $values[$f['key']] = trim((string) $v) === '' ? null : mb_substr(trim((string) $v), 0, 1000);
                }
            }
            // Empty fields are left out and keys sorted, so saving without changes is not a change.
            $norm = function ($v) {
                $v = array_filter((array) $v, function ($x) { return $x !== null; });
                ksort($v);
                return json_encode($v);
            };
            $json = $norm($values);
            if ($json !== $norm($asset->values())) {
                $asset->field_values = $json;
            }
        }
        if ($isNew) {
            if (!$asset->owner_id && !isset($errors['owner_id'])) {
                $errors['owner_id'] = __('Choose the owner: an organisation you can see');
            }
            $asset->status = $asset->status ?: 'in_use';
        }
        if ($errors) {
            return [$errors, false];
        }

        $dirty = $asset->getDirty();
        $before = [];
        foreach ($dirty as $k => $v) {
            $before[$k] = $asset->getOriginal($k);
        }
        if ($isNew) {
            $asset->tag = Asset::nextTag();
            $asset->qr_token = Asset::newToken();
            $asset->created_by = $user ? $user->id : null;
        }
        if (!$isNew && !$dirty) {
            return [[], true];
        }
        $asset->save();

        if ($isNew) {
            History::log($asset, 'created', ['tag' => $asset->tag], $user ? $user->id : null);
            \Eventy::action('mfsassets.asset.created', $asset);
            return [[], true];
        }
        $events = [];
        $rest = $dirty;
        foreach (self::TRACKED as $action => $cols) {
            $changes = [];
            foreach ($cols as $c) {
                if (array_key_exists($c, $dirty)) {
                    $changes[$c] = [$before[$c], $asset->$c];
                    unset($rest[$c]);
                }
            }
            if ($changes) {
                History::log($asset, $action, $changes, $user ? $user->id : null);
                $events[] = $action;
            }
        }
        unset($rest['updated_at']);
        if ($rest) {
            $changes = [];
            foreach (array_keys($rest) as $c) {
                $changes[$c] = [$before[$c] ?? null, $asset->$c];
            }
            History::log($asset, 'updated', $changes, $user ? $user->id : null);
        }
        \Eventy::action('mfsassets.asset.updated', $asset, $events);
        return [[], true];
    }

    /** Human label of a history value (for the asset page). */
    public static function displayValue($field, $value)
    {
        if ($value === null || $value === '') {
            return '–';
        }
        switch ($field) {
            case 'status':
                return Asset::statuses()[$value] ?? $value;
            case 'location_id':
                $l = Location::find($value);
                return $l ? $l->path() : '#'.$value;
            case 'assigned_department_id':
                $d = Department::find($value);
                return $d ? __('Department').': '.$d->name : '#'.$value;
            case 'owner_id':
                $o = Organisation::find($value);
                return $o ? $o->name : '#'.$value;
            case 'type_id':
                $t = AssetType::find($value);
                return $t ? $t->name : '#'.$value;
            case 'field_values':
                return __('fields changed');
        }
        return is_scalar($value) ? (string) $value : json_encode($value);
    }

    public static function fieldName($field)
    {
        $names = [
            'name' => __('Name'), 'type_id' => __('Type'), 'status' => __('Status'), 'owner_id' => __('Owner'),
            'assigned_email' => __('Assigned to'), 'assigned_department_id' => __('Assigned to'), 'location_id' => __('Location'),
            'serial' => __('Serial number'), 'supplier' => __('Supplier'), 'purchase_date' => __('Purchase date'),
            'purchase_price' => __('Purchase price'), 'warranty_end' => __('Warranty until'), 'notes' => __('Notes'),
            'field_values' => __('Fields'), 'conversation' => __('Conversation'), 'tag' => __('Tag'),
        ];
        return $names[$field] ?? $field;
    }

    /** Owner first, then the other organisations the agent may see: [org name => [location id => path]]. */
    public static function locationOptions($user, $owner_id = null)
    {
        $orgs = self::orgs($user)->orderBy('name')->get(['id', 'name']);
        $out = [];
        $sorted = $orgs->sortBy(function ($o) use ($owner_id) {
            return ($o->id == $owner_id ? '0' : '1').mb_strtolower($o->name);
        });
        foreach ($sorted as $o) {
            $tree = Location::treeOptions($o->id);
            if ($tree) {
                $out[$o->name] = $tree;
            }
        }
        return $out;
    }
}
