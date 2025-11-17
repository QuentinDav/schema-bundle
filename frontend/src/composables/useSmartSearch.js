import { ref, computed, watch } from 'vue'
import Fuse from 'fuse.js'

/**
 * Smart search composable with fuzzy matching and advanced filtering.
 *
 * Provides fuzzy search capabilities for entities, fields, and relations
 * with configurable thresholds and search keys.
 */
export function useSmartSearch(items, options = {}) {
  const searchQuery = ref('')
  const searchResults = ref([])
  const isSearching = ref(false)

  const defaultOptions = {
    keys: ['name', 'table', 'fqcn'],
    threshold: 0.3, // 0 = perfect match, 1 = match anything
    includeScore: true,
    includeMatches: true,
    minMatchCharLength: 2,
    ignoreLocation: true,
    ...options,
  }

  const fuse = computed(() => {
    return new Fuse(items.value || [], defaultOptions)
  })

  const search = (query) => {
    searchQuery.value = query

    if (!query || query.trim().length < 1) {
      searchResults.value = items.value || []
      return
    }

    isSearching.value = true

    try {
      const results = fuse.value.search(query)
      searchResults.value = results.map((result) => ({
        ...result.item,
        _searchScore: result.score,
        _matches: result.matches,
      }))
    } catch (error) {
      searchResults.value = []
    } finally {
      isSearching.value = false
    }
  }

  const clear = () => {
    searchQuery.value = ''
    searchResults.value = items.value || []
  }

  // Auto-search when query changes
  watch(searchQuery, (newQuery) => {
    search(newQuery)
  })

  // Update results when items change
  watch(
    () => items.value,
    (newItems) => {
      if (!searchQuery.value) {
        searchResults.value = newItems || []
      } else {
        search(searchQuery.value)
      }
    },
    { deep: true }
  )

  return {
    searchQuery,
    searchResults,
    isSearching,
    search,
    clear,
  }
}

/**
 * Advanced entity filtering composable.
 *
 * Provides complex filtering capabilities for entities based on
 * various criteria like relations, field types, etc.
 */
export function useEntityFilters(entities) {
  const filters = ref({
    withRelations: false,
    minRelations: 0,
    maxRelations: Infinity,
    fieldTypes: [],
    hasNullableFields: null,
    hasUniqueFields: null,
    namespaces: [],
  })

  const filteredEntities = computed(() => {
    let result = entities.value || []

    // Filter by relations
    if (filters.value.withRelations) {
      result = result.filter((e) => e.relations && e.relations.length > 0)
    }

    if (filters.value.minRelations > 0) {
      result = result.filter(
        (e) => (e.relations?.length || 0) >= filters.value.minRelations
      )
    }

    if (filters.value.maxRelations < Infinity) {
      result = result.filter(
        (e) => (e.relations?.length || 0) <= filters.value.maxRelations
      )
    }

    // Filter by field types
    if (filters.value.fieldTypes.length > 0) {
      result = result.filter((e) =>
        e.fields?.some((f) => filters.value.fieldTypes.includes(f.type))
      )
    }

    // Filter by nullable fields
    if (filters.value.hasNullableFields !== null) {
      result = result.filter((e) =>
        filters.value.hasNullableFields
          ? e.fields?.some((f) => f.nullable)
          : e.fields?.every((f) => !f.nullable)
      )
    }

    // Filter by unique fields
    if (filters.value.hasUniqueFields !== null) {
      result = result.filter((e) =>
        filters.value.hasUniqueFields
          ? e.fields?.some((f) => f.unique)
          : e.fields?.every((f) => !f.unique)
      )
    }

    // Filter by namespaces
    if (filters.value.namespaces.length > 0) {
      result = result.filter((e) => {
        const namespace = e.fqcn?.split('\\').slice(0, -1).join('\\') || ''
        return filters.value.namespaces.some((ns) => namespace.includes(ns))
      })
    }

    return result
  })

  const applyFilter = (filterName, value) => {
    filters.value[filterName] = value
  }

  const clearFilters = () => {
    filters.value = {
      withRelations: false,
      minRelations: 0,
      maxRelations: Infinity,
      fieldTypes: [],
      hasNullableFields: null,
      hasUniqueFields: null,
      namespaces: [],
    }
  }

  const hasActiveFilters = computed(() => {
    return (
      filters.value.withRelations ||
      filters.value.minRelations > 0 ||
      filters.value.maxRelations < Infinity ||
      filters.value.fieldTypes.length > 0 ||
      filters.value.hasNullableFields !== null ||
      filters.value.hasUniqueFields !== null ||
      filters.value.namespaces.length > 0
    )
  })

  return {
    filters,
    filteredEntities,
    applyFilter,
    clearFilters,
    hasActiveFilters,
  }
}

/**
 * Highlight matches in search results.
 *
 * @param {string} text - Original text
 * @param {Array} matches - Fuse.js matches array
 * @returns {string} HTML string with highlighted matches
 */
export function highlightMatches(text, matches = []) {
  if (!matches || matches.length === 0) {
    return text
  }

  let result = ''
  let lastIndex = 0

  // Flatten and sort all match indices
  const indices = matches
    .flatMap((match) => match.indices)
    .sort((a, b) => a[0] - b[0])

  // Merge overlapping ranges
  const merged = []
  for (const range of indices) {
    if (merged.length === 0 || merged[merged.length - 1][1] < range[0]) {
      merged.push(range)
    } else {
      merged[merged.length - 1][1] = Math.max(merged[merged.length - 1][1], range[1])
    }
  }

  // Build highlighted string
  for (const [start, end] of merged) {
    result += text.slice(lastIndex, start)
    result += `<mark class="bg-yellow-200 text-gray-900 font-semibold">${text.slice(
      start,
      end + 1
    )}</mark>`
    lastIndex = end + 1
  }

  result += text.slice(lastIndex)

  return result
}

/**
 * Search presets for common queries.
 */
export const searchPresets = [
  {
    label: 'Entities with many relations',
    description: 'Entities with 3+ relations',
    filter: { minRelations: 3 },
  },
  {
    label: 'Simple entities',
    description: 'Entities with no relations',
    filter: { withRelations: false, maxRelations: 0 },
  },
  {
    label: 'Core entities',
    description: 'Most connected entities',
    filter: { minRelations: 5 },
  },
  {
    label: 'Entities with dates',
    description: 'Entities with date/datetime fields',
    filter: { fieldTypes: ['datetime', 'date', 'datetime_immutable'] },
  },
  {
    label: 'Entities with JSON',
    description: 'Entities with JSON fields',
    filter: { fieldTypes: ['json'] },
  },
]
