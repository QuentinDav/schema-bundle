/**
 * SQL Parser Service
 *
 * Parses natural language queries and generates SQL using schema metadata.
 * Uses rule-based pattern matching (no external AI required).
 */

import { findPaths } from './pathFinder'

/**
 * Parse natural language prompt and generate SQL
 *
 * @param {String} prompt - User's natural language query
 * @param {Array} entities - All schema entities
 * @returns {Object} Result with SQL, explanation, and metadata
 */
export function generateSqlFromPrompt(prompt, entities) {
  try {
    // 1. Parse the prompt
    const parsed = parsePrompt(prompt, entities)

    if (!parsed.mainEntity) {
      return {
        success: false,
        error: 'ENTITY_NOT_FOUND',
        message:
          'Could not identify the main entity in your query. Try mentioning an entity name explicitly.',
        suggestions: entities.slice(0, 5).map((e) => e.name),
      }
    }

    // 2. Find paths between entities
    const pathResults = findRelationPaths(parsed, entities)

    if (!pathResults.success) {
      return pathResults
    }

    // 3. Build SQL
    const sql = buildSql(parsed, pathResults.paths, entities)

    // 4. Generate explanation
    const explanation = generateExplanation(parsed, pathResults.paths)

    return {
      success: true,
      sql: sql,
      explanation: explanation,
      entities: parsed.entities,
      paths: pathResults.paths,
      confidence: calculateConfidence(parsed, pathResults),
    }
  } catch (error) {
    return {
      success: false,
      error: 'PARSER_ERROR',
      message: `Error parsing query: ${error.message}`,
      details: error.stack,
    }
  }
}

/* -------------------------------------------------------------------------- */
/*                               Helper functions                             */
/* -------------------------------------------------------------------------- */

/** Double-quote SQL identifiers when nécessaire (Postgres/SQL standard) */
function quoteIdent(id) {
  if (
    /[^a-z0-9_]/i.test(id) ||
    /^(user|order|group|select|from|where|limit|table)$/i.test(id)
  ) {
    return `"${id.replace(/"/g, '""')}"`
  }
  return id
}

