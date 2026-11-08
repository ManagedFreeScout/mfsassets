<?php

namespace Modules\MFSAssets\Entities;

use Illuminate\Database\Eloquent\Model;

/** Audit trail of an asset (card #426, v1 item 9): created, changes of hands, moves, status, links. */
class History extends Model
{
    protected $table = 'mfsassets_history';

    public $timestamps = false;

    protected $fillable = ['asset_id', 'user_id', 'action', 'changes', 'created_at'];

    protected $dates = ['created_at'];

    public static function log(Asset $asset, $action, array $changes = [], $user_id = null)
    {
        return self::create([
            'asset_id'   => $asset->id,
            'user_id'    => $user_id ?? (\Auth::check() ? \Auth::id() : null),
            'action'     => $action,
            'changes'    => $changes ? json_encode($changes) : null,
            'created_at' => now(),
        ]);
    }

    public function changeList()
    {
        // Not $this->changes: that is Eloquent's own list of changed attributes.
        $c = json_decode((string) $this->getAttribute('changes'), true);
        return is_array($c) ? $c : [];
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }
}
