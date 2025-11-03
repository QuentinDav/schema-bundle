import { resolveEntity, resolveField } from './entityResolver.js'

const CONNECTORS = new Set(['where','with','when','whose','for','having']);

/**
 * Parse conditions introduced by where/with/when/whose/for/having
 * @param {{tokens:string[]}} tok
 * @param {Array} entities
 * @param {*} lex
 * @param {*} mainEntity
 * @returns {{conditions:Array, orderBy:null|{field:string,entity:any,direction:'ASC'|'DESC'}, groupBy:Array, limit:number|null}}
 */
export function parseConditions(tok, entities, lex, mainEntity){
  const t = tok.tokens;
  const idx = t.findIndex(x=>CONNECTORS.has(x));
  const tail = idx>=0 ? t.slice(idx+1) : [];

  // stop at order by / group by / limit
  const stop = Math.min(
    indexOfPhrase(tail, 'order by'),
    indexOfPhrase(tail, 'sort by'),
    indexOfPhrase(tail, 'group by'),
    indexOfSingle(tail, 'limit'),
    tail.length
  );
  const whereTokens = tail.slice(0, stop);

  const conditions = [];
  // split by AND/OR while keeping them
  const parts = splitByAndOr(whereTokens);

  for(const part of parts){
    if(typeof part === 'string' && (part==='AND' || part==='OR')){
      conditions.push({ connector: part });
      continue;
    }
    const c = parseAtomicCondition(part, entities, lex, mainEntity);
    if(c) conditions.push(c);
  }

  // ORDER BY
  const order = parseOrderBy(tail.slice(stop));
  const group = parseGroupBy(tail.slice(stop));
  const limit = parseLimit(tail.slice(stop));

  return { conditions, orderBy: order, groupBy: group, limit };
}

function splitByAndOr(tokens){
  const out=[]; let buf=[];
  for(let i=0;i<tokens.length;i++){
    const w=tokens[i];
    if(/^and$/i.test(w) || /^or$/i.test(w)){
      if(buf.length) out.push(buf), buf=[];
      out.push(w.toUpperCase());
    } else buf.push(w);
  }
  if(buf.length) out.push(buf);
  return out;
}

// --- replace parseAtomicCondition by this version (no countTokensBefore needed)
function parseAtomicCondition(tokens, entities, lex, mainEntity){
  // try to locate the operator as a contiguous span of tokens
  const { found, op, start, length } = findOperatorSpan(tokens);
  let lhsTokens, rhsTokens, normalized;

  if (found) {
    lhsTokens = tokens.slice(0, start);
    rhsTokens = tokens.slice(start + length);
    normalized = normalizeOperator(op);
  } else {
    // fallback "field value" → assume '='
    normalized = '=';
    lhsTokens = tokens.slice(0, 1);
    rhsTokens = tokens.slice(1);
  }

  // resolve LHS as <entity.field> using sliding window + bias
  const lhs = resolveField(lhsTokens, entities, lex, mainEntity);
  if (!lhs) return null;

  // normalize RHS according to field type
  const rhs = normalizeValue(rhsTokens.join(' '), fieldTypeOf(lhs.entity, lhs.field));

  return { entity: lhs.entity, field: lhs.field, operator: normalized, value: rhs };
}

// --- add this helper in the same file
function findOperatorSpan(tokens){
  // Define operators as token arrays (longest first)
  const OPS = [
    ['greater','than','or','equal','to'],
    ['less','than','or','equal','to'],
    ['not','equal','to'],
    ['greater','than'],
    ['more','than'],
    ['less','than'],
    ['at','least'],
    ['no','more','than'],
    ['starts','with'],
    ['ends','with'],
    ['equal','to'],
    ['not','in'],
    ['in'],
    ['like'],
    ['>='], ['<='], ['<>'], ['!='], ['>'], ['<'], ['='],
  ];

  // Normalize tokens to lowercase once
  const t = tokens.map(x => String(x).toLowerCase());

  // Try to find the first occurrence; prefer earliest index,
  // and for the same index prefer the longest operator
  let best = { found:false, op:null, start:-1, length:0 };

  for (let i = 0; i < t.length; i++) {
    for (const phrase of OPS) {
      const L = phrase.length;
      if (i + L > t.length) continue;

      let match = true;
      for (let k = 0; k < L; k++) {
        if (t[i + k] !== phrase[k]) { match = false; break; }
      }
      if (!match) continue;

      // candidate
      if (!best.found || i < best.start || (i === best.start && L > best.length)) {
        best = { found:true, op: phrase.join(' '), start:i, length:L };
      }
    }
  }

  return best;
}