function getFieldMeta(entity, fieldName) {
  return (
    entity?.fields?.find(
      (f) => f.name.toLowerCase() === String(fieldName).toLowerCase()
    ) || null
  )
}
function isNumericType(t) {
  return [
    'integer',
    'bigint',
    'smallint',
    'float',
    'double',
    'decimal',
    'number',
  ].includes((t || '').toLowerCase())
}
function isBooleanType(t) {
  return ['bool', 'boolean'].includes((t || '').toLowerCase())
}
function sqlQuote(v) {
  const escaped = String(v).replace(/'/g, "''")
  return `'${escaped}'`
}
function normalizeOperator(op) {
  if (!op) return '='
  if (op === 'CONTAINS' || op === 'STARTS' || op === 'ENDS') return 'LIKE'
  if (op === '!=') return '<>'
  return op
}
function coerceValueForSQL(raw, meta, operator) {
  if (isBooleanType(meta?.type)) {
    const v = String(raw).toLowerCase()
    if (v === 'true' || v === '1') return 'TRUE'
    if (v === 'false' || v === '0') return 'FALSE'
    return sqlQuote(raw)
  }
  if (isNumericType(meta?.type)) {
    return /^[+-]?\d+(\.\d+)?$/.test(String(raw)) ? String(raw) : '0'
  }
  // LIKE helpers
  if (operator === 'CONTAINS' || operator === 'STARTS' || operator === 'ENDS') {
    const base = String(raw)
    const like =
      operator === 'CONTAINS'
        ? `%${base}%`
        : operator === 'STARTS'
          ? `${base}%`
          : `%${base}`
    return sqlQuote(like)
  }
  return sqlQuote(raw)
}

/* -------------------------------------------------------------------------- */
/*                                   Parser                                   */
/* -------------------------------------------------------------------------- */

/**
 * Parse prompt to extract entities, action, and conditions
 * English-only for better reliability and no encoding issues
 */
function parsePrompt(prompt, entities) {
  const lowerPrompt = prompt.toLowerCase()

  // Build entity name variations map
  const entityMap = new Map()
  entities.forEach((entity) => {
    const variations = [
      entity.name.toLowerCase(),
      entity.table.toLowerCase(),
      pluralize(entity.name.toLowerCase()),
      pluralize(entity.table.toLowerCase()),
    ]
    variations.forEach((v) => entityMap.set(v, entity))
  })

  // Detect action (SELECT, INSERT, UPDATE, DELETE)
  let action = 'SELECT'
  if (lowerPrompt.match(/\b(insert|add|create)\b/)) action = 'INSERT'
  else if (lowerPrompt.match(/\b(update|modify|change)\b/)) action = 'UPDATE'
  else if (lowerPrompt.match(/\b(delete|remove|drop)\b/)) action = 'DELETE'

  // Extract entities mentioned in prompt
  const mentionedEntities = []
  const words = lowerPrompt.split(/\s+/)

  words.forEach((word) => {
    // Clean word from punctuation
    const cleanWord = word.replace(/\W/g, '')
    if (entityMap.has(cleanWord)) {
      const entity = entityMap.get(cleanWord)
      if (!mentionedEntities.find((e) => e.name === entity.name)) {
        mentionedEntities.push(entity)
      }
    }
  })

  // Determine main entity - improved to handle "X of Y" patterns
  let mainEntity = null

  // Pattern 1: "show/get X of Y" → X is main entity
  const ofPattern =
    /(?:get|fetch|show|list|select|find|retrieve)\s+(?:me\s+)?(?:all\s+)?(?:the\s+)?(\w+)\s+of\s+(\w+)/i
  const ofMatch = prompt.match(ofPattern)
  if (ofMatch) {
    const firstWord = ofMatch[1].toLowerCase()
    if (entityMap.has(firstWord)) {
      mainEntity = entityMap.get(firstWord)
    }
  }

  // Pattern 2: Simple "show/get X"
  if (!mainEntity) {
    const simplePattern =
      /(?:get|fetch|show|list|select|find|retrieve)\s+(?:me\s+)?(?:all\s+)?(?:the\s+)?(\w+)/i
    const simpleMatch = prompt.match(simplePattern)
    if (simpleMatch) {
      const entityWord = simpleMatch[1].toLowerCase()
      // Skip filler words
      if (
        !['me', 'all', 'the', 'a', 'an', 'some'].includes(entityWord) &&
        entityMap.has(entityWord)
      ) {
        mainEntity = entityMap.get(entityWord)
      }
    }
  }

  // Fallback: first mentioned entity
  if (!mainEntity && mentionedEntities.length > 0) {
    mainEntity = mentionedEntities[0]
  }

  // Extract specific fields to SELECT
  const selectFields = extractSelectFields(prompt, mainEntity)

  // Extract conditions (WHERE clauses) - robust + AND/OR
  const conditions = extractConditions(prompt, mentionedEntities, entityMap)

  // ✅ Include entities referenced only inside conditions
  conditions.forEach((c) => {
    const byName =
      c.entity ||
      (c.entityName &&
        entities.find(
          (e) => e.name.toLowerCase() === c.entityName.toLowerCase()
        ))
    if (byName && !mentionedEntities.find((e) => e.name === byName.name)) {
      mentionedEntities.push(byName)
    }
  })

  // Extract LIMIT
  const limitMatch = prompt.match(
    /limit\s+(?:to)?\s*(\d+)|(\d+)\s+(?:first|results?|rows?)/i
  )
  const limit = limitMatch ? parseInt(limitMatch[1] || limitMatch[2]) : null

  // Extract ORDER BY
  const orderBy = extractOrderBy(prompt)

  return {
    action,
    mainEntity,
    entities: mentionedEntities,
    selectFields,
    conditions,
    limit,
    orderBy,
  }
}

/**
 * Extract specific fields to SELECT from prompt
 */
function extractSelectFields(prompt, mainEntity) {
  if (!mainEntity) return null

  // Pattern: "show/get entity field1 field2"
  const entityName = mainEntity.name.toLowerCase()

  // Try to find fields mentioned after entity name
  const pattern = new RegExp(
    `(?:show|get|list|select)\\s+${entityName}\\s+((?:id|\\w+)(?:\\s+(?:and\\s+)?\\w+)*)`,
    'i'
  )
  const match = prompt.match(pattern)

  if (!match) return null

  const fieldsText = match[1]

  // Check if it's "all" or similar
  if (fieldsText.match(/^(all|everything|\*)$/i)) return null

  // Extract field names
  const fields = fieldsText
    .toLowerCase()
    .replace(/\band\b/g, ' ')
    .split(/\s+/)
    .filter((f) => f && f !== 'and')
    .filter((f) => {
      // Only keep fields that exist in the entity or are common SQL fields
      const fieldExists = mainEntity.fields?.some(
        (field) => field.name.toLowerCase() === f
      )
      return fieldExists || f === 'id' || f === 'name'
    })

  return fields.length > 0 ? fields : null
}

/**
 * Extract WHERE conditions from prompt (English only)
 * Robust to EOL, supports AND/OR
 */
function extractConditions(prompt, mentionedEntities, entityMap) {
  const conditions = []

  // Capture until ORDER|LIMIT or end of string (no trailing space required)
  const whereMatch = prompt.match(
    /(?:where|with)\s+(.+?)(?=\s+(?:limit|order)\b|$)/i
  )
  if (!whereMatch) return conditions

  const whereClause = whereMatch[1].trim()

  // Split into tokens while keeping connectors (AND/OR)
  const tokens = whereClause.split(/\s+(and|or)\s+/i).filter(Boolean)

  for (let i = 0; i < tokens.length; i++) {
    const token = tokens[i].trim()
    if (/^(and|or)$/i.test(token)) {
      conditions.push({ connector: token.toUpperCase() })
    } else {
      const parsed = parseCondition(token, mentionedEntities, entityMap)
      if (parsed) conditions.push(parsed)
    }
  }

  return conditions
}

/**
 * Parse a single condition string
 * Supports many operators and entity.field form
 */
function parseCondition(conditionStr, mentionedEntities, entityMap) {
  const s = conditionStr.trim()

  // Textual operators → SQL
  const wordOps = [
    { re: /\bnot equal to\b/i, op: '<>' },
    { re: /\bequal to\b|\bis\b/i, op: '=' },
    { re: /\bgreater than or equal to\b/i, op: '>=' },
    { re: /\bless than or equal to\b/i, op: '<=' },
    { re: /\bgreater than\b/i, op: '>' },
    { re: /\bless than\b/i, op: '<' },
    { re: /\bcontains\b/i, op: 'CONTAINS' },
    { re: /\bstarts with\b/i, op: 'STARTS' },
    { re: /\bends with\b/i, op: 'ENDS' },
    { re: /\blike\b/i, op: 'LIKE' },
  ]

  let op = null
  for (const { re, op: mapped } of wordOps) {
    if (re.test(s)) {
      op = mapped
      break
    }
  }

  if (!op) {
    const sym = s.match(/\s(>=|<=|<>|!=|=|>|<)\s/)
    if (sym) op = sym[1] === '!=' ? '<>' : sym[1]
  }

  // Extract LHS (entity.field or field) + RHS (quoted or not, multi-words)
  const pattern = op
    ? new RegExp(
      `^(\\w+(?:\\.\\w+)?)\\s+(?:${op.replace(
        /[.*+?^${}()|[\]\\]/g,
        '\\$&'
      )}|${wordOps
        .filter((o) => o.op === op)
        .map((o) => o.re.source)
        .join('|')})\\s+["']?(.+?)["']?$`,
      'i'
    )
    : /^(\w+(?:\.\w+)?)\s+["']?(.+?)["']?$/i // fallback (rare)
  const m = s.match(pattern)
  if (!m) return null

  const lhs = m[1] // entity.field OR field
  const value = (m[2] ?? '').trim()

  // Determine entity + field if "entity.field"
  let entity = null,
    entityName = null,
    fieldName = null
  if (lhs.includes('.')) {
    const [ent, fld] = lhs.split('.')
    entityName = ent
    fieldName = fld
    entity = entityMap.get(ent.toLowerCase()) || null
  } else {
    fieldName = lhs
  }

  return {
    field: fieldName,
    operator: op || '=',
    value,
    entity: entity || null,
    entityName: entity ? entity.name : entityName || null,
  }
}

/**
 * Extract ORDER BY from prompt (English only)
 */
function extractOrderBy(prompt) {
  const orderPatterns = [
    /order\s+by\s+(\w+)(?:\s+(asc|desc|ascending|descending))?/i,
    /sort\s+by\s+(\w+)(?:\s+(asc|desc|ascending|descending))?/i,
  ]

  for (const pattern of orderPatterns) {
    const match = prompt.match(pattern)
    if (match) {
      const field = match[1]
      let direction = 'ASC'
      if (match[2] && match[2].toLowerCase().includes('desc')) {
        direction = 'DESC'
      }
      return { field, direction }
    }
  }

  return null
}

/**
 * Find relation paths between entities
 */
function findRelationPaths(parsed, entities) {
  const { mainEntity, entities: mentionedEntities } = parsed

  if (mentionedEntities.length === 1) {
    // Single entity, no joins needed
    return {
      success: true,
      paths: [],
    }
  }

  // Find paths from main entity to all other mentioned entities
  const paths = []
  const missingPaths = []

  mentionedEntities.forEach((targetEntity) => {
    if (targetEntity.name === mainEntity.name) return

    const foundPaths = findPaths(mainEntity, targetEntity, entities, 5)

    if (foundPaths.length === 0) {
      missingPaths.push({
        from: mainEntity.name,
        to: targetEntity.name,
      })
    } else {
      // Use shortest path
      paths.push(foundPaths[0])
    }
  })

  if (missingPaths.length > 0) {
    return {
      success: false,
      error: 'NO_PATH_FOUND',
      message: `Cannot find relationships between entities.`,
      missingPaths,
      suggestions: [
        `Add relations to connect ${missingPaths
          .map((p) => `${p.from} → ${p.to}`)
          .join(', ')}`,
        'Check if entity names are spelled correctly',
        'Try using entities that are directly related',
      ],
    }
  }

  return {
    success: true,
    paths,
  }
}

/**
 * Build SQL from parsed data and paths
 */
function buildSql(parsed, paths /*, entities */) {
  const { mainEntity, selectFields, conditions, limit, orderBy, action } = parsed

  if (action !== 'SELECT') {
    return `-- Only SELECT queries are supported currently\n-- Action detected: ${action}`
  }

  let sql = ''

  // SELECT clause
  const mainAlias = getAlias(mainEntity.name)

  if (selectFields && selectFields.length > 0) {
    const fieldList = selectFields
      .map((f) => `${mainAlias}.${quoteIdent(f)}`)
      .join(', ')
    sql += `SELECT ${fieldList}\n`
  } else {
    // All fields
    sql += `SELECT ${mainAlias}.*\n`
  }

  // FROM clause
  sql += `FROM ${quoteIdent(mainEntity.table)} ${mainAlias}\n`

  // JOINs from paths
  const joins = buildJoins(paths)
  if (joins.length > 0) {
    sql += joins.join('\n') + '\n'
  }

  // WHERE clause (with AND/OR connectors)
  if (conditions.length > 0) {
    const parts = []
    for (const cond of conditions) {
      if (cond.connector) {
        parts.push(cond.connector) // "AND" / "OR"
      } else {
        const resolved = resolveCondition(cond, [
          mainEntity,
          ...paths.flatMap((p) => p.entities),
        ])
        if (resolved) parts.push(resolved)
      }
    }
    // Remove orphan connectors at edges
    const compact = []
    for (let i = 0; i < parts.length; i++) {
      if (
        (parts[i] === 'AND' || parts[i] === 'OR') &&
        (i === 0 || i === parts.length - 1)
      )
        continue
      compact.push(parts[i])
    }
    if (compact.length > 0) {
      const pretty = compact
        .map((p) => ((p === 'AND' || p === 'OR') ? p : `  ${p}`))
        .join('\n')
      sql += `WHERE\n${pretty}\n`
    }
  }

  // ORDER BY clause
  if (orderBy) {
    sql += `ORDER BY ${mainAlias}.${quoteIdent(orderBy.field)} ${orderBy.direction}\n`
  }

  // LIMIT clause
  if (limit) {
    sql += `LIMIT ${limit}`
  }

  return sql.trim()
}

/**
 * Build JOIN clauses from paths
 * Uses mappedBy/inversedBy for correct join conditions
 */
function buildJoins(paths) {
  const joins = []
  const seenJoins = new Set()

  paths.forEach((path) => {
    path.relations.forEach((relation, index) => {
      const fromEntity = path.entities[index]
      const toEntity = path.entities[index + 1]

      const fromAlias = getAlias(fromEntity.name)
      const toAlias = getAlias(toEntity.name)

      const joinKey = `${fromAlias}-${toAlias}`
      if (seenJoins.has(joinKey)) return

      // Determine join condition based on relation type and ownership
      let joinCondition = ''

      if (relation.type === 2 || relation.type === 1) {
        // ManyToOne or OneToOne (owning side)
        // The fromEntity has the foreign key
        // Ex: Address.user → User: address.user_id = user.id
        joinCondition = `${fromAlias}.${quoteIdent(relation.field)}_id = ${toAlias}.id`
      } else if (relation.type === 4) {
        // OneToMany (inverse side)
        // The toEntity has the foreign key pointing back to fromEntity
        // Use mappedBy to find the correct field name in toEntity
        const foreignKeyField = relation.mappedBy || relation.field
        joinCondition = `${toAlias}.${quoteIdent(foreignKeyField)}_id = ${fromAlias}.id`
      } else {
        // ManyToMany or other (would need junction table, not supported yet)
        joinCondition = `${toAlias}.${quoteIdent(relation.field)}_id = ${fromAlias}.id`
      }

      joins.push(
        `INNER JOIN ${quoteIdent(toEntity.table)} ${toAlias} ON ${joinCondition}`
      )
      seenJoins.add(joinKey)
    })
  })

  return joins
}

/**
 * Resolve which table a condition belongs to
 * Now supports conditions with pre-resolved entities (entity.field syntax)
 */
function resolveCondition(condition, involvedEntities) {
  const { field, operator, value, entity, entityName } = condition

  if (!field) return null

  const normOp = normalizeOperator(operator || '=')

  // If entity was already resolved from "entity.field" syntax, use it
  let targetEntity = null
  if (entity) {
    targetEntity = entity
  } else if (entityName) {
    targetEntity =
      involvedEntities.find(
        (e) => e.name.toLowerCase() === entityName.toLowerCase()
      ) || null
  } else {
    // Try to find which entity has this field
    targetEntity =
      involvedEntities.find((e) =>
        e.fields?.some(
          (f) => f.name.toLowerCase() === String(field).toLowerCase()
        )
      ) || involvedEntities[0]
  }

  const alias = getAlias(targetEntity.name)
  const meta = getFieldMeta(targetEntity, field)
  const rhs = coerceValueForSQL(value, meta, operator)

  // Field not found, generate with comment
  if (!meta) {
    return `${alias}.${quoteIdent(field)} ${normOp} ${rhs} -- ⚠️ Field '${field}' not found in schema`
  }

  return `${alias}.${quoteIdent(field)} ${normOp} ${rhs}`
}

/**
 * Generate explanation of the query
 */
function generateExplanation(parsed, paths) {
  const { mainEntity, entities, conditions, limit } = parsed

  let explanation = `This query retrieves all ${mainEntity.name} records`

  if (paths.length > 0) {
    const joinedEntities = [
      ...new Set(paths.flatMap((p) => p.entities.map((e) => e.name))),
    ].filter((name) => name !== mainEntity.name)

    if (joinedEntities.length) {
      explanation += ` with related ${joinedEntities.join(', ')}`
    }
  }

  const conds = conditions.filter((c) => !c.connector)
  if (conds.length > 0) {
    explanation += ` where ${conds
      .map((c) => `${c.field} ${normalizeOperator(c.operator)} ${c.value}`)
      .join(' and ')}`
  }

  if (limit) {
    explanation += `, limited to ${limit} result${limit > 1 ? 's' : ''}`
  }

  explanation += '.'

  return explanation
}

/**
 * Calculate confidence score
 */
function calculateConfidence(parsed, pathResults) {
  let confidence = 0.5

  // Main entity found
  if (parsed.mainEntity) confidence += 0.2

  // Paths found
  if (pathResults.paths) confidence += 0.2

  // Conditions parsed
  if (parsed.conditions.length > 0) confidence += 0.1

  return Math.min(confidence, 1.0)
}

/**
 * Get alias for entity (first letter lowercase)
 */
function getAlias(entityName) {
  return entityName[0].toLowerCase()
}

/**
 * Simple pluralization
 */
function pluralize(word) {
  if (word.endsWith('s')) return word
  if (word.endsWith('y')) return word.slice(0, -1) + 'ies'
  if (
    word.endsWith('x') ||
    word.endsWith('z') ||
    word.endsWith('ch') ||
    word.endsWith('sh')
  ) {
    return word + 'es'
  }
  return word + 's'
}
