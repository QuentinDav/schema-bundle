/** Build a schema-aware lexicon (entities, fields, operators, connectors). */

/**
 * @param {Array} entities
 */
export function buildLexicon(entities){
  const entityNames = new Map();
  const fieldNamesByEntity = new Map();

  for(const e of entities){
    const names = new Set([
      e.name,
      e.table,
      pluralize(e.name),
      pluralize(e.table)
    ].filter(Boolean).map(x=>x.toLowerCase()));
    entityNames.set(e.name, names);

    const fieldSet = new Set((e.fields||[]).map(f=>f.name.toLowerCase()));
    fieldNamesByEntity.set(e.name, fieldSet);
  }

  const connectors = new Set(['where','with','when','whose','for','having']);
  const orderSyn = new Set(['order by','sort by']);
  const groupSyn = new Set(['group by']);

  const opMap = new Map([
    ['>=', ['greater than or equal to','at least','>=']],
    ['<=', ['less than or equal to','no more than','<=']],
    ['>',  ['greater than','more than','over','>']],
    ['<',  ['less than','under','<']],
    ['<>', ['not equal to','!=','<>']],
    ['=',  ['equal to','is','=']],
    ['LIKE',['like','contains','starts with','ends with']],
    ['IN',  ['in']],
    ['NOT IN',['not in']]
  ]);

  return { entityNames, fieldNamesByEntity, connectors, orderSyn, groupSyn, opMap };
}

function pluralize(w){
  if(!w) return w; const s=w.toLowerCase();
  if(s.endsWith('s')) return w; if(/[yz]$/.test(s)) return w.slice(0,-1)+'ies';
  if(/(ch|sh|x|z)$/.test(s)) return w+'es';
  return w+'s';
}
