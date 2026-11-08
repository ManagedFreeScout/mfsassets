<?php

namespace Modules\MFSAssets\Entities;

use Illuminate\Database\Eloquent\Model;

/** An asset type (Laptop, Phone, Software licence, ...) with its own fields (card #426, v1 item 1). */
class AssetType extends Model
{
    protected $table = 'mfsassets_types';

    protected $fillable = ['name', 'fields'];

    const FIELD_TYPES = ['text', 'number', 'date', 'select', 'checkbox'];

    public static function fieldTypes()
    {
        return [
            'text'     => __('Text'),
            'number'   => __('Number'),
            'date'     => __('Date'),
            'select'   => __('List'),
            'checkbox' => __('Yes/no'),
        ];
    }

    /** [{key, label, type, options[]}] */
    public function fieldList()
    {
        $list = json_decode((string) $this->fields, true);
        return is_array($list) ? $list : [];
    }

    /**
     * Fields from the types form: one per line, "Label | type | option1, option2".
     * The key is derived from the label and kept stable for existing labels, so values survive
     * a rename of the order.
     */
    public static function parseFields($text, array $existing = [])
    {
        $keys_by_label = [];
        foreach ($existing as $f) {
            $keys_by_label[mb_strtolower($f['label'])] = $f['key'];
        }
        $out = [];
        $used = [];
        foreach (preg_split('/\r?\n/', (string) $text) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $label = mb_substr($parts[0] ?? '', 0, 100);
            if ($label === '') {
                continue;
            }
            $type = in_array($parts[1] ?? '', self::FIELD_TYPES) ? $parts[1] : 'text';
            $key = $keys_by_label[mb_strtolower($label)] ?? (trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(\Illuminate\Support\Str::ascii($label))), '_') ?: 'field');
            $base = $key;
            for ($i = 2; isset($used[$key]); $i++) {
                $key = $base.'_'.$i;
            }
            $used[$key] = true;
            $field = ['key' => $key, 'label' => $label, 'type' => $type];
            if ($type == 'select') {
                $field['options'] = array_values(array_filter(array_map('trim', explode(',', $parts[2] ?? ''))));
            }
            $out[] = $field;
        }
        return $out;
    }

    public static function fieldsText(array $fields)
    {
        return implode("\n", array_map(function ($f) {
            return $f['label'].' | '.$f['type'].(($f['type'] == 'select') ? ' | '.implode(', ', $f['options'] ?? []) : '');
        }, $fields));
    }
}
