<?php

namespace Modules\MFSAssets\Entities;

use Illuminate\Database\Eloquent\Model;

/** A file on an asset (invoice, warranty card, photo). Stored on FreeScout's private disk. */
class Attachment extends Model
{
    protected $table = 'mfsassets_attachments';

    public $timestamps = false;

    protected $fillable = ['asset_id', 'file_name', 'mime_type', 'size', 'path', 'user_id', 'created_at'];

    protected $dates = ['created_at'];

    const DIR = 'mfsassets';
    const MAX_BYTES = 15728640;
}
