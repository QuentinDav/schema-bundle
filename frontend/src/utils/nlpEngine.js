import { tokenize } from './nlp/tokenizer.js'
import { buildLexicon } from './nlp/lexicon.js'
import { parseSelect } from './nlp/selectParser.js'
import { parseConditions } from './nlp/conditionParser.js'
import { buildSQL } from './nlp/sqlBuilder.js'
import { findPaths } from './pathFinder.js' // your existing graph search

/** Main entry compatible with your current interface. */
export function generateSqlFromPrompt(prompt, entities){
  try{
    const tok = tokenize(prompt)
    const lex = buildLexicon(entities)

    // SELECT & main entity
    const { mainEntity, selectFields } = parseSelect(tok, entities, lex)
    if(!mainEntity){
      return {
        success:false,
        error:'ENTITY_NOT_FOUND',
        message:'Could not identify the main entity in your query. Try mentioning an entity name explicitly.',
        suggestions: entities.slice(0,5).map(e=>e.name)
      }
    }

    // Conditions / ORDER / GROUP / LIMIT
    const { conditions, orderBy, groupBy, limit } = parseConditions(tok, entities, lex, mainEntity)

    // Build SQL (minimal joins)
    const sql = buildSQL({ mainEntity, selectFields, conditions, orderBy, groupBy, limit }, entities, findPaths)

    // Simple explanation & confidence
    const explanation = makeExplanation(mainEntity, selectFields, conditions, limit)
    const confidence = 0.7
      + (conditions.length?0.1:0)
      + (selectFields.length && selectFields[0].field!=='*'?0.1:0)

    return { success:true, sql, explanation, entities:[mainEntity, ...new Set(selectFields.map(s=>s.entity))], paths:[], confidence: Math.min(confidence, 0.95) }
  }catch(err){
    return { success:false, error:'PARSER_ERROR', message:`Error parsing query: ${err.message}`, details: err.stack }
  }
}

function makeExplanation(mainEntity, selectFields, conditions, limit){
  const sel = selectFields.map(s=> s.field==='*'? `${s.entity.name}.*` : `${s.entity.name}.${s.field}`).join(', ')
  const cond = conditions.filter(c=>!c.connector).map(c=>`${c.entity.name}.${c.field} ${c.operator} ${String(c.value).replace(/\n/g,' ')}`).join(' and ')
  let text = `This query retrieves ${sel || mainEntity.name + ' records'}`
  if(cond) text += ` where ${cond}`
  if(limit) text += `, limited to ${limit} results`
  return text + '.'
}
