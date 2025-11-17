import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

export const useAliasesStore = defineStore('aliases', () => {
  const aliases = ref({}) // { entityFqcn: [{ id, alias, language, description, ... }] }
  const loading = ref(false)
  const error = ref(null)

  /**
   * Get aliases for a specific entity
   */
  const getAliasesForEntity = computed(() => (entityFqcn) => {
    return aliases.value[entityFqcn] || []
  })

  /**
   * Get count of aliases for an entity
   */
  const getAliasCountForEntity = computed(() => (entityFqcn) => {
    return (aliases.value[entityFqcn] || []).length
  })

  /**
   * Fetch all aliases from the server
   */
  async function fetchAllAliases() {
    loading.value = true
    error.value = null

    try {
      const response = await fetch('/schema-doc/api/aliases')
      if (!response.ok) throw new Error('Failed to fetch aliases')

      const data = await response.json()
      aliases.value = data
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch aliases for a specific entity
   */
  async function fetchAliasesForEntity(entityFqcn) {
    loading.value = true
    error.value = null

    try {
      const encodedFqcn = encodeURIComponent(entityFqcn)
      const response = await fetch(`/schema-doc/api/aliases/entity/${encodedFqcn}`)
      if (!response.ok) throw new Error('Failed to fetch aliases')

      const data = await response.json()
      aliases.value[entityFqcn] = data
    } catch (err) {
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a new alias
   */
  async function createAlias(aliasData) {
    loading.value = true
    error.value = null

    try {
      const response = await fetch('/schema-doc/api/aliases', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(aliasData)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.error || errorData.errors || 'Failed to create alias')
      }

      const createdAlias = await response.json()

      // Add to local state
      const { entityFqcn } = createdAlias
      if (!aliases.value[entityFqcn]) {
        aliases.value[entityFqcn] = []
      }
      aliases.value[entityFqcn].push(createdAlias)

      return createdAlias
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing alias
   */
  async function updateAlias(id, updates) {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`/schema-doc/api/aliases/${id}`, {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(updates)
      })

      if (!response.ok) {
        const errorData = await response.json()
        throw new Error(errorData.error || errorData.errors || 'Failed to update alias')
      }

      const updatedAlias = await response.json()

      // Update in local state
      const { entityFqcn } = updatedAlias
      if (aliases.value[entityFqcn]) {
        const index = aliases.value[entityFqcn].findIndex(a => a.id === id)
        if (index !== -1) {
          aliases.value[entityFqcn][index] = updatedAlias
        }
      }

      return updatedAlias
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete an alias
   */
  async function deleteAlias(id, entityFqcn) {
    loading.value = true
    error.value = null

    try {
      const response = await fetch(`/schema-doc/api/aliases/${id}`, {
        method: 'DELETE'
      })

      if (!response.ok) {
        throw new Error('Failed to delete alias')
      }

      // Remove from local state
      if (aliases.value[entityFqcn]) {
        aliases.value[entityFqcn] = aliases.value[entityFqcn].filter(a => a.id !== id)
      }
    } catch (err) {
      error.value = err.message
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Clear all aliases (useful when switching projects)
   */
  function clearAliases() {
    aliases.value = {}
    error.value = null
  }

  return {
    // State
    aliases,
    loading,
    error,

    // Getters
    getAliasesForEntity,
    getAliasCountForEntity,

    // Actions
    fetchAllAliases,
    fetchAliasesForEntity,
    createAlias,
    updateAlias,
    deleteAlias,
    clearAliases
  }
})
