import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

/**
 * Pinia store for managing migration history data.
 *
 * Handles fetching, caching, and providing migration timeline data
 * for entity evolution visualization.
 */
export const useMigrationsStore = defineStore('migrations', () => {
  // State
  const entityHistories = ref(new Map()) // Map<entityFqcn, history>
  const allHistory = ref([])
  const stats = ref(null)
  const migrations = ref([])
  const loading = ref(false)
  const error = ref(null)

  // Computed
  const hasHistory = computed(() => entityHistories.value.size > 0)
  const totalMigrations = computed(() => stats.value?.totalMigrations ?? 0)
  const totalEntities = computed(() => stats.value?.totalEntities ?? 0)

  /**
   * Fetch migration history for a specific entity.
   *
   * @param {string} entityFqcn - Fully qualified class name
   * @returns {Promise<Array>} Migration timeline
   */
  async function fetchEntityHistory(entityFqcn) {
    // Check cache first
    if (entityHistories.value.has(entityFqcn)) {
      return entityHistories.value.get(entityFqcn)
    }

    loading.value = true
    error.value = null

    try {
      // Encode FQCN for URL safety
      const encodedFqcn = btoa(entityFqcn)
      const response = await fetch(`/schema-doc/api/migrations/history/${encodedFqcn}`)

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const data = await response.json()

      if (!data.ok) {
        throw new Error(data.error || 'Failed to fetch migration history')
      }

      const history = data.history || []

      // Cache the result
      entityHistories.value.set(entityFqcn, history)

      return history
    } catch (err) {
      error.value = err.message
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch migration history for all entities.
   *
   * @returns {Promise<Array>}
   */
  async function fetchAllHistory() {
    loading.value = true
    error.value = null

    try {
      const response = await fetch('/schema-doc/api/migrations/history')

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const data = await response.json()

      if (!data.ok) {
        throw new Error(data.error || 'Failed to fetch all history')
      }

      allHistory.value = data.entities || []

      // Cache individual entity histories
      for (const entity of allHistory.value) {
        if (entity.entityFqcn) {
          entityHistories.value.set(entity.entityFqcn, entity.timeline)
        }
      }

      return allHistory.value
    } catch (err) {
      error.value = err.message
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch migration statistics.
   *
   * @returns {Promise<Object>}
   */
  async function fetchStats() {
    loading.value = true
    error.value = null

    try {
      const response = await fetch('/schema-doc/api/migrations/stats')

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const data = await response.json()

      if (!data.ok) {
        throw new Error(data.error || 'Failed to fetch stats')
      }

      stats.value = data.stats

      return stats.value
    } catch (err) {
      error.value = err.message
      return null
    } finally {
      loading.value = false
    }
  }

  /**
   * Fetch list of all migrations.
   *
   * @returns {Promise<Array>}
   */
  async function fetchMigrations() {
    loading.value = true
    error.value = null

    try {
      const response = await fetch('/schema-doc/api/migrations/list')

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const data = await response.json()

      if (!data.ok) {
        throw new Error(data.error || 'Failed to fetch migrations')
      }

      migrations.value = data.migrations || []

      return migrations.value
    } catch (err) {
      error.value = err.message
      return []
    } finally {
      loading.value = false
    }
  }

  /**
   * Get cached history for an entity (without fetching).
   *
   * @param {string} entityFqcn
   * @returns {Array|null}
   */
  function getCachedHistory(entityFqcn) {
    return entityHistories.value.get(entityFqcn) || null
  }

  /**
   * Clear all cached data.
   */
  function clearCache() {
    entityHistories.value.clear()
    allHistory.value = []
    stats.value = null
    migrations.value = []
    error.value = null
  }

  /**
   * Get human-readable label for change type.
   *
   * @param {string} type
   * @returns {string}
   */
  function getChangeTypeLabel(type) {
    const labels = {
      create_table: 'Created',
      drop_table: 'Dropped',
      add_column: 'Added Column',
      drop_column: 'Removed Column',
      rename_column: 'Renamed Column',
      modify_column: 'Modified Column',
      add_index: 'Added Index',
      drop_index: 'Dropped Index',
      add_constraint: 'Added Constraint',
      drop_constraint: 'Dropped Constraint',
      alter_table: 'Modified',
    }

    return labels[type] || type
  }

  /**
   * Get icon class for change type (Heroicons or custom).
   *
   * @param {string} type
   * @returns {string}
   */
  function getChangeTypeIcon(type) {
    const icons = {
      create_table: 'plus-circle',
      drop_table: 'trash',
      add_column: 'plus',
      drop_column: 'minus',
      rename_column: 'pencil',
      modify_column: 'pencil-square',
      add_index: 'key',
      drop_index: 'key',
      add_constraint: 'link',
      drop_constraint: 'link-slash',
      alter_table: 'wrench',
    }

    return icons[type] || 'document'
  }

  /**
   * Get color class for change type.
   *
   * @param {string} type
   * @returns {string}
   */
  function getChangeTypeColor(type) {
    const colors = {
      create_table: 'text-green-600',
      drop_table: 'text-red-600',
      add_column: 'text-blue-600',
      drop_column: 'text-orange-600',
      rename_column: 'text-purple-600',
      modify_column: 'text-yellow-600',
      add_index: 'text-indigo-600',
      drop_index: 'text-gray-600',
      add_constraint: 'text-teal-600',
      drop_constraint: 'text-pink-600',
      alter_table: 'text-gray-500',
    }

    return colors[type] || 'text-gray-600'
  }

  /**
   * Format timestamp to human-readable date.
   *
   * @param {number} timestamp - Unix timestamp
   * @returns {string}
   */
  function formatDate(timestamp) {
    const date = new Date(timestamp * 1000)
    return date.toLocaleString('fr-FR', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    })
  }

  /**
   * Get relative time string (e.g., "2 days ago").
   *
   * @param {number} timestamp - Unix timestamp
   * @returns {string}
   */
  function getRelativeTime(timestamp) {
    const now = Date.now() / 1000
    const diff = now - timestamp

    const minute = 60
    const hour = minute * 60
    const day = hour * 24
    const week = day * 7
    const month = day * 30
    const year = day * 365

    if (diff < minute) return 'Just now'
    if (diff < hour) return `${Math.floor(diff / minute)}m ago`
    if (diff < day) return `${Math.floor(diff / hour)}h ago`
    if (diff < week) return `${Math.floor(diff / day)}d ago`
    if (diff < month) return `${Math.floor(diff / week)}w ago`
    if (diff < year) return `${Math.floor(diff / month)}mo ago`
    return `${Math.floor(diff / year)}y ago`
  }

  return {
    // State
    entityHistories,
    allHistory,
    stats,
    migrations,
    loading,
    error,

    // Computed
    hasHistory,
    totalMigrations,
    totalEntities,

    // Actions
    fetchEntityHistory,
    fetchAllHistory,
    fetchStats,
    fetchMigrations,
    getCachedHistory,
    clearCache,

    // Helpers
    getChangeTypeLabel,
    getChangeTypeIcon,
    getChangeTypeColor,
    formatDate,
    getRelativeTime,
  }
})
