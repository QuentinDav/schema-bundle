import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useSchemaStore } from './schema'

/**
 * Pinia store for Schema Playground (What-if mode).
 *
 * Allows temporary modifications to the schema for experimentation
 * without affecting the actual database or schema.
 */
export const usePlaygroundStore = defineStore('playground', () => {
  const schemaStore = useSchemaStore()

  // State
  const isActive = ref(false)
  const temporaryEntities = ref([]) // New entities added in playground
  const temporaryRelations = ref([]) // New relations added in playground
  const hiddenEntities = ref(new Set()) // Entities hidden in playground
  const modifiedFields = ref(new Map()) // Modified fields per entity

  // Computed
  const hasModifications = computed(() => {
    return (
      temporaryEntities.value.length > 0 ||
      temporaryRelations.value.length > 0 ||
      hiddenEntities.value.size > 0 ||
      modifiedFields.value.size > 0
    )
  })

  /**
   * Get the playground schema (real schema + modifications).
   */
  const playgroundSchema = computed(() => {
    if (!isActive.value) {
      return schemaStore.entities
    }

    // Clone real entities
    let entities = JSON.parse(JSON.stringify(schemaStore.entities))

    // Filter hidden entities
    entities = entities.filter((e) => !hiddenEntities.value.has(e.fqcn || e.name))

    // Apply field modifications
    entities = entities.map((entity) => {
      const entityId = entity.fqcn || entity.name
      if (modifiedFields.value.has(entityId)) {
        const modifications = modifiedFields.value.get(entityId)
        return {
          ...entity,
          fields: [...(entity.fields || []), ...modifications.added],
        }
      }
      return entity
    })

    // Add temporary relations
    entities = entities.map((entity) => {
      const entityId = entity.fqcn || entity.name
      const tempRels = temporaryRelations.value.filter((r) => r.sourceEntity === entityId)

      if (tempRels.length > 0) {
        return {
          ...entity,
          relations: [
            ...(entity.relations || []),
            ...tempRels.map((r) => ({
              field: r.fieldName,
              target: r.targetEntity,
              type: r.relationType,
              isOwning: true,
              nullable: r.nullable,
              _temporary: true, // Mark as temporary
            })),
          ],
        }
      }

      return entity
    })

    // Add temporary entities
    entities = [...entities, ...temporaryEntities.value]

    return entities
  })

  /**
   * Activate playground mode.
   */
  function activate() {
    isActive.value = true
  }

  /**
   * Deactivate playground mode.
   */
  function deactivate() {
    isActive.value = false
  }

  /**
   * Toggle playground mode.
   */
  function toggle() {
    isActive.value = !isActive.value
  }

  /**
   * Add a temporary relation between two entities.
   *
   * @param {Object} relation - Relation definition
   * @param {string} relation.sourceEntity - Source entity FQCN
   * @param {string} relation.targetEntity - Target entity FQCN
   * @param {string} relation.fieldName - Field name for the relation
   * @param {number} relation.relationType - Doctrine relation type (1,2,4,8)
   * @param {boolean} relation.nullable - Is nullable
   */
  function addTemporaryRelation(relation) {
    const id = `${relation.sourceEntity}.${relation.fieldName}`

    // Check if already exists
    const exists = temporaryRelations.value.some(
      (r) => r.sourceEntity === relation.sourceEntity && r.fieldName === relation.fieldName
    )

    if (exists) {
      return
    }

    temporaryRelations.value.push({
      id,
      ...relation,
      createdAt: new Date().toISOString(),
    })
  }

  /**
   * Remove a temporary relation.
   *
   * @param {string} relationId - Relation ID
   */
  function removeTemporaryRelation(relationId) {
    const index = temporaryRelations.value.findIndex((r) => r.id === relationId)
    if (index !== -1) {
      temporaryRelations.value.splice(index, 1)
    }
  }

  /**
   * Add a temporary entity.
   *
   * @param {Object} entity - Entity definition
   */
  function addTemporaryEntity(entity) {
    const id = entity.fqcn || entity.name

    // Check if already exists
    const exists = temporaryEntities.value.some((e) => (e.fqcn || e.name) === id)

    if (exists) {
      return
    }

    temporaryEntities.value.push({
      ...entity,
      _temporary: true,
      createdAt: new Date().toISOString(),
    })
  }

  /**
   * Remove a temporary entity.
   *
   * @param {string} entityId - Entity FQCN or name
   */
  function removeTemporaryEntity(entityId) {
    const index = temporaryEntities.value.findIndex((e) => (e.fqcn || e.name) === entityId)
    if (index !== -1) {
      temporaryEntities.value.splice(index, 1)
    }
  }

  /**
   * Hide an entity (from real schema).
   *
   * @param {string} entityId - Entity FQCN or name
   */
  function hideEntity(entityId) {
    hiddenEntities.value.add(entityId)
  }

  /**
   * Show a hidden entity.
   *
   * @param {string} entityId - Entity FQCN or name
   */
  function showEntity(entityId) {
    hiddenEntities.value.delete(entityId)
  }

  /**
   * Add a temporary field to an entity.
   *
   * @param {string} entityId - Entity FQCN or name
   * @param {Object} field - Field definition
   */
  function addTemporaryField(entityId, field) {
    if (!modifiedFields.value.has(entityId)) {
      modifiedFields.value.set(entityId, { added: [], modified: [] })
    }

    const modifications = modifiedFields.value.get(entityId)
    modifications.added.push({
      ...field,
      _temporary: true,
    })
  }

  /**
   * Clear all playground modifications.
   */
  function clearAll() {
    temporaryEntities.value = []
    temporaryRelations.value = []
    hiddenEntities.value.clear()
    modifiedFields.value.clear()
  }

  /**
   * Reset playground to initial state.
   */
  function reset() {
    clearAll()
    isActive.value = false
  }

  /**
   * Export playground modifications as JSON.
   */
  function exportModifications() {
    return {
      entities: temporaryEntities.value,
      relations: temporaryRelations.value,
      hiddenEntities: Array.from(hiddenEntities.value),
      modifiedFields: Object.fromEntries(modifiedFields.value),
      exportedAt: new Date().toISOString(),
    }
  }

  /**
   * Import playground modifications from JSON.
   *
   * @param {Object} data - Exported modifications
   */
  function importModifications(data) {
    temporaryEntities.value = data.entities || []
    temporaryRelations.value = data.relations || []
    hiddenEntities.value = new Set(data.hiddenEntities || [])
    modifiedFields.value = new Map(Object.entries(data.modifiedFields || {}))
  }

  /**
   * Get relation type name.
   *
   * @param {number} type - Doctrine relation type
   * @returns {string}
   */
  function getRelationTypeName(type) {
    const types = {
      1: 'OneToOne',
      2: 'ManyToOne',
      4: 'OneToMany',
      8: 'ManyToMany',
    }
    return types[type] || 'Unknown'
  }

  /**
   * Get available relation types.
   */
  const relationTypes = [
    { value: 1, label: 'OneToOne', description: '1:1 relationship' },
    { value: 2, label: 'ManyToOne', description: 'N:1 relationship' },
    { value: 4, label: 'OneToMany', description: '1:N relationship' },
    { value: 8, label: 'ManyToMany', description: 'N:N relationship' },
  ]

  return {
    // State
    isActive,
    temporaryEntities,
    temporaryRelations,
    hiddenEntities,
    modifiedFields,

    // Computed
    hasModifications,
    playgroundSchema,

    // Actions
    activate,
    deactivate,
    toggle,
    addTemporaryRelation,
    removeTemporaryRelation,
    addTemporaryEntity,
    removeTemporaryEntity,
    hideEntity,
    showEntity,
    addTemporaryField,
    clearAll,
    reset,
    exportModifications,
    importModifications,
    getRelationTypeName,

    // Helpers
    relationTypes,
  }
})
