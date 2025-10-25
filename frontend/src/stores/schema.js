import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

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

export const useSchemaStore = defineStore('schema', () => {
  // State
  const entities = ref([])
  const loading = ref(false)
  const error = ref(null)
  const selectedEntity = ref(null)
  const focusedEntity = ref(null) // For schema view focus mode
  const searchQuery = ref('')

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

  // Actions
  async function fetchSchema() {
    loading.value = true
    error.value = null

    try {
      const res = await fetch('/schema-doc/api/schema')
      if (!res.ok) throw new Error('API error')

      const data = await res.json()
      entities.value = data.entities || []
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
    reloadSnapshot
  }
})
