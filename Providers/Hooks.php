<?php

namespace Modules\MFSAssets\Providers;

use Illuminate\Support\Facades\Auth;
use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Entities\History;
use Modules\MFSAssets\Providers\MFSAssetsServiceProvider as Provider;

/**
 * Where MFS Assets shows up in FreeScout and MFS Organisations (card #426, milestone 3):
 * the Assets block on a conversation, the Assets tab on the customer page and on the
 * organisation page, and the GDPR rule for deleted customers.
 */
class Hooks
{
    /** @var array conversation_id => [tags], filled once per list page (badges). */
    public static $list_tags = [];

    /** Visible assets whose tag or serial number is exactly $q (global search, v1 item 8). */
    protected static function assetIdsMatching($q)
    {
        $q = trim((string) $q);
        if ($q === '' || mb_strlen($q) < 3 || !Auth::check()) {
            return [];
        }
        return Asset::visibleTo(Auth::user())->where(function ($w) use ($q) {
            $w->where('tag', $q)->orWhere('serial', $q);
        })->pluck('id')->all();
    }

    public static function register()
    {
        \Modules\MFSAssets\Services\Webhooks::register();

        // Search (v1 item 8): filter "Asset" (a tag), and a tag or serial number in the normal
        // search box also finds the conversations linked to that asset.
        \Eventy::addFilter('search.filters_list', function ($list) {
            if (Provider::enabled()) {
                $list[] = 'asset';
            }
            return $list;
        });
        \Eventy::addAction('search.display_filters', function ($filters, $filters_data, $mode) {
            if (!Provider::enabled() || $mode != \App\Conversation::SEARCH_MODE_CONV) {
                return;
            }
            echo view('mfsassets::partials.search_filter', ['filters' => $filters])->render();
        }, 30, 3);
        \Eventy::addFilter('search.conversations.apply_filters', function ($query, $filters, $q) {
            if (!empty($filters['asset']) && Provider::enabled()) {
                $ids = self::assetIdsMatching($filters['asset']) ?: [0];
                $query->whereIn('conversations.id', function ($sub) use ($ids) {
                    $sub->select('conversation_id')->from('mfsassets_conversations')->whereIn('asset_id', $ids);
                });
            }
            return $query;
        }, 20, 3);
        \Eventy::addFilter('search.conversations.or_where', function ($query, $filters, $q) {
            if (Provider::enabled() && ($ids = self::assetIdsMatching($q))) {
                $query->orWhereIn('conversations.id', function ($sub) use ($ids) {
                    $sub->select('conversation_id')->from('mfsassets_conversations')->whereIn('asset_id', $ids);
                });
            }
            return $query;
        }, 20, 3);

        // Badges in the conversation list and on Kanban cards (v1 item 8): the linked assets' tags.
        \Eventy::addFilter('conversations_table.preload_table_data', function ($conversations) {
            if (!Provider::enabled() || !count($conversations) || !Auth::check()) {
                return $conversations;
            }
            $ids = [];
            foreach ($conversations as $c) {
                $ids[] = $c->id;
            }
            $rows = \DB::table('mfsassets_conversations')->join('mfsassets_assets', 'mfsassets_assets.id', '=', 'mfsassets_conversations.asset_id')
                ->whereIn('mfsassets_conversations.conversation_id', $ids)
                ->orderBy('mfsassets_assets.tag')
                ->get(['mfsassets_conversations.conversation_id', 'mfsassets_assets.tag', 'mfsassets_assets.owner_id']);
            $visible = Auth::user()->isAdmin() ? null : array_flip(\Modules\MFSOrganisations\Entities\Organisation::visibleTo(Auth::user())->pluck('id')->all());
            foreach ($rows as $r) {
                if ($visible === null || isset($visible[$r->owner_id])) {
                    self::$list_tags[$r->conversation_id][] = $r->tag;
                }
            }
            return $conversations;
        }, 30);
        \Eventy::addAction('conversations_table.after_subject', function ($conversation) {
            $tags = self::$list_tags[$conversation->id] ?? [];
            if (!$tags) {
                return;
            }
            $shown = array_slice($tags, 0, 2);
            echo ' <span class="mfsassets-list-badge" title="'.e(__('Linked assets').': '.implode(', ', $tags)).'">'
                .e(implode(' ', $shown)).(count($tags) > 2 ? ' +'.(count($tags) - 2) : '').'</span>';
        });

        // Under the Organisations block (that one uses the default priority 20).
        \Eventy::addAction('conversation.after_customer_sidebar', function ($conversation) {
            self::renderConversationBlock($conversation);
        }, 30);

        \Eventy::addAction('customers.profile_tabs.append', function () {
            $id = (int) request()->route('id');
            if (!$id || !Provider::enabled()) {
                return;
            }
            $active = \Route::currentRouteName() == 'mfsassets.customer' ? ' class="active"' : '';
            echo '<li'.$active.'><a href="'.route('mfsassets.customer', ['id' => $id]).'">'.e(__('Assets')).'</a></li>';
        });

        \Eventy::addFilter('mfsorganisations.org_tabs', function ($tabs, $org) {
            if (Provider::enabled()) {
                $tabs['assets'] = [
                    'title' => __('Assets'),
                    'icon'  => 'hdd',
                    'count' => Asset::where('owner_id', $org->id)->whereNotIn('status', ['retired', 'disposed'])->count(),
                ];
            }
            return $tabs;
        }, 20, 2);

        \Eventy::addAction('mfsorganisations.org_tab', function ($tab, $org) {
            if ($tab != 'assets' || !Provider::enabled()) {
                return;
            }
            $user = Auth::user();
            $owned = Asset::visibleTo($user)->with('type', 'location')->where('owner_id', $org->id)->orderBy('tag')->get();
            // Others' assets at this organisation's locations (e.g. a provider's router at a client).
            $loc_ids = \Modules\MFSOrganisations\Entities\Location::where('organisation_id', $org->id)->pluck('id')->all();
            $here = $loc_ids ? Asset::visibleTo($user)->with('type', 'location', 'owner')->whereIn('location_id', $loc_ids)
                ->where('owner_id', '!=', $org->id)->orderBy('tag')->get() : collect();
            echo view('mfsassets::partials.org_tab', [
                'org' => $org, 'owned' => $owned, 'here' => $here, 'can_manage' => Provider::canManage(),
            ])->render();
        }, 20, 2);

        // GDPR (spec "Technical"): deleting a customer unlinks its assets, it never deletes them.
        \Eventy::addAction('customer.deleting', function ($customer) {
            if (!Provider::organisationsAvailable()) {
                return;
            }
            $emails = array_map('strtolower', $customer->emails()->pluck('email')->all());
            foreach (Asset::whereIn('assigned_email', $emails ?: [''])->get() as $asset) {
                $old = $asset->assigned_email;
                $asset->assigned_email = null;
                $asset->save();
                History::log($asset, 'assigned', ['assigned_email' => [$old, null], 'reason' => __('customer deleted')]);
            }
        });
    }

