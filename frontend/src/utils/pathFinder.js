/**
 * PathFinder Service
 *
 * Finds all paths between two entities in a schema graph using BFS algorithm.
 * Useful for understanding how entities are connected through relations.
 */

/**
 * Find all paths between source and target entities
 *
 * @param {Object} sourceEntity - Starting entity
 * @param {Object} targetEntity - Destination entity
 * @param {Array} allEntities - All entities in the schema
 * @param {Number} maxDepth - Maximum path length (default: 5)
 * @returns {Array} Array of paths, each containing entities and relations
 */
export function findPaths(sourceEntity, targetEntity, allEntities, maxDepth = 5) {
  if (!sourceEntity || !targetEntity) {
    return []
  }

  const sourceId = sourceEntity.fqcn || sourceEntity.name
  const targetId = targetEntity.fqcn || targetEntity.name

  // Same entity
  if (sourceId === targetId) {
    return [{
      path: [sourceEntity],
      length: 0,
      relations: [],
      entities: [sourceEntity]
    }]
  }

  // Build maps for lookups
  const entityMapByFqcn = new Map(allEntities.map(e => [e.fqcn || e.name, e]))
  const entityMapByName = new Map(allEntities.map(e => [e.name, e]))

  // Build adjacency map for faster lookups
  const adjacencyMap = buildAdjacencyMap(allEntities, entityMapByName)
  const entityMap = entityMapByFqcn

  // BFS to find all paths
  const paths = []
  const queue = [{
    currentId: sourceId,
    path: [sourceId],
    relations: [],
    visited: new Set([sourceId])
  }]

  while (queue.length > 0) {
    const { currentId, path, relations, visited } = queue.shift()

    // Stop if max depth reached
    if (path.length > maxDepth) {
      continue
    }

    // Get neighbors
    const neighbors = adjacencyMap.get(currentId) || []

    for (const { targetId: neighborId, relation } of neighbors) {
      // Skip if already visited (avoid cycles)
      if (visited.has(neighborId)) {
        continue
      }

      const newPath = [...path, neighborId]
      const newRelations = [...relations, relation]
      const newVisited = new Set([...visited, neighborId])

      // Found target!
      if (neighborId === targetId) {
        paths.push({
          path: newPath.map(id => entityMap.get(id)),
          length: newPath.length - 1,
          relations: newRelations,
          entities: newPath.map(id => entityMap.get(id))
        })
        continue
      }

      // Continue searching
      queue.push({
        currentId: neighborId,
        path: newPath,
        relations: newRelations,
        visited: newVisited
      })
    }
  }

  // Sort by path length (shortest first)
  return paths.sort((a, b) => a.length - b.length)
}

/**
 * Build adjacency map for efficient graph traversal
 *
 * @param {Array} entities - All entities
 * @param {Map} entityMapByName - Map of entity name to entity object
 * @returns {Map} Adjacency map: entityId -> [{targetId, relation}]
 */
function buildAdjacencyMap(entities, entityMapByName) {
  const adjacencyMap = new Map()

  entities.forEach(entity => {
    const entityId = entity.fqcn || entity.name

    if (!adjacencyMap.has(entityId)) {
      adjacencyMap.set(entityId, [])
    }

    if (entity.relations) {
      entity.relations.forEach(relation => {
        // Resolve target name to FQCN for consistency
        const targetEntity = entityMapByName.get(relation.target)
        const targetId = targetEntity ? (targetEntity.fqcn || targetEntity.name) : relation.target

        adjacencyMap.get(entityId).push({
          targetId: targetId,
          relation: {
            field: relation.field,
            type: relation.type,
            isOwning: relation.isOwning,
            mappedBy: relation.mappedBy,      // Essential for correct JOIN generation
            inversedBy: relation.inversedBy,  // Essential for correct JOIN generation
            from: entity.name,
            to: relation.target
          }
        })
      })
    }
  })

  return adjacencyMap
}

/**
 * Get all entities involved in paths
 *
 * @param {Array} paths - Array of path objects
 * @returns {Array} Unique entities from all paths
 */
export function getEntitiesFromPaths(paths) {
  const entitySet = new Set()

  paths.forEach(path => {
    path.entities.forEach(entity => {
      entitySet.add(entity)
    })
  })

  return Array.from(entitySet)
}

/**
 * Get all relations involved in paths
 *
 * @param {Array} paths - Array of path objects
 * @returns {Array} Unique relations from all paths
 */
export function getRelationsFromPaths(paths, entities) {
  const relations = []
  const seenPairs = new Set()

  const entityMapByName = new Map(entities.map(e => [e.name, e]))
  const entityMapByFqcn = new Map(entities.map(e => [e.fqcn || e.name, e]))

  paths.forEach(path => {
    path.relations.forEach(relation => {
      const fromEntity = entityMapByName.get(relation.from) || entityMapByFqcn.get(relation.from)
      const toEntity = entityMapByName.get(relation.to) || entityMapByFqcn.get(relation.to)

      if (fromEntity && toEntity) {
        const pairKey = [
          fromEntity.fqcn || fromEntity.name,
          toEntity.fqcn || toEntity.name
        ].sort().join('|')

        if (!seenPairs.has(pairKey)) {
          seenPairs.add(pairKey)

          relations.push({
            from: fromEntity,
            to: toEntity,
            field: relation.field,
            type: relation.type,
            isOwning: relation.isOwning
          })
        }
      }
    })
  })

  return relations
}

/**
 * Format path for display
 *
 * @param {Object} path - Path object
 * @returns {String} Formatted path string
 */
export function formatPath(path) {
  if (!path || !path.entities || path.entities.length === 0) {
    return ''
  }

  return path.entities.map(e => e.name).join(' → ')
}

/**
 * Get path statistics
 *
 * @param {Array} paths - Array of path objects
 * @returns {Object} Statistics about paths
 */
export function getPathStats(paths) {
  if (paths.length === 0) {
    return {
      count: 0,
      shortestLength: 0,
      longestLength: 0,
      averageLength: 0
    }
  }

  const lengths = paths.map(p => p.length)

  return {
    count: paths.length,
    shortestLength: Math.min(...lengths),
    longestLength: Math.max(...lengths),
    averageLength: (lengths.reduce((a, b) => a + b, 0) / lengths.length).toFixed(1)
  }
}
