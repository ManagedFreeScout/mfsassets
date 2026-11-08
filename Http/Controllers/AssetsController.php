<?php

namespace Modules\MFSAssets\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Entities\AssetType;
use Modules\MFSAssets\Entities\Attachment;
use Modules\MFSAssets\Entities\History;
use Modules\MFSAssets\Providers\MFSAssetsServiceProvider as Provider;
use Modules\MFSAssets\Services\AssetService;
use Modules\MFSOrganisations\Entities\Department;
use Modules\MFSOrganisations\Entities\Organisation;

/** Assets: list, page, create/edit, delete, files, link to a conversation (card #426). */
class AssetsController extends Controller
{
    const PER_PAGE = 50;

    const SORTS = ['tag', 'name', 'status', 'warranty_end', 'updated_at'];

    protected function guard()
    {
        if (!Provider::enabled()) {
            abort(404);
        }
    }

    protected function guardManage()
    {
        $this->guard();
        if (!Provider::canManage()) {
            abort(403);
        }
    }

    protected function findVisible($id)
    {
        $asset = Asset::findOrFail($id);
        if (!$asset->isVisibleTo(Auth::user())) {
            abort(404);
        }
        return $asset;
    }

    /** The list (v1 item 4): search, filters, sorting. */
    public function index(Request $request)
    {
        $this->guard();
        $user = Auth::user();
        $query = Asset::visibleTo($user)->with('type', 'owner', 'location');
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('tag', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial', 'like', $like)
                    ->orWhere('assigned_email', 'like', $like)->orWhere('supplier', 'like', $like);
            });
        }
        foreach (['type_id', 'owner_id', 'location_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, (int) $request->input($f));
            }
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif (!$request->filled('all')) {
            // Retired and disposed assets only when asked for.
            $query->whereNotIn('status', ['retired', 'disposed']);
        }
        if ($request->filled('email')) {
            $query->where('assigned_email', strtolower($request->input('email')));
        }
        $sort = in_array($request->input('sort'), self::SORTS) ? $request->input('sort') : 'tag';
        $dir = $request->input('dir') == 'desc' ? 'desc' : 'asc';
        $assets = $query->orderBy($sort, $dir)->paginate(self::PER_PAGE)->appends($request->except('page'));

        return view('mfsassets::assets.index', [
            'assets'     => $assets,
            'types'      => AssetType::orderBy('name')->get(),
            'orgs'       => Organisation::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'statuses'   => Asset::statuses(),
            'sort'       => $sort,
            'dir'        => $dir,
            'can_manage' => Provider::canManage(),
        ]);
    }

    public function create(Request $request)
    {
        $this->guardManage();
        $asset = new Asset([
            'owner_id' => (int) $request->input('owner_id') ?: Provider::ourOrgId(),
            'status'   => 'in_use',
            'type_id'  => (int) $request->input('type_id') ?: null,
            'assigned_email' => $request->input('email') ? strtolower($request->input('email')) : null,
        ]);
        return $this->form($asset, (int) $request->input('conversation_id'));
    }

    protected function form(Asset $asset, $conversation_id = 0)
    {
        $user = Auth::user();
        return view('mfsassets::assets.form', [
            'asset'       => $asset,
            'types'       => AssetType::orderBy('name')->get(),
            'orgs'        => Organisation::visibleTo($user)->orderBy('name')->get(['id', 'name']),
            'statuses'    => Asset::statuses(),
            'departments' => $asset->owner_id ? Department::treeOptions($asset->owner_id) : [],
            'locations'   => AssetService::locationOptions($user, $asset->owner_id),
            'conversation_id' => $conversation_id,
        ]);
    }

    public function store(Request $request)
    {
        $this->guardManage();
        $asset = new Asset();
        list($errors) = AssetService::save($asset, $this->input($request), Auth::user());
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        // Created from a conversation (v1 item 6): link it right away.
        if ($cid = (int) $request->input('conversation_id')) {
            $conv = \App\Conversation::find($cid);
            if ($conv && Auth::user()->can('view', $conv)) {
                $asset->link($conv->id, Auth::id());
                \Session::flash('flash_success_floating', __(':tag created and linked to the conversation.', ['tag' => $asset->tag]));
                return redirect()->to($conv->url());
            }
        }
        \Session::flash('flash_success_floating', __(':tag created.', ['tag' => $asset->tag]));
        return redirect()->to($asset->url());
    }

    protected function input(Request $request)
    {
        $in = $request->only(['name', 'type_id', 'status', 'owner_id', 'location_id', 'serial', 'supplier', 'purchase_date', 'purchase_price', 'warranty_end', 'notes']);
        $mode = $request->input('assigned_mode', 'none');
        $in['assigned_to'] = $mode == 'email' ? 'email:'.$request->input('assigned_email')
            : ($mode == 'department' ? 'department:'.$request->input('assigned_department_id') : 'none');
        $in['fields'] = (array) $request->input('fields', []);
        return $in;
    }

    const CSV_COLUMNS = ['tag', 'name', 'type', 'status', 'owner', 'assigned_email', 'assigned_department', 'location', 'serial', 'supplier', 'purchase_date', 'purchase_price', 'warranty_end', 'notes', 'fields'];

    /** CSV of the list as filtered (v1 item 10); with ?email= for one person's assets (GDPR). */
    public function export(Request $request)
    {
        $this->guard();
        $query = Asset::visibleTo(Auth::user())->with('type', 'owner', 'location', 'department')->orderBy('tag');
        foreach (['type_id', 'owner_id', 'location_id'] as $f) {
            if ($request->filled($f)) {
                $query->where($f, (int) $request->input($f));
            }
        }
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif (!$request->filled('all')) {
            $query->whereNotIn('status', ['retired', 'disposed']);
        }
        if ($request->filled('email')) {
            $query->where('assigned_email', strtolower($request->input('email')));
        }
        if ($q = trim((string) $request->input('q'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('tag', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial', 'like', $like)->orWhere('assigned_email', 'like', $like);
            });
        }
        return response()->stream(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::CSV_COLUMNS);
            $query->chunk(500, function ($assets) use ($out) {
                foreach ($assets as $a) {
                    fputcsv($out, [
                        $a->tag, $a->name, $a->type ? $a->type->name : '', $a->status, $a->owner ? $a->owner->name : '',
                        $a->assigned_email, $a->department ? $a->department->name : '', $a->location ? $a->location->path() : '',
                        $a->serial, $a->supplier, $a->purchase_date ? $a->purchase_date->toDateString() : '', $a->purchase_price,
                        $a->warranty_end ? $a->warranty_end->toDateString() : '', $a->notes, $a->values() ? json_encode($a->values()) : '',
                    ]);
                }
            });
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="assets-'.date('Y-m-d').'.csv"',
        ]);
    }

    /** CSV import (v1 item 10), Organisations conventions: header line, ; or , and an existing tag updates. */
    public function import(Request $request)
    {
        $this->guardManage();
        $file = $request->file('csv');
        if (!$file || !$file->isValid()) {
            \Session::flash('flash_error_floating', __('Choose a CSV file.'));
            return redirect()->route('mfsassets.index');
        }
        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $first = preg_replace('/^\xEF\xBB\xBF/', '', array_shift($lines) ?? '');
        $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
        $header = array_map(function ($h) { return strtolower(trim($h)); }, str_getcsv($first, $sep));
        if (!in_array('name', $header) && !in_array('tag', $header)) {
            \Session::flash('flash_error_floating', __('The first line must hold the column names, with at least "name".'));
            return redirect()->route('mfsassets.index');
        }
        $user = Auth::user();
        $orgs = Organisation::visibleTo($user)->get(['id', 'name']);
        $created = $updated = 0;
        $problems = [];
        foreach (array_slice($lines, 0, 5000) as $i => $line) {
            $row = array_combine($header, array_pad(array_slice(str_getcsv($line, $sep), 0, count($header)), count($header), ''));
            $tag = trim($row['tag'] ?? '');
            $asset = $tag !== '' ? Asset::visibleTo($user)->where('tag', $tag)->first() : null;
            if ($tag !== '' && !$asset) {
                $problems[] = __('Line :n: tag :tag not found', ['n' => $i + 2, 'tag' => $tag]);
                continue;
            }
            $isNew = !$asset;
            $asset = $asset ?: new Asset();
            $in = [];
            foreach (['name', 'serial', 'supplier', 'purchase_date', 'purchase_price', 'warranty_end', 'notes', 'status'] as $k) {
                if (array_key_exists($k, $row) && ($row[$k] !== '' || !$isNew)) {
                    $in[$k] = $row[$k];
                }
            }
            if (array_key_exists('type', $row) && $row['type'] !== '') {
                $type = AssetType::where('name', trim($row['type']))->first();
                $in['type_id'] = $type ? $type->id : -1;
            }
            $owner_id = $asset->owner_id;
            if (array_key_exists('owner', $row) && trim($row['owner']) !== '') {
                $o = $orgs->first(function ($o) use ($row) { return mb_strtolower($o->name) == mb_strtolower(trim($row['owner'])); });
                $owner_id = $o ? $o->id : -1;
            } elseif ($isNew) {
                $owner_id = Provider::ourOrgId();
            }
            $in['owner_id'] = $owner_id;
            if (!empty($row['assigned_email'])) {
                $in['assigned_to'] = 'email:'.$row['assigned_email'];
            } elseif (!empty($row['assigned_department']) && $owner_id > 0) {
                $d = array_search(mb_strtolower(trim($row['assigned_department'])), array_map('mb_strtolower', Department::treeOptions($owner_id)));
                $in['assigned_to'] = $d ? 'department:'.$d : 'department:0';
            }
            if (!empty($row['location'])) {
                $want = mb_strtolower(preg_replace('/\s*[›>]\s*/u', ' › ', trim($row['location'])));
                $found = 0;
                foreach (AssetService::locationOptions($user, $owner_id) as $tree) {
                    foreach ($tree as $lid => $path) {
                        if (mb_strtolower($path) == $want) {
                            $found = $lid;
                            break 2;
                        }
                    }
                }
                $in['location_id'] = $found ?: -1;
            }
            list($errors) = AssetService::save($asset, $in, $user, !$isNew);
            if ($errors) {
                $problems[] = __('Line :n: :error', ['n' => $i + 2, 'error' => implode('; ', $errors)]);
            } else {
                $isNew ? $created++ : $updated++;
            }
        }
        \Session::flash('flash_success_floating', __('Import: :c created, :u updated.', ['c' => $created, 'u' => $updated]));
        if ($problems) {
            \Session::flash('flash_warning', implode(' · ', array_slice($problems, 0, 20)).(count($problems) > 20 ? ' …' : ''));
        }
        return redirect()->route('mfsassets.index');
    }

    /** Asset page (v1 item 5): details, history, conversations, files. */
    public function show($id)
    {
        $this->guard();
        $asset = $this->findVisible($id);
        $user = Auth::user();
        $conversations = \App\Conversation::whereIn('id', $asset->conversationIds() ?: [0])
            ->whereIn('mailbox_id', $user->mailboxesIdsCanView())
            ->orderBy('last_reply_at', 'desc')->limit(100)->get();
        return view('mfsassets::assets.show', [
            'asset'         => $asset,
            'history'       => History::where('asset_id', $asset->id)->with('user')->orderBy('id', 'desc')->limit(200)->get(),
            'conversations' => $conversations,
            'files'         => Attachment::where('asset_id', $asset->id)->orderBy('id')->get(),
            'can_manage'    => Provider::canManage(),
            'is_admin'      => $user->isAdmin(),
        ]);
    }

    public function edit($id)
    {
        $this->guardManage();
        return $this->form($this->findVisible($id));
    }

    public function update(Request $request, $id)
    {
        $this->guardManage();
        $asset = $this->findVisible($id);
        list($errors) = AssetService::save($asset, $this->input($request), Auth::user());
        if ($errors) {
            return back()->withErrors($errors)->withInput();
        }
        \Session::flash('flash_success_floating', __('Saved.'));
        return redirect()->to($asset->url());
    }

    /** Admins only (v1 item 13). Links and history go too; nothing else refers to an asset. */
    public function destroy($id)
    {
        $this->guard();
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
        $asset = $this->findVisible($id);
        foreach (Attachment::where('asset_id', $asset->id)->get() as $f) {
            \Storage::disk('private')->delete($f->path);
            $f->delete();
        }
        \DB::table('mfsassets_conversations')->where('asset_id', $asset->id)->delete();
        History::where('asset_id', $asset->id)->delete();
        \Eventy::action('mfsassets.asset.deleting', $asset);
        $asset->delete();
        \Session::flash('flash_success_floating', __(':tag deleted.', ['tag' => $asset->tag]));
        return redirect()->route('mfsassets.index');
    }

    public function upload(Request $request, $id)
    {
        $this->guardManage();
        $asset = $this->findVisible($id);
        $file = $request->file('file');
        if (!$file || !$file->isValid() || $file->getSize() > Attachment::MAX_BYTES) {
            \Session::flash('flash_error_floating', __('Choose a file of at most 15 MB.'));
            return redirect()->to($asset->url());
        }
        $path = $file->storeAs(Attachment::DIR.'/'.$asset->id, bin2hex(random_bytes(12)).'.'.preg_replace('/[^a-z0-9]/', '', strtolower($file->getClientOriginalExtension())), 'private');
        Attachment::create([
            'asset_id' => $asset->id, 'file_name' => mb_substr($file->getClientOriginalName(), 0, 191),
            'mime_type' => $file->getMimeType(), 'size' => $file->getSize(), 'path' => $path,
            'user_id' => Auth::id(), 'created_at' => now(),
        ]);
        History::log($asset, 'updated', ['file' => [null, $file->getClientOriginalName()]]);
        return redirect()->to($asset->url());
    }

    public function download($id, $file_id)
    {
        $this->guard();
        $asset = $this->findVisible($id);
        $f = Attachment::where('id', $file_id)->where('asset_id', $asset->id)->firstOrFail();
        return \Storage::disk('private')->download($f->path, $f->file_name);
    }

    public function deleteFile($id, $file_id)
    {
        $this->guardManage();
        $asset = $this->findVisible($id);
        $f = Attachment::where('id', $file_id)->where('asset_id', $asset->id)->firstOrFail();
        \Storage::disk('private')->delete($f->path);
        $f->delete();
        History::log($asset, 'updated', ['file' => [$f->file_name, null]]);
        return redirect()->to($asset->url());
    }

    /** Link or unlink a conversation (from the conversation block or the asset page). */
    public function link(Request $request, $id)
    {
        $this->guard();
        $asset = $this->findVisible($id);
        $conv = \App\Conversation::findOrFail((int) $request->input('conversation_id'));
        if (!Auth::user()->can('view', $conv)) {
            abort(403);
        }
        $done = $request->input('action') == 'unlink' ? $asset->unlink($conv->id, Auth::id()) : $asset->link($conv->id, Auth::id());
        if ($request->ajax()) {
            return response()->json(['ok' => true, 'changed' => $done]);
        }
        return redirect()->to($request->input('back') == 'asset' ? $asset->url() : $conv->url());
    }

    /** Printable QR label (v1 item 11); the QR code holds /a/{token}, never the asset id. */
    public function label($id)
    {
        $this->guard();
        return view('mfsassets::assets.label', ['asset' => $this->findVisible($id)]);
    }

    /** Assets tab on the customer page (v1 item 7): assets assigned to any of the customer's addresses. */
    public function customer($id)
    {
        $this->guard();
        $customer = \App\Customer::findOrFail($id);
        $emails = array_map('strtolower', $customer->emails()->pluck('email')->all());
        $assets = Asset::visibleTo(Auth::user())->with('type', 'owner', 'location')
            ->whereIn('assigned_email', $emails ?: [''])->orderBy('tag')->get();
        return view('mfsassets::assets.customer', [
            'customer' => $customer, 'emails' => $emails, 'assets' => $assets, 'can_manage' => Provider::canManage(),
        ]);
    }

    /** Owner changed on the form: its departments and the location list (owner's locations first). */
    public function options(Request $request)
    {
        $this->guardManage();
        $owner = (int) $request->input('owner_id');
        if ($owner && !Organisation::visibleTo(Auth::user())->where('id', $owner)->exists()) {
            $owner = 0;
        }
        return response()->json([
            'departments' => $owner ? Department::treeOptions($owner) : [],
            'locations'   => AssetService::locationOptions(Auth::user(), $owner),
        ]);
    }

    /** Search by tag, name or serial (conversation block, v1 item 6). */
    public function search(Request $request)
    {
        $this->guard();
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
        $assets = Asset::visibleTo(Auth::user())->with('owner')
            ->where(function ($w) use ($like) {
                $w->where('tag', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial', 'like', $like);
            })->orderBy('tag')->limit(20)->get();
        return response()->json($assets->map(function ($a) {
            return ['id' => $a->id, 'text' => $a->tag.' · '.$a->name.($a->owner ? ' ('.$a->owner->name.')' : '')];
        })->values());
    }
}
