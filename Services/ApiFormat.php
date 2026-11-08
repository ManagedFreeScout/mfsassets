<?php

namespace Modules\MFSAssets\Services;

use Modules\MFSAssets\Entities\Asset;

/** How the API and the webhooks show assets: one format for both (card #426), as in MFS Organisations. */
class ApiFormat
{
    public static function date($date)
    {
        return $date ? $date->copy()->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z') : null;
    }

    public static function asset(Asset $a)
    {
        return [
            'id'                   => $a->id,
            'tag'                  => $a->tag,
            'name'                 => $a->name,
            'typeId'               => $a->type_id,
            'type'                 => $a->type ? $a->type->name : null,
            'status'               => $a->status,
            'ownerId'              => $a->owner_id,
            'owner'                => $a->owner ? ['id' => $a->owner->id, 'name' => $a->owner->name] : null,
            'assignedEmail'        => $a->assigned_email,
            'assignedDepartmentId' => $a->assigned_department_id,
            'locationId'           => $a->location_id,
            'locationPath'         => $a->location ? $a->location->path() : null,
            'serial'               => $a->serial,
            'supplier'             => $a->supplier,
            'purchaseDate'         => $a->purchase_date ? $a->purchase_date->toDateString() : null,
            'purchasePrice'        => $a->purchase_price !== null ? (float) $a->purchase_price : null,
            'warrantyEnd'          => $a->warranty_end ? $a->warranty_end->toDateString() : null,
            'notes'                => $a->notes,
            'fields'               => (object) $a->values(),
            'webUrl'               => $a->url(),
            'createdAt'            => self::date($a->created_at),
            'updatedAt'            => self::date($a->updated_at),
        ];
    }

    /** Short form inside other payloads (conversation webhooks). */
    public static function ref(Asset $a)
    {
        return ['id' => $a->id, 'tag' => $a->tag, 'name' => $a->name];
    }
}
