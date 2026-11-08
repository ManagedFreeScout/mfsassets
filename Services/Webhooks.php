<?php

namespace Modules\MFSAssets\Services;

use Modules\MFSAssets\Entities\Asset;
use Modules\MFSAssets\Providers\MFSAssetsServiceProvider as Provider;

/**
 * Asset events for the official API & Webhooks module (card #426, v1 item 12), same set-up as
 * MFS Organisations: the events appear in its webhook screen; it sends them in the background,
 * signed, with its retry and log. Conversation webhooks get an extra "assets" field.
 */
class Webhooks
{
    const EVENTS = [
        'asset.created', 'asset.updated', 'asset.deleted', 'asset.assigned', 'asset.moved',
        'asset.status_changed', 'asset.linked', 'asset.unlinked',
    ];

    public static function available()
    {
        return \Module::isActive('apiwebhooks') && class_exists('\Modules\ApiWebhooks\Providers\ApiWebhooksServiceProvider');
    }

    public static function fire($event, array $data)
    {
        if (!self::available() || !Provider::enabled()) {
            return;
        }
        \Modules\ApiWebhooks\Providers\ApiWebhooksServiceProvider::maybeRunWebhook($event, $data);
    }

    public static function register()
    {
        if (!self::available()) {
            return;
        }
        \Eventy::addFilter('webhooks.events', function ($events) {
            return array_values(array_unique(array_merge($events, self::EVENTS)));
        });
        \Eventy::addFilter('apiwebhooks.webhook.before_run', function ($params, $event, $data) {
            if (strpos((string) $event, 'convo.') === 0 && $data instanceof \App\Conversation && is_array($params) && Provider::enabled()) {
                $params['assets'] = Asset::ofConversation($data->id)->orderBy('tag')->get()->map(function ($a) {
                    return ApiFormat::ref($a);
                })->values()->all();
            }
            return $params;
        }, 20, 3);

        \Eventy::addAction('mfsassets.asset.created', function ($asset) {
            self::fire('asset.created', ApiFormat::asset($asset->fresh()));
        });
        // One "updated" per save, plus the specific events (assigned / moved / status_changed).
        \Eventy::addAction('mfsassets.asset.updated', function ($asset, $events) {
            $payload = ApiFormat::asset($asset->fresh());
            foreach ((array) $events as $e) {
                self::fire('asset.'.$e, $payload);
            }
            self::fire('asset.updated', $payload);
        }, 20, 2);
        \Eventy::addAction('mfsassets.asset.deleting', function ($asset) {
            self::fire('asset.deleted', ['id' => $asset->id, 'tag' => $asset->tag, 'name' => $asset->name]);
        });
        \Eventy::addAction('mfsassets.asset.linked', function ($asset, $conversation_id) {
            self::fire('asset.linked', ApiFormat::ref($asset) + ['conversationId' => $conversation_id]);
        }, 20, 2);
        \Eventy::addAction('mfsassets.asset.unlinked', function ($asset, $conversation_id) {
            self::fire('asset.unlinked', ApiFormat::ref($asset) + ['conversationId' => $conversation_id]);
        }, 20, 2);
    }
}