function normalizeOperator(op){
  const o = op.toLowerCase();
  if(o==='!='||o==='<>') return '<>';
  if(o==='='||o==='equal to'||o==='is') return '=';
  if(o==='>='||o==='greater than or equal to'||o==='at least') return '>=';
  if(o==='<='||o==='less than or equal to'||o==='no more than') return '<=';
  if(o==='>'||o==='greater than'||o==='more than') return '>';
  if(o==='<'||o==='less than') return '<';
  if(o==='like'||o==='contains'||o==='starts with'||o==='ends with') return 'LIKE';
  if(o==='in') return 'IN';
  if(o==='not in') return 'NOT IN';
  return '=';
}

function normalizeValue(raw, fieldType){
  const v = String(raw||'').trim().replace(/^"|"$/g,'').replace(/^'|'$/g,'');
  if(!v) return v;
  const low=v.toLowerCase();
  if(fieldType==='boolean') return (['true','1','yes','y'].includes(low)?'TRUE':['false','0','no','n'].includes(low)?'FALSE':`'${escapeQuote(v)}'`);
  if(fieldType==='number') return /^[-+]?\d+(\.\d+)?$/.test(v)?v:'0';
  if(fieldType==='string'){
    // LIKE helpers: we'll add % at build time depending on operator
    return `'${escapeQuote(v)}'`;
  }
  return `'${escapeQuote(v)}'`;
}

function fieldTypeOf(entity, field){
  const f=(entity.fields||[]).find(x=>x.name.toLowerCase()===String(field).toLowerCase());
  if(!f) return 'string';
  const t=(f.type||'').toLowerCase();
  if(['integer','bigint','smallint','float','double','decimal','number'].includes(t)) return 'number';
  if(['bool','boolean'].includes(t)) return 'boolean';
  return 'string';
}

function escapeQuote(s){return String(s).replace(/'/g, "''");}

function indexOfPhrase(tokens, phrase){
  const parts = phrase.split(' ');
  for(let i=0;i<=tokens.length-parts.length;i++){
    if(parts.every((p,idx)=>tokens[i+idx]===p)) return i;
  }
  return Number.POSITIVE_INFINITY;
}
function indexOfSingle(tokens, word){
  const i=tokens.indexOf(word); return i<0?Number.POSITIVE_INFINITY:i;
}

function parseOrderBy(tokens){
  const i = indexOfPhrase(tokens,'order by');
  const j = i===Number.POSITIVE_INFINITY ? indexOfPhrase(tokens,'sort by') : i;
  if(j===Number.POSITIVE_INFINITY) return null;
  const parts = tokens.slice(j+2);
  if(parts.length===0) return null;
  const field = parts[0];
  const dir = (parts[1] && /desc|descending/i.test(parts[1])) ? 'DESC':'ASC';
  return { field, entity: null, direction: dir };
}
function parseGroupBy(tokens){
  const j = indexOfPhrase(tokens,'group by');
  if(j===Number.POSITIVE_INFINITY) return [];
  return [ tokens[j+2] ].filter(Boolean);
}
function parseLimit(tokens){
  const j = indexOfSingle(tokens,'limit');
  if(j===Number.POSITIVE_INFINITY) return null;
  const n = Number(tokens[j+1]);
  return Number.isFinite(n)?n:null;
}
