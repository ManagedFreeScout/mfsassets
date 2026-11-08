<?php

namespace Modules\MFSAssets\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Entities\AssetType;
use Modules\MFSAssets\Providers\MFSAssetsServiceProvider as Provider;

/** Asset types and their own fields (v1 item 1). Admins only. */
class TypesController extends Controller
{
    protected function guardAdmin()
    {
        if (!Provider::enabled()) {
            abort(404);
        }
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
    }

    public function index()
    {
        $this->guardAdmin();
        $types = AssetType::orderBy('name')->get();
        $counts = Asset::selectRaw('type_id, count(*) as n')->groupBy('type_id')->pluck('n', 'type_id');
        return view('mfsassets::types.index', ['types' => $types, 'counts' => $counts, 'field_types' => AssetType::fieldTypes()]);
    }

    /** action add / update / delete (only a type no asset uses). */
    public function save(Request $request)
    {
        $this->guardAdmin();
        $action = $request->input('action');
        $name = mb_substr(trim((string) $request->input('name')), 0, 191);
        if ($action == 'add' && $name !== '') {
            if (AssetType::where('name', $name)->exists()) {
                \Session::flash('flash_error_floating', __('A type with this name already exists.'));
            } else {
                AssetType::create(['name' => $name, 'fields' => json_encode(AssetType::parseFields($request->input('fields')))]);
                \Session::flash('flash_success_floating', __('Type added.'));
            }
        } else {
            $type = AssetType::find((int) $request->input('type_id'));
            if ($type && $action == 'update' && $name !== '') {
                if (AssetType::where('name', $name)->where('id', '!=', $type->id)->exists()) {
                    \Session::flash('flash_error_floating', __('A type with this name already exists.'));
                } else {
                    $type->update(['name' => $name, 'fields' => json_encode(AssetType::parseFields($request->input('fields'), $type->fieldList()))]);
                    \Session::flash('flash_success_floating', __('Saved.'));
                }
            } elseif ($type && $action == 'delete') {
                if (Asset::where('type_id', $type->id)->exists()) {
                    \Session::flash('flash_error_floating', __('This type is still used by assets.'));
                } else {
                    $type->delete();
                    \Session::flash('flash_success_floating', __('Type deleted.'));
                }
            }
        }
        return redirect()->route('mfsassets.types');
    }
}
