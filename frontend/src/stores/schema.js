import { defineStore } from 'pinia'
import { ref, computed, shallowRef } from 'vue'
import { usePlaygroundStore } from './playground'

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
  const rawEntities = ref([])
  const loading = ref(false)
  const error = ref(null)
  const selectedEntity = ref(null)
  const focusedEntity = ref(null)
  const searchQuery = ref('')
  const selectedEntities = ref(new Set())

  const relationIndex = shallowRef(null)

  const entities = computed(() => {
    try {
      const playgroundStore = usePlaygroundStore()
      if (playgroundStore.isActive) {
        return playgroundStore.playgroundSchema
      }
    } catch (e) {
    }
    return rawEntities.value
  })

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

  // Group entities by namespace
  const namespaces = computed(() => {
    const nsMap = new Map()

    entities.value.forEach(entity => {
      const fqcn = entity.fqcn || entity.name
      const parts = fqcn.split('\\')
      const namespace = parts.slice(0, -1).join('\\') || 'Default'

      if (!nsMap.has(namespace)) {
        nsMap.set(namespace, {
          name: namespace,
          entities: [],
          relationCount: 0
        })
      }

      nsMap.get(namespace).entities.push(entity)
    })

    // Calculate relation counts
    nsMap.forEach(ns => {
      let internalRelations = 0
      let externalRelations = 0

      ns.entities.forEach(entity => {
        entity.relations?.forEach(rel => {
          const targetNs = rel.target.split('\\').slice(0, -1).join('\\') || 'Default'
          if (targetNs === ns.name) {
            internalRelations++
          } else {
            externalRelations++
          }
        })
      })

      ns.relationCount = internalRelations + externalRelations
      ns.internalRelations = internalRelations
      ns.externalRelations = externalRelations
    })

    return Array.from(nsMap.values()).sort((a, b) => a.name.localeCompare(b.name))
  })

  // Get selected entities with their direct relations
  const selectedEntitiesWithRelations = computed(() => {
    if (selectedEntities.value.size === 0) {
      return []
    }

    const entityMap = new Map(entities.value.map(e => [e.fqcn || e.name, e]))
    const resultSet = new Set()

    // Add all selected entities
    selectedEntities.value.forEach(id => {
      const entity = entityMap.get(id)
      if (entity) {
        resultSet.add(entity)
      }
    })

    // Add their direct relations
    selectedEntities.value.forEach(id => {
      const related = getRelatedEntities(id)
      related.forEach(e => resultSet.add(e))
    })

    return Array.from(resultSet)
  })

  async function fetchSchema() {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/schema')
      if (!res.ok) throw new Error('API error')

      const data = await res.json()
      rawEntities.value = data.entities || []

      relationIndex.value = buildRelationIndex(entities.value)
    } catch (e) {
      error.value = e.message
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

  // Multi-selection functions
  function toggleEntitySelection(fqcn) {
    const newSet = new Set(selectedEntities.value)
    if (newSet.has(fqcn)) {
      newSet.delete(fqcn)
    } else {
      newSet.add(fqcn)
    }
    selectedEntities.value = newSet
  }

  function addEntityToSelection(fqcn) {
    const newSet = new Set(selectedEntities.value)
    newSet.add(fqcn)
    selectedEntities.value = newSet
  }

  function removeEntityFromSelection(fqcn) {
    const newSet = new Set(selectedEntities.value)
    newSet.delete(fqcn)
    selectedEntities.value = newSet
  }

  function clearSelectedEntities() {
    selectedEntities.value = new Set()
  }

  function setSelectedEntities(fqcns) {
    selectedEntities.value = new Set(fqcns)
  }

  return {
    entities,
    rawEntities,
    loading,
    error,
    selectedEntity,
    focusedEntity,
    searchQuery,
    selectedEntities,
    relationIndex,

    filteredEntities,
    schemaEntities,
    getEntityById,
    totalEntities,
    totalFields,
    totalRelations,
    namespaces,
    selectedEntitiesWithRelations,

    fetchSchema,
    selectEntity,
    clearSelection,
    focusEntity,
    clearFocus,
    setSearchQuery,
    getRelatedEntities,
    toggleEntitySelection,
    addEntityToSelection,
    removeEntityFromSelection,
    clearSelectedEntities,
    setSelectedEntities
  }
})
