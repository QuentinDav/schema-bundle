/** Entity & field resolution utilities. */

/**
 * @typedef {{name:string, table:string, fields:Array, relations?:Array}} Entity
 */

/**
 * Try to map free text to an entity.
 * @param {string} token
 * @param {{entityNames: Map<string, Set<string>>}} lex
 * @param {Entity[]} entities
 * @returns {Entity|null}
 */
export function resolveEntity(token, lex, entities){
  const t = token.toLowerCase();
  // direct match against any variant set
  for(const e of entities){
    if(lex.entityNames.get(e.name)?.has(t)) return e;
  }
  return null;
}

/**
 * Resolve a field possibly expressed as "field of Entity" or "Entity field".
 * @param {string[]} windowTokens - sliding window of tokens
 * @param {Entity[]} entities
 * @param {*} lex
 * @param {Entity|null} biasEntity - preferred entity (e.g., from SELECT)
 * @returns {{entity:Entity, field:string}|null}
 */
export function resolveField(windowTokens, entities, lex, biasEntity){
  const joined = windowTokens.join(' ').toLowerCase();

  // patterns: "id of training" | "training id" | "training.id"
  const ofMatch = joined.match(/^(?<field>\w+)\s+of\s+(?<entity>\w+)$/i);
  if(ofMatch){
    const ent = resolveEntity(ofMatch.groups.entity, lex, entities);
    if(ent && hasField(ent, ofMatch.groups.field)) return { entity: ent, field: ofMatch.groups.field };
  }
  const efMatch = joined.match(/^(?<entity>\w+)\s+(?<field>\w+)$/i) || joined.match(/^(?<entity>\w+)\.(?<field>\w+)$/i);
  if(efMatch){
    const ent = resolveEntity(efMatch.groups.entity, lex, entities);
    if(ent && hasField(ent, efMatch.groups.field)) return { entity: ent, field: efMatch.groups.field };
  }

  // fallback: try bias entity then any entity containing the field name
  const field = joined.trim();
  if(biasEntity && hasField(biasEntity, field)) return { entity: biasEntity, field };
  for(const e of entities){ if(hasField(e, field)) return { entity: e, field }; }
  return null;
}

function hasField(entity, field){
  return !!(entity.fields||[]).find(f=>f.name.toLowerCase()===String(field).toLowerCase());
}