    /**
     * Assets block (v1 item 6): assets linked to this conversation, then the customer's own,
     * their department's and the shared ones of the conversation's organisation, each with a
     * one-click Link; a search; "New asset" (created and linked in one go).
     */
    public static function renderConversationBlock($conversation)
    {
        static $done = [];
        if (isset($done[$conversation->id]) || !Provider::enabled() || !Auth::check()) {
            return;
        }
        $done[$conversation->id] = true;
        $user = Auth::user();
        $email = strtolower((string) $conversation->customer_email);
        $orgRec = \Modules\MFSOrganisations\Entities\OrgConversation::where('conversation_id', $conversation->id)->first();
        $org_id = $orgRec ? (int) $orgRec->organisation_id : 0;
        $link = $email ? \Modules\MFSOrganisations\Entities\AddressLink::where('email', $email)->first() : null;
        $dept_id = ($link && $link->organisation_id == $org_id) ? (int) $link->department_id : 0;

        $linked = Asset::visibleTo($user)->with('type')->whereIn('id', function ($q) use ($conversation) {
            $q->select('asset_id')->from('mfsassets_conversations')->where('conversation_id', $conversation->id);
        })->orderBy('tag')->get();
        $linked_ids = $linked->pluck('id')->all();

        // One query for the suggestions: own (assigned to this address), department, shared.
        $suggest = collect();
        if ($email || $org_id) {
            $suggest = Asset::visibleTo($user)->with('type')
                ->whereNotIn('status', ['retired', 'disposed'])
                ->whereNotIn('id', $linked_ids ?: [0])
                ->where(function ($w) use ($email, $org_id, $dept_id) {
                    if ($email) {
                        $w->orWhere('assigned_email', $email);
                    }
                    if ($dept_id) {
                        $w->orWhere('assigned_department_id', $dept_id);
                    }
                    if ($org_id) {
                        $w->orWhere(function ($s) use ($org_id) {
                            $s->where('owner_id', $org_id)->whereNull('assigned_email')->whereNull('assigned_department_id');
                        });
                    }
                })->orderBy('tag')->limit(30)->get();
        }
        $groups = ['own' => [], 'department' => [], 'shared' => []];
        foreach ($suggest as $a) {
            $key = $a->assigned_email == $email && $email ? 'own' : ($a->assigned_department_id && $a->assigned_department_id == $dept_id ? 'department' : 'shared');
            $groups[$key][] = $a;
        }

        echo view('mfsassets::partials.conversation_block', [
            'conversation' => $conversation,
            'linked'       => $linked,
            'groups'       => $groups,
            'org_id'       => $org_id,
            'email'        => $email,
            'can_manage'   => Provider::canManage(),
        ])->render();
    }
}
