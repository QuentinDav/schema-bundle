import { resolveEntity, resolveField } from './entityResolver.js'

/**
 * Parse the projection to identify main entity and selected fields.
 * Supports: "select user email", "show users", "list training name and id".
 * @param {{tokens:string[]}} tok
 * @param {Array} entities
 * @param {*} lex
 * @returns {{mainEntity:any, selectFields:Array<{entity:any,field:string}>}}
 */
export function parseSelect(tok, entities, lex){
  const t = tok.tokens;
  // heuristics: after verbs show|get|list|select, look for entity and fields
  const verbIdx = t.findIndex(x=>/^(show|get|list|select|find|retrieve)$/i.test(x));
  const scan = verbIdx>=0 ? t.slice(verbIdx+1) : t;

  // try patterns "<entity> <field> [and <field>...]"
  let mainEntity = null; const selectFields = [];

  // 1) try to pick an entity token first
  for(let i=0;i<scan.length;i++){
    const e = resolveEntity(scan[i], lex, entities);
    if(e){ mainEntity = e; break; }
  }

  // 2) collect fields (bias = mainEntity)
  for(let i=0;i<scan.length;i++){
    // windows of 3 tokens max to catch "id of training"
    const windows = [
      [scan[i]],
      scan[i+1] ? [scan[i], scan[i+1]] : null,
      scan[i+2] ? [scan[i], scan[i+1], scan[i+2]] : null
    ].filter(Boolean);

    for(const w of windows){
      const rf = resolveField(w, entities, lex, mainEntity);
      if(rf){
        if(!mainEntity) mainEntity = rf.entity;
        // avoid duplicates
        if(!selectFields.find(s=>s.entity.name===rf.entity.name && s.field===rf.field)){
          selectFields.push(rf);
        }
      }
    }
  }

  // fallback: if nothing, but we have an entity, select *
  if(selectFields.length===0 && mainEntity){
    selectFields.push({ entity: mainEntity, field: '*' });
  }

  return { mainEntity, selectFields };
}
