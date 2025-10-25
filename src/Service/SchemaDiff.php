<?php
namespace Qd\SchemaBundle\Service;

final class SchemaDiff
{
    public function diff(array $old, array $new): array
    {
        $fieldsAdded = array_values(array_diff(array_keys($new['fields']), array_keys($old['fields'])));
        $fieldsRemoved = array_values(array_diff(array_keys($old['fields']), array_keys($new['fields'])));
        $fieldsChanged = [];
        foreach (array_intersect(array_keys($old['fields']), array_keys($new['fields'])) as $k) {
            if (SchemaExtractor::stableJson($old['fields'][$k]) !== SchemaExtractor::stableJson($new['fields'][$k])) {
                $fieldsChanged[] = ['name'=>$k, 'from'=>$old['fields'][$k], 'to'=>$new['fields'][$k]];
            }
        }

        $relsAdded = array_values(array_diff(array_keys($new['rels']), array_keys($old['rels'])));
        $relsRemoved = array_values(array_diff(array_keys($old['rels']), array_keys($new['rels'])));
        $relsChanged = [];
        foreach (array_intersect(array_keys($old['rels']), array_keys($new['rels'])) as $k) {
            if (SchemaExtractor::stableJson($old['rels'][$k]) !== SchemaExtractor::stableJson($new['rels'][$k])) {
                $relsChanged[] = ['field'=>$k, 'from'=>$old['rels'][$k], 'to'=>$new['rels'][$k]];
            }
        }

        return [
            'fields_added'    => array_map(fn($n)=>['name'=>$n,'spec'=>$new['fields'][$n]], $fieldsAdded),
            'fields_removed'  => array_map(fn($n)=>['name'=>$n], $fieldsRemoved),
            'fields_changed'  => $fieldsChanged,
            'rels_added'      => array_map(fn($n)=>['field'=>$n,'spec'=>$new['rels'][$n]], $relsAdded),
            'rels_removed'    => array_map(fn($n)=>['field'=>$n], $relsRemoved),
            'rels_changed'    => $relsChanged,
        ];
    }

    public function isEmpty(array $diff): bool
    {
        foreach ($diff as $k=>$v) if (!empty($v)) return false;
        return true;
    }

    public function toSystemComment(array $diff): array
    {
        $parts = [];

        $fmtField = function(string $name, array $spec): string {
            $bits = [];
            if (!empty($spec['type']))   $bits[] = $spec['type'];
            if (isset($spec['length']))  $bits[] = 'len='.($spec['length'] ?? '∅');
            if (isset($spec['nullable']))$bits[] = $spec['nullable'] ? 'nullable' : 'not-null';
            if (isset($spec['unique']) && $spec['unique']) $bits[] = 'unique';
            return '[['.$name.']] ('.implode(', ', $bits).')';
        };

        $fmtFieldDelta = function(string $name, array $from, array $to) use ($fmtField): string {
            $changes = [];
            if (($from['type'] ?? null) !== ($to['type'] ?? null))           $changes[] = 'type: '.($from['type'] ?? '∅').'→'.($to['type'] ?? '∅');
            if (($from['length'] ?? null) !== ($to['length'] ?? null))       $changes[] = 'len: '.(($from['length'] ?? '∅')).'→'.(($to['length'] ?? '∅'));
            if (($from['nullable'] ?? null) !== ($to['nullable'] ?? null))   $changes[] = ($to['nullable'] ?? false) ? 'nullable↑' : 'nullable↓';
            if (($from['unique'] ?? null) !== ($to['unique'] ?? null))       $changes[] = ($to['unique'] ?? false) ? 'unique↑' : 'unique↓';
            return $fmtField($name, $to).' '.($changes ? '('.implode(', ', $changes).')' : '');
        };

        $fmtRel = function(string $field, array $spec, string $prefix='+rel'): string {
            $bits = [$spec['type'] ?? 'rel'];
            if (isset($spec['nullable'])) $bits[] = $spec['nullable'] ? 'nullable' : 'not-null';
            if (!empty($spec['owning']))  $bits[] = 'owning';
            $target = $spec['target'] ?? '∅';
            return $prefix.' [['.$field.']] ('.implode(', ', $bits).') → '.substr($target, strrpos($target, '\\') + 1);
        };

        foreach ($diff['fields_added']   as $f) $parts[] = '+ '.$fmtField($f['name'], $f['spec']);
        foreach ($diff['rels_added']     as $r) $parts[] = $fmtRel($r['field'], $r['spec'], '+rel');

        foreach ($diff['fields_removed'] as $f) $parts[] = '- [['.$f['name'].']]';
        foreach ($diff['rels_removed']   as $r) $parts[] = '-rel [['.$r['field'].']]';

        foreach ($diff['fields_changed'] as $f) $parts[] = $fmtFieldDelta($f['name'], $f['from'], $f['to']);
        foreach ($diff['rels_changed']   as $r) {
            $changes = [];
            if (($r['from']['type'] ?? null) !== ($r['to']['type'] ?? null))         $changes[] = 'type';
            if (($r['from']['nullable'] ?? null) !== ($r['to']['nullable'] ?? null)) $changes[] = ($r['to']['nullable'] ?? false) ? 'nullable↑' : 'nullable↓';
            if (($r['from']['owning'] ?? null) !== ($r['to']['owning'] ?? null))     $changes[] = ($r['to']['owning'] ?? false) ? 'owning↑' : 'owning↓';
            $parts[] = $fmtRel($r['field'], $r['to'], 'rel').' '.($changes ? '('.implode(', ', $changes).')' : '');
        }

        return $parts;
    }

}
