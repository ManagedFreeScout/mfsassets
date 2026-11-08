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
use Modules\MFSAssets\Services\ApiFormat;
use Modules\MFSAssets\Services\AssetService;

/**
 * REST API for assets (card #426, v1 item 12), in the MFS Organisations conventions: behind the
 * official API & Webhooks module's key check; camelCase; _embedded lists with a page block;
 * Resource-ID on create; the same error body; 402 without a licence. A personal API key acts as
 * its owner (visibility via the owner organisation's mailboxes, "Manage assets" to change, admin
 * to delete); the module's main API key acts as admin.
 */
class ApiController extends Controller
{
    const PAGE_SIZE = 50;
    const MAX_PAGE_SIZE = 200;

    // API field (camelCase) => form/service field (snake_case).
    const FIELDS = [
        'name' => 'name', 'typeId' => 'type_id', 'status' => 'status', 'ownerId' => 'owner_id', 'locationId' => 'location_id',
        'serial' => 'serial', 'supplier' => 'supplier', 'purchaseDate' => 'purchase_date', 'purchasePrice' => 'purchase_price',
        'warrantyEnd' => 'warranty_end', 'notes' => 'notes', 'fields' => 'fields',
    ];

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            return Provider::enabled() ? $next($request) : self::unlicensed();
        });
    }

    public static function unlicensed()
    {
        return response()->json(['message' => 'MFS Assets needs a valid licence and MFS Organisations 1.1 or newer', '_embedded' => ['errors' => []]], 402);
    }

    protected function user()
    {
        return Auth::user();
    }

    protected function canManage()
    {
        return !$this->user() || Provider::canManage($this->user());
    }

    protected function isAdmin()
    {
        return !$this->user() || $this->user()->isAdmin();
    }

    protected function find($id)
    {
        return Asset::visibleTo($this->user())->where('id', (int) $id)->first();
    }

    protected function json($data, $code = 200)
    {
        return response()->json($data, $code);
    }

    protected function error($message, $code = 400, $path = '')
    {
        $errors = $path !== '' ? [['path' => $path, 'message' => $message, 'source' => 'JSON']] : [];
        return $this->json(['message' => $message, '_embedded' => ['errors' => $errors]], $code);
    }

    protected function body(Request $request)
    {
        $data = $request->json()->all();
        return $data ?: $request->all();
    }

    /** Service errors (snake_case fields) as the API error body (camelCase paths). */
    protected function validationError(array $errors)
    {
        $paths = array_flip(self::FIELDS) + ['assigned_to' => 'assignedEmail'];
        $list = [];
        foreach ($errors as $field => $msg) {
            $list[] = ['path' => $paths[$field] ?? $field, 'message' => $msg, 'source' => 'JSON'];
        }
        return $this->json(['message' => reset($errors), '_embedded' => ['errors' => $list]], 400);
    }

    /** API body → service input. assignedEmail / assignedDepartmentId / assignedTo: "none". */
    protected function input(array $data)
    {
        $in = [];
        foreach (self::FIELDS as $api => $field) {
            if (array_key_exists($api, $data)) {
                $in[$field] = $data[$api];
            }
        }
        if (array_key_exists('assignedEmail', $data) && $data['assignedEmail']) {
            $in['assigned_to'] = 'email:'.$data['assignedEmail'];
        } elseif (array_key_exists('assignedDepartmentId', $data) && $data['assignedDepartmentId']) {
            $in['assigned_to'] = 'department:'.(int) $data['assignedDepartmentId'];
        } elseif ((array_key_exists('assignedEmail', $data) || array_key_exists('assignedDepartmentId', $data)) || (($data['assignedTo'] ?? null) === 'none')) {
            $in['assigned_to'] = 'none';
        }
        return $in;
    }

    /** GET /api/assets?search=&tag=&serial=&ownerId=&typeId=&status=&assignedEmail=&locationId=&conversationId=&page=&pageSize= */
    public function index(Request $request)
    {
        $query = Asset::visibleTo($this->user())->with('type', 'owner', 'location')->orderBy('tag');
        if ($s = trim((string) $request->input('search'))) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $s).'%';
            $query->where(function ($w) use ($like) {
                $w->where('tag', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial', 'like', $like);
            });
        }
        foreach (['tag' => 'tag', 'serial' => 'serial', 'status' => 'status'] as $p => $col) {
            if ($request->filled($p)) {
                $query->where($col, $request->input($p));
            }
        }
        foreach (['ownerId' => 'owner_id', 'typeId' => 'type_id', 'locationId' => 'location_id'] as $p => $col) {
            if ($request->filled($p)) {
                $query->where($col, (int) $request->input($p));
            }
        }
        if ($request->filled('assignedEmail')) {
            $query->where('assigned_email', strtolower($request->input('assignedEmail')));
        }
        if ($request->filled('conversationId')) {
            $cid = (int) $request->input('conversationId');
            $query->whereIn('id', function ($q) use ($cid) {
                $q->select('asset_id')->from('mfsassets_conversations')->where('conversation_id', $cid);
            });
        }
        $size = min(self::MAX_PAGE_SIZE, max(1, (int) ($request->input('pageSize') ?: self::PAGE_SIZE)));
        $page = $query->paginate($size, ['*'], 'page', max(1, (int) $request->input('page', 1)));
        return $this->json([
            '_embedded' => ['assets' => array_map([ApiFormat::class, 'asset'], $page->items())],
            'page'      => ['size' => $page->perPage(), 'totalElements' => $page->total(), 'totalPages' => $page->lastPage(), 'number' => $page->currentPage()],
        ]);
    }

    /** GET /api/assets/{id}?embed=history,conversations */
    public function show(Request $request, $id)
    {
        $asset = $this->find($id);
        if (!$asset) {
            return $this->error('Asset not found', 404);
        }
        $data = ApiFormat::asset($asset);
        $embed = array_map('trim', explode(',', (string) $request->input('embed')));
        if (in_array('history', $embed)) {
            $data['_embedded']['history'] = History::where('asset_id', $asset->id)->orderBy('id', 'desc')->limit(500)->get()->map(function ($h) {
                return ['action' => $h->action, 'changes' => (object) $h->changeList(), 'userId' => $h->user_id, 'createdAt' => ApiFormat::date($h->created_at)];
            })->values()->all();
        }
        if (in_array('conversations', $embed)) {
            $data['_embedded']['conversationIds'] = array_map('intval', $asset->conversationIds());
        }
        return $this->json($data);
    }

    /** POST /api/assets {"name": "...", "ownerId": 1, "typeId": null, "status": "in_use", "assignedEmail": "...", "locationId": null, ...} */
    public function store(Request $request)
    {
        if (!$this->canManage()) {
            return $this->error('Forbidden: This action requires the permission "Manage assets"', 403);
        }
        $in = $this->input($this->body($request));
        if (!isset($in['owner_id']) && Provider::ourOrgId()) {
            $in['owner_id'] = Provider::ourOrgId();
        }
        $asset = new Asset();
        list($errors) = AssetService::save($asset, $in, $this->user());
        if ($errors) {
            return $this->validationError($errors);
        }
        return $this->json(ApiFormat::asset($asset->fresh()), 201)->header('Resource-ID', $asset->id);
    }

    /** PUT/PATCH /api/assets/{id}: only the fields sent change. */
    public function update(Request $request, $id)
    {
        if (!$this->canManage()) {
            return $this->error('Forbidden: This action requires the permission "Manage assets"', 403);
        }
        $asset = $this->find($id);
        if (!$asset) {
            return $this->error('Asset not found', 404);
        }
        list($errors) = AssetService::save($asset, $this->input($this->body($request)), $this->user(), true);
        if ($errors) {
            return $this->validationError($errors);
        }
        return $this->json(ApiFormat::asset($asset->fresh()));
    }

    /** DELETE /api/assets/{id}: admins / main key only. Set status "retired" or "disposed" to keep the history. */
    public function destroy($id)
    {
        if (!$this->isAdmin()) {
            return $this->error('Forbidden: only an administrator can delete assets', 403);
        }
        $asset = $this->find($id);
        if (!$asset) {
            return $this->error('Asset not found', 404);
        }
        foreach (Attachment::where('asset_id', $asset->id)->get() as $f) {
            \Storage::disk('private')->delete($f->path);
            $f->delete();
        }
        \DB::table('mfsassets_conversations')->where('asset_id', $asset->id)->delete();
        History::where('asset_id', $asset->id)->delete();
        \Eventy::action('mfsassets.asset.deleting', $asset);
        $asset->delete();
        return response('', 204);
    }

    /** POST /api/assets/{id}/conversations {"conversationId": 123} — link; DELETE .../conversations/{conversationId} — unlink. */
    public function link(Request $request, $id)
    {
        $asset = $this->find($id);
        if (!$asset) {
            return $this->error('Asset not found', 404);
        }
        $cid = (int) ($this->body($request)['conversationId'] ?? 0);
        $conv = \App\Conversation::find($cid);
        if (!$conv || ($this->user() && !in_array($conv->mailbox_id, $this->user()->mailboxesIdsCanView()))) {
            return $this->error('Conversation not found', 404, 'conversationId');
        }
        $asset->link($conv->id, $this->user() ? $this->user()->id : null);
        return $this->json(['conversationIds' => array_map('intval', $asset->conversationIds())], 201);
    }

    public function unlink($id, $conversation_id)
    {
        $asset = $this->find($id);
        if (!$asset) {
            return $this->error('Asset not found', 404);
        }
        if (!$asset->unlink((int) $conversation_id, $this->user() ? $this->user()->id : null)) {
            return $this->error('This asset is not linked to that conversation', 404);
        }
        return response('', 204);
    }

    /** GET /api/assets/types: the types and their own fields. */
    public function types()
    {
        return $this->json(['_embedded' => ['types' => AssetType::orderBy('name')->get()->map(function ($t) {
            return ['id' => $t->id, 'name' => $t->name, 'fields' => $t->fieldList()];
        })->values()->all()]]);
    }
}
