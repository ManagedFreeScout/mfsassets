<?php

namespace Modules\MFSAssets\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Entities\History;
use Modules\MFSAssets\Providers\MFSAssetsServiceProvider as Provider;

/**
 * QR label (card #426, v1 item 11): /a/{token}. A logged-in agent who may see the asset goes to
 * its page; anyone else gets a short report form (when a report mailbox is set) that creates a
 * conversation linked to the asset. The token is random (128 bit), the form is rate-limited per
 * IP address and has a hidden spam trap. The form shows no details of the asset or its owner.
 */
class ReportController extends Controller
{
    protected function asset($token)
    {
        if (!Provider::enabled()) {
            abort(404);
        }
        return Asset::where('qr_token', $token)->firstOrFail();
    }

    protected function mailbox()
    {
        $id = (int) \Option::get(Provider::OPTION_REPORT_MAILBOX, 0);
        return $id ? \App\Mailbox::find($id) : null;
    }

    public function show($token)
    {
        $asset = $this->asset($token);
        if (Auth::check() && $asset->isVisibleTo(Auth::user())) {
            return redirect()->to($asset->url());
        }
        return view('mfsassets::report.form', ['asset' => $asset, 'mailbox' => $this->mailbox(), 'sent' => session('mfsassets_sent')]);
    }

    public function submit(Request $request, $token)
    {
        $asset = $this->asset($token);
        $mailbox = $this->mailbox();
        if (!$mailbox) {
            abort(404);
        }
        // Spam trap: a field people don't see.
        if ($request->filled('website')) {
            return redirect()->route('mfsassets.qr', ['token' => $token])->with('mfsassets_sent', true);
        }
        $key = 'mfsassets_report_'.sha1((string) $request->ip());
        $count = (int) \Cache::get($key, 0);
        if ($count >= (int) config('mfsassets.report_rate_limit', 5)) {
            return back()->withErrors(['description' => __('Too many reports from this connection. Please try again later.')])->withInput();
        }
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'email'       => 'required|email|max:191',
            'description' => 'required|string|max:5000',
        ]);
        \Cache::put($key, $count + 1, now()->addHour());

        $customer = \App\Customer::create(strtolower($data['email']), ['first_name' => mb_substr($data['name'], 0, 100)]);
        $body = nl2br(e($data['description'])).'<br><br><i>'.e(__('Reported via the QR label of asset :tag (:name).', ['tag' => $asset->tag, 'name' => $asset->name])).'</i>';
        $result = \App\Conversation::create([
            'type'        => \App\Conversation::TYPE_EMAIL,
            'subject'     => mb_substr(__('Problem with :tag :name', ['tag' => $asset->tag, 'name' => $asset->name]), 0, 190),
            'mailbox_id'  => $mailbox->id,
            'source_type' => \App\Conversation::SOURCE_TYPE_WEB,
            'status'      => \App\Conversation::STATUS_ACTIVE,
        ], [[
            'type' => \App\Thread::TYPE_CUSTOMER,
            'body' => $body,
        ]], $customer);
        if (!empty($result['conversation'])) {
            $asset->link($result['conversation']->id, null);
            History::log($asset, 'reported', ['conversation' => $result['conversation']->id], null);
        }
        return redirect()->route('mfsassets.qr', ['token' => $token])->with('mfsassets_sent', true);
    }
}
