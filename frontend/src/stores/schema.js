import { defineStore } from 'pinia'
import { ref, computed, shallowRef } from 'vue'

// Helper to convert relation type number to name
// 1: OneToOne, 2: OneToMany, 3: ManyToOne, 4: ManyToMany
export function getRelationTypeName(type) {
  const types = {
    1: 'OneToOne',
    2: 'ManyToOne',
    4: 'OneToMany',
    8: 'ManyToMany',
  }
  return types[type] || 'Unknown'
}

// Memoization helper for expensive computations
function memoize(fn) {
  const cache = new Map()
  return (...args) => {
    const key = JSON.stringify(args)
    if (cache.has(key)) {
      return cache.get(key)
    }
    const result = fn(...args)
    cache.set(key, result)
    return result
  }
}

// Build relation index for fast lookups
function buildRelationIndex(entities) {
  const index = new Map()

  entities.forEach(entity => {
    const entityId = entity.fqcn || entity.name
    const relations = {
      outgoing: [],
      incoming: []
    }

    // Outgoing relations (from this entity)
    if (entity.relations) {
      entity.relations.forEach(rel => {
        relations.outgoing.push({
          target: rel.target,
          field: rel.field,
          type: rel.type,
          isOwning: rel.isOwning
        })
      })
    }

    index.set(entityId, relations)
  })

  // Incoming relations (to this entity)
  entities.forEach(entity => {
    if (entity.relations) {
      entity.relations.forEach(rel => {
        const targetRelations = index.get(rel.target)
        if (targetRelations) {
          targetRelations.incoming.push({
            source: entity.fqcn || entity.name,
            field: rel.field,
            type: rel.type,
            isOwning: rel.isOwning
          })
        }
      })
    }
  })

  return index
}

export const useSchemaStore = defineStore('schema', () => {
  // State
  const entities = ref([])
  const loading = ref(false)
  const error = ref(null)
  const selectedEntity = ref(null)
  const focusedEntity = ref(null) // For schema view focus mode
  const searchQuery = ref('')

  // Performance: Relation index cache
  const relationIndex = shallowRef(null)

  // Getters
  const filteredEntities = computed(() => {
    if (!searchQuery.value) return entities.value

    const query = searchQuery.value.toLowerCase()
    return entities.value.filter((entity) => {
      return (
        entity.name.toLowerCase().includes(query) ||
        entity.table?.toLowerCase().includes(query)
      )
    })
  })

  const getEntityById = computed(() => {
    return (fqcn) => entities.value.find((e) => e.fqcn === fqcn)
  })

  const totalEntities = computed(() => entities.value.length)

  const totalFields = computed(() => {
    return entities.value.reduce((acc, entity) => acc + (entity.fields?.length || 0), 0)
  })

  const totalRelations = computed(() => {
    return entities.value.reduce((acc, entity) => {
      return acc + (entity.relations?.length || 0)
    }, 0)
  })

  // Entities to display in schema (with focus mode support)
  const schemaEntities = computed(() => {
    if (!focusedEntity.value) return filteredEntities.value

    // Get related entity names
    const relatedEntityNames = new Set()
    relatedEntityNames.add(focusedEntity.value.name)

    // Add all entities that are targets of relations
    focusedEntity.value.relations?.forEach((rel) => {
      relatedEntityNames.add(rel.target)
    })

    // Add all entities that have relations pointing to the focused entity
    entities.value.forEach((entity) => {
      entity.relations?.forEach((rel) => {
        if (rel.target === focusedEntity.value.name) {
          relatedEntityNames.add(entity.name)
        }
      })
    })

    return filteredEntities.value.filter((e) => relatedEntityNames.has(e.name))
  })

  // Get related entities for a given entity (optimized with index)
  function getRelatedEntities(entityId) {
    if (!relationIndex.value) {
      relationIndex.value = buildRelationIndex(entities.value)
    }

    const relations = relationIndex.value.get(entityId)
    if (!relations) return []

    const relatedIds = new Set()
    relatedIds.add(entityId)

    // Add outgoing relations
    relations.outgoing.forEach(rel => {
      relatedIds.add(rel.target)
    })

    // Add incoming relations
    relations.incoming.forEach(rel => {
      relatedIds.add(rel.source)
    })

    return entities.value.filter(e => relatedIds.has(e.fqcn || e.name))
  }

  // Actions
  async function fetchSchema() {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/schema')
      if (!res.ok) throw new Error('API error')

      const data = await res.json()
      entities.value = data.entities || []

      // Build relation index for fast lookups
      relationIndex.value = buildRelationIndex(entities.value)
    } catch (e) {
      error.value = e.message
      console.error('Error fetching schema:', e)
    } finally {
      loading.value = false
    }
  }

  async function reloadSnapshot()
  {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/snapshot')
      if(!res.ok) throw new Error('API error')

      await fetchSchema()
    } catch (e) {
      error.value = e.message
      console.error('Error reload snapshot:', e)
    } finally {
      loading.value = false
    }
  }

  function selectEntity(fqcn) {
    selectedEntity.value = entities.value.find((e) => e.fqcn === fqcn)
  }

  function clearSelection() {
    selectedEntity.value = null
  }

  function setSearchQuery(query) {
    searchQuery.value = query
  }

  function focusEntity(fqcn) {
    focusedEntity.value = entities.value.find((e) => e.fqcn === fqcn)
  }

  function clearFocus() {
    focusedEntity.value = null
  }

  return {
    // State
    entities,
    loading,
    error,
    selectedEntity,
    focusedEntity,
    searchQuery,
    relationIndex,

    // Getters
    filteredEntities,
    schemaEntities,
    getEntityById,
    totalEntities,
    totalFields,
    totalRelations,

    // Actions
    fetchSchema,
    selectEntity,
    clearSelection,
    focusEntity,
    clearFocus,
    setSearchQuery,
    reloadSnapshot,
    getRelatedEntities
  }
})
