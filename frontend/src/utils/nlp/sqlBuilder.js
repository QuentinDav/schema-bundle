/** SQL builder: minimal joins, quoting, LIKE shaping. */

export function quoteIdent(id){
  if(/[^a-z0-9_]/i.test(id) || /^(user|order|group|select|from|where|limit|table)$/i.test(id)){
    return '"'+String(id).replace(/"/g,'""')+'"';
  }
  return id;
}

function aliasOf(entity){ return entity.name[0].toLowerCase(); }

/**
 * @param {{mainEntity:any, selectFields:Array, conditions:Array, orderBy:any, groupBy:Array, limit:number|null}} plan
 * @param {Array} entities
 * @param {(src: any, dst: any, entities: Array, maxDepth:number)=>Array} findPaths
 */
export function buildSQL(plan, entities, findPaths){
  const { mainEntity, selectFields, conditions, orderBy, groupBy, limit } = plan;
  const mainAlias = aliasOf(mainEntity);

  // Required entities: from SELECT + WHERE + ORDER/GROUP
  const required = new Set([mainEntity]);
  for(const s of selectFields){ required.add(s.entity); }
  for(const c of conditions){ if(!c.connector) required.add(c.entity); }

  const requiredList = Array.from(required);
  // Path gathering
  const paths=[]; const seen=new Set();
  for(const e of requiredList){
    if(e.name===mainEntity.name) continue;
    const p = findPaths(mainEntity, e, entities, 5)[0];
    if(p){
      // store relations for JOIN build
      const key = p.entities.map(x=>x.name).join('>');
      if(!seen.has(key)) { paths.push(p); seen.add(key); }
    }
  }

  // SELECT
  let sql = '';
  const projection = selectFields.length ? selectFields
    .map(s => s.field==='*' ? `${aliasOf(s.entity)}.*` : `${aliasOf(s.entity)}.${quoteIdent(s.field)}`)
    .join(', ') : `${mainAlias}.*`;
  sql += `SELECT ${projection}\n`;
  sql += `FROM ${quoteIdent(mainEntity.table)} ${mainAlias}\n`;

  // JOINs
  for(const path of paths){
    path.relations.forEach((rel, idx) => {
      const from = path.entities[idx];
      const to = path.entities[idx+1];
      const fa = aliasOf(from), ta = aliasOf(to);
      let on='';
      if(rel.type===2 || rel.type===1){
        on = `${fa}.${quoteIdent(rel.field)}_id = ${ta}.id`;
      } else if(rel.type===4){
        const fk = rel.mappedBy || rel.field;
        on = `${ta}.${quoteIdent(fk)}_id = ${fa}.id`;
      } else {
        on = `${ta}.${quoteIdent(rel.field)}_id = ${fa}.id`;
      }
      sql += `INNER JOIN ${quoteIdent(to.table)} ${ta} ON ${on}\n`;
    });
  }

  // WHERE
  if(conditions.length){
    const parts=[];
    for(const c of conditions){
      if(c.connector){ parts.push(c.connector); continue; }
      const a = aliasOf(c.entity);
      const rhs = shapeRightHand(c);
      parts.push(`${a}.${quoteIdent(c.field)} ${c.operator} ${rhs}`);
    }
    const pretty = parts.map(p=> (p==='AND'||p==='OR')?p:`  ${p}`).join('\n');
    sql += `WHERE\n${pretty}\n`;
  }

  // ORDER BY (entity resolution for the field can be added if needed)
  if(orderBy){
    sql += `ORDER BY ${mainAlias}.${quoteIdent(orderBy.field)} ${orderBy.direction}\n`;
  }
  if(groupBy && groupBy.length){
    sql += `GROUP BY ${groupBy.map(f=>`${mainAlias}.${quoteIdent(f)}`).join(', ')}\n`;
  }
  if(Number.isFinite(limit)) sql += `LIMIT ${limit}\n`;

  return sql.trim();
}

function shapeRightHand(c){
  // If LIKE + a quoted string, add wildcards for contains/starts/ends based on original normalization
  if(c.operator==='LIKE'){
    const val = String(c.value);
    const unquoted = val.replace(/^'|'$/g,'');
    if(/%/.test(unquoted)) return `'${unquoted}'`; // already has pattern
    // infer from hint embedded in value (optional). Simpler: default to contains.
    return `'%' || ${quoteString(unquoted)} || '%''`.replace("''", "'"); // safe fallback if DB supports ||; otherwise return `'%' + unquoted + '%'`
  }
  return c.value;
}

function quoteString(s){ return `'${String(s).replace(/'/g,"''")}'`; }
