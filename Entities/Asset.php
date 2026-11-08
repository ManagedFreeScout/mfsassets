<?php

namespace Modules\MFSAssets\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\MFSOrganisations\Entities\Department;
use Modules\MFSOrganisations\Entities\Location;
use Modules\MFSOrganisations\Entities\Organisation;

/**
 * An asset (card #426). The model answers three questions: whose is it (owner, an organisation),
 * who uses it (an address, a department, or nobody on purpose) and where is it (a location, or
 * none for something digital).
 */
class Asset extends Model
{
    protected $table = 'mfsassets_assets';

    protected $fillable = [
        'tag', 'name', 'type_id', 'status', 'owner_id', 'assigned_email', 'assigned_department_id', 'location_id',
        'serial', 'supplier', 'purchase_date', 'purchase_price', 'warranty_end', 'notes', 'field_values', 'qr_token', 'created_by',
    ];

    protected $dates = ['purchase_date', 'warranty_end'];

    const OPTION_NEXT_TAG = 'mfsassets.next_tag';
    const OPTION_TAG_PREFIX = 'mfsassets.tag_prefix';

    // Lifecycle (v1 item 2), in order.
    const STATUSES = ['ordered', 'in_stock', 'in_use', 'in_repair', 'retired', 'disposed'];

    public static function statuses()
    {
        return [
            'ordered'   => __('Ordered'),
            'in_stock'  => __('In stock'),
            'in_use'    => __('In use'),
            'in_repair' => __('In repair'),
            'retired'   => __('Retired'),
            'disposed'  => __('Disposed'),
        ];
    }

    public function statusName()
    {
        return self::statuses()[$this->status] ?? $this->status;
    }

    public function type()
    {
        return $this->belongsTo(AssetType::class, 'type_id');
    }

    public function owner()
    {
        return $this->belongsTo(Organisation::class, 'owner_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    public function values()
    {
        $v = json_decode((string) $this->field_values, true);
        return is_array($v) ? $v : [];
    }

    public function url()
    {
        return route('mfsassets.show', ['id' => $this->id]);
    }

    /** "Julia Schmidt (julia@acme.com)", "Department: Finance", or "Shared (nobody)". */
    public function assignedLabel()
    {
        if ($this->assigned_email) {
            $customer = \App\Customer::getByEmail($this->assigned_email);
            $name = $customer ? trim($customer->getFullName()) : '';
            return $name ? $name.' ('.$this->assigned_email.')' : $this->assigned_email;
        }
        if ($this->assigned_department_id && $this->department) {
            return __('Department').': '.$this->department->name;
        }
        return __('Shared (nobody)');
    }

    /** Assets whose owner organisation the agent may see (visibility follows the owner's mailboxes). */
    public static function visibleTo($user)
    {
        $query = self::query();
        if ($user && !$user->isAdmin()) {
            $query->whereIn('owner_id', Organisation::visibleTo($user)->pluck('id')->all() ?: [0]);
        }
        return $query;
    }

    public function isVisibleTo($user)
    {
        return $user && ($user->isAdmin() || Organisation::visibleTo($user)->where('id', $this->owner_id)->exists());
    }

    public static function tagPrefix()
    {
        $p = \Option::get(self::OPTION_TAG_PREFIX, '');
        return $p !== '' ? $p : config('mfsassets.tag_prefix');
    }

    /** Next free tag, AST-0001 style; the counter only goes up, so a deleted tag is never reused. */
    public static function nextTag()
    {
        return \DB::transaction(function () {
            $n = max(1, (int) \Option::get(self::OPTION_NEXT_TAG, 1));
            do {
                $tag = self::tagPrefix().str_pad($n, config('mfsassets.tag_digits', 4), '0', STR_PAD_LEFT);
                $n++;
            } while (self::where('tag', $tag)->exists());
            \Option::set(self::OPTION_NEXT_TAG, $n);
            return $tag;
        });
    }

    public static function newToken()
    {
        return bin2hex(random_bytes(16));
    }

    /** Assets linked to a conversation. */
    public static function ofConversation($conversation_id)
    {
        return self::whereIn('id', function ($q) use ($conversation_id) {
            $q->select('asset_id')->from('mfsassets_conversations')->where('conversation_id', $conversation_id);
        });
    }

    public function conversationIds()
    {
        return \DB::table('mfsassets_conversations')->where('asset_id', $this->id)->pluck('conversation_id')->all();
    }

    public function link($conversation_id, $user_id = null)
    {
        $exists = \DB::table('mfsassets_conversations')->where('asset_id', $this->id)->where('conversation_id', $conversation_id)->exists();
        if (!$exists) {
            \DB::table('mfsassets_conversations')->insert(['asset_id' => $this->id, 'conversation_id' => $conversation_id, 'linked_by' => $user_id, 'created_at' => now()]);
            History::log($this, 'linked', ['conversation' => (int) $conversation_id], $user_id);
            \Eventy::action('mfsassets.asset.linked', $this, (int) $conversation_id);
        }
        return !$exists;
    }

    public function unlink($conversation_id, $user_id = null)
    {
        $n = \DB::table('mfsassets_conversations')->where('asset_id', $this->id)->where('conversation_id', $conversation_id)->delete();
        if ($n) {
            History::log($this, 'unlinked', ['conversation' => (int) $conversation_id], $user_id);
            \Eventy::action('mfsassets.asset.unlinked', $this, (int) $conversation_id);
        }
        return (bool) $n;
    }
}
