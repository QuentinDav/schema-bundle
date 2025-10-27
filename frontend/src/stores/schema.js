import { defineStore } from 'pinia'
import { ref, computed, shallowRef } from 'vue'

export function getRelationTypeName(type) {
  const types = {
    1: 'OneToOne',
    2: 'ManyToOne',
    4: 'OneToMany',
    8: 'ManyToMany',
  }
  return types[type] || 'Unknown'
}

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

function buildRelationIndex(entities) {
  const index = new Map()

  entities.forEach(entity => {
    const entityId = entity.fqcn || entity.name
    const relations = {
      outgoing: [],
      incoming: []
    }

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
  const entities = ref([])
  const loading = ref(false)
  const error = ref(null)
  const selectedEntity = ref(null)
  const focusedEntity = ref(null)
  const searchQuery = ref('')

  const relationIndex = shallowRef(null)

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

  const schemaEntities = computed(() => {
    if (!focusedEntity.value) return filteredEntities.value

    const relatedEntityNames = new Set()
    relatedEntityNames.add(focusedEntity.value.name)

    focusedEntity.value.relations?.forEach((rel) => {
      relatedEntityNames.add(rel.target)
    })

    entities.value.forEach((entity) => {
      entity.relations?.forEach((rel) => {
        if (rel.target === focusedEntity.value.name) {
          relatedEntityNames.add(entity.name)
        }
      })
    })

    return filteredEntities.value.filter((e) => relatedEntityNames.has(e.name))
  })

  function getRelatedEntities(entityId) {
    if (!relationIndex.value) {
      relationIndex.value = buildRelationIndex(entities.value)
    }

    const relations = relationIndex.value.get(entityId)
    if (!relations) return []

    const relatedIds = new Set()
    relatedIds.add(entityId)

    relations.outgoing.forEach(rel => {
      relatedIds.add(rel.target)
    })

    relations.incoming.forEach(rel => {
      relatedIds.add(rel.source)
    })

    return entities.value.filter(e => relatedIds.has(e.fqcn || e.name))
  }

  async function fetchSchema() {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/schema')
      if (!res.ok) throw new Error('API error')

      const data = await res.json()
      entities.value = data.entities || []

      relationIndex.value = buildRelationIndex(entities.value)
    } catch (e) {
      error.value = e.message
      console.error('Error fetching schema:', e)
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
    entities,
    loading,
    error,
    selectedEntity,
    focusedEntity,
    searchQuery,
    relationIndex,

    filteredEntities,
    schemaEntities,
    getEntityById,
    totalEntities,
    totalFields,
    totalRelations,

    fetchSchema,
    selectEntity,
    clearSelection,
    focusEntity,
    clearFocus,
    setSearchQuery,
    getRelatedEntities
  }
})
