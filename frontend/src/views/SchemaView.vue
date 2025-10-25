<script setup>
import { computed, ref, watch, onMounted } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import SchemaGraph from '@/components/SchemaGraph.vue'
import Icon from '@/components/Icon.vue'

const schemaStore = useSchemaStore()

const viewMode = ref('force')
const searchQuery = ref('')
const showSearchDropdown = ref(false)
const showRelationsOnly = ref(false)
const selectedNamespace = ref('')

// Compute available namespaces
const namespaces = computed(() => {
  const ns = new Set()
  schemaStore.schemaEntities.forEach(entity => {
    const namespace = extractNamespace(entity.fqcn || entity.name)
    ns.add(namespace)
  })
  return Array.from(ns).sort()
})

// Auto-select first namespace on mount
onMounted(() => {
  if (namespaces.value.length > 0) {
    selectedNamespace.value = namespaces.value[0]
  }
  document.addEventListener('click', handleClickOutside)
  document.addEventListener('keydown', handleKeyboardShortcut)
})

// Keyboard shortcuts
function handleKeyboardShortcut(event) {
  // Cmd+K or Ctrl+K to focus search
  if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
    event.preventDefault()
    const searchInput = document.querySelector('.search-input')
    if (searchInput) {
      searchInput.focus()
    }
  }
}

// Get selected entity (from search or focus)
const selectedEntity = computed(() => {
  if (!searchQuery.value) return null

  const query = searchQuery.value.toLowerCase()
  return schemaStore.schemaEntities.find(entity =>
    entity.name.toLowerCase() === query ||
    entity.table?.toLowerCase() === query
  )
})

// Filtered entities based on search and namespace
const filteredEntities = computed(() => {
  let entities = schemaStore.schemaEntities

  // If there's a selected entity (from search)
  if (selectedEntity.value) {
    if (showRelationsOnly.value) {
      // Show selected entity + all related entities
      entities = getRelatedEntities(selectedEntity.value)
    } else {
      // Show ONLY the selected entity
      entities = [selectedEntity.value]
    }
  }

  // Filter by namespace
  if (selectedNamespace.value) {
    entities = entities.filter(entity => {
      const ns = extractNamespace(entity.fqcn || entity.name)
      return ns === selectedNamespace.value
    })
  }

  return entities
})

// Note: Auto-fit functionality is handled by SchemaGraph component



// Get all entities related to selected entity
const getRelatedEntities = (entity) => {
  const relatedIds = new Set()
  relatedIds.add(entity.fqcn || entity.name)

  // Find all entities that this entity has relations to
  if (entity.relations) {
    entity.relations.forEach(relation => {
      relatedIds.add(relation.target)
    })
  }

  // Find all entities that have relations to this entity
  schemaStore.schemaEntities.forEach(e => {
    if (e.relations) {
      e.relations.forEach(rel => {
        if (rel.target === entity.fqcn || rel.target === entity.name) {
          relatedIds.add(e.fqcn || e.name)
        }
      })
    }
  })

  return schemaStore.schemaEntities.filter(e => relatedIds.has(e.fqcn || e.name))
}

// Search results for dropdown
const searchResults = computed(() => {
  if (!searchQuery.value) return []

  const query = searchQuery.value.toLowerCase()
  return schemaStore.schemaEntities
    .filter(entity =>
      entity.name.toLowerCase().includes(query) ||
      entity.table?.toLowerCase().includes(query)
    )
    .slice(0, 5) // Limit to 5 results
})

// Compute relations between filtered entities
const relations = computed(() => {
  const rels = []

  // If there's a selected entity and "Related tables only" is NOT checked
  // Don't show any relations (only the table itself)
  if (selectedEntity.value && !showRelationsOnly.value) {
    return []
  }

  // Otherwise, show relations between filtered entities
  filteredEntities.value.forEach((entity) => {
    if (entity.relations) {
      entity.relations.forEach((relation) => {
        const target = filteredEntities.value.find(
          (e) => e.name === relation.target || e.fqcn === relation.target,
        )

        if (target) {
          rels.push({
            from: entity,
            to: target,
            field: relation.field,
            type: relation.type,
            isOwning: relation.isOwning,
          })
        }
      })
    }
  })

  return rels
})

function extractNamespace(fqcn) {
  const parts = fqcn.split('\\')
  return parts.slice(0, -1).join('\\') || 'Default'
}

function handleEntityClick(entity) {
  schemaStore.selectEntity(entity.fqcn || entity.id)
}

function selectEntityFromSearch(entity) {
  searchQuery.value = entity.name
  showSearchDropdown.value = false
}

function clearSearch() {
  searchQuery.value = ''
  selectedNamespace.value = namespaces.value.length > 0 ? namespaces.value[0] : null
  showRelationsOnly.value = false
}

// Close dropdown when clicking outside
function handleClickOutside(event) {
  if (!event.target.closest('.search-container')) {
    showSearchDropdown.value = false
  }
}
</script>

<template>
  <div class="schema-view">
    <!-- Enhanced Top Controls Bar -->
    <div class="controls-bar">
      <!-- Left Section: Search & Filters -->
      <div class="controls-left">
        <!-- Stats Badge -->
        <div class="stats-badge">
          <span class="stat-number">{{ filteredEntities.length }}</span>
          <span class="stat-label">/ {{ schemaStore.schemaEntities.length }} tables</span>
        </div>
        <!-- Advanced Search -->
        <div class="search-container">
          <div class="search-input-wrapper">
            <Icon name="magnifying-glass" :size="18" class="search-icon" />
            <input
              v-model="searchQuery"
              @focus="showSearchDropdown = true"
              type="text"
              placeholder="Search tables (Cmd+K)"
              class="search-input"
            />
            <button v-if="searchQuery" @click="clearSearch" class="clear-btn">
              <Icon name="x-mark" :size="16" />
            </button>
          </div>

          <!-- Search Dropdown -->
          <div v-if="showSearchDropdown && searchResults.length > 0" class="search-dropdown">
            <div
              v-for="entity in searchResults"
              :key="entity.fqcn"
              @click="selectEntityFromSearch(entity)"
              class="search-result-item hover-lift"
            >
              <Icon name="table-cells" :size="16" />
              <div class="result-content">
                <span class="result-name">{{ entity.name }}</span>
                <span class="result-meta">{{ entity.fields?.length || 0 }} fields</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Namespace Filter -->
        <div v-if="namespaces.length > 1" class="filter-group">
          <Icon name="funnel" :size="16" />
          <select v-model="selectedNamespace" class="filter-select">
            <option v-for="ns in namespaces" :key="ns" :value="ns">
              {{ ns }}
            </option>
          </select>
        </div>

        <!-- Show Relations Only Toggle -->
        <label class="checkbox-group">
          <input type="checkbox" v-model="showRelationsOnly" />
          <span>Related tables only</span>
        </label>
      </div>
    </div>

    <!-- Graph Canvas -->
    <div class="schema-canvas">
      <SchemaGraph
        v-if="viewMode === 'force'"
        :entities="filteredEntities"
        :relations="relations"
        :focused-entity="null"
        @entity-click="handleEntityClick"
      />

      <div v-if="filteredEntities.length === 0" class="empty-state">
        <Icon name="database" :size="64" class="empty-icon" />
        <h3>No tables found</h3>
        <p>Try adjusting your filters or search query</p>
        <button @click="clearSearch" class="btn-clear-filters">
          Clear filters
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.schema-view {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--color-gray-50);
}

/* Enhanced Controls Bar */
.controls-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-4);
  padding: var(--spacing-4) var(--spacing-6);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
  box-shadow: var(--shadow-sm);
  z-index: 10;
}

.controls-left,
.controls-right {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
}

.controls-left {
  flex: 1;
}

/* Advanced Search */
.search-container {
  position: relative;
  min-width: 320px;
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  padding: var(--spacing-2) var(--spacing-3);
  transition: all var(--transition-base);
}

.search-input-wrapper:focus-within {
  background: white;
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 3px var(--color-primary-100);
}

.search-icon {
  color: var(--color-gray-400);
  margin-right: var(--spacing-2);
}

.search-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: var(--text-sm);
  outline: none;
  color: var(--color-gray-900);
}

.search-input::placeholder {
  color: var(--color-gray-400);
}

.clear-btn {
  background: none;
  border: none;
  padding: var(--spacing-1);
  cursor: pointer;
  color: var(--color-gray-400);
  display: flex;
  align-items: center;
  border-radius: var(--radius-md);
  transition: all var(--transition-base);
}

.clear-btn:hover {
  background: var(--color-gray-200);
  color: var(--color-gray-600);
}

/* Search Dropdown */
.search-dropdown {
  position: absolute;
  top: calc(100% + var(--spacing-2));
  left: 0;
  right: 0;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  z-index: 100;
  animation: slide-down 0.2s var(--ease-out);
}

@keyframes slide-down {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.search-result-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-3);
  cursor: pointer;
  transition: all var(--transition-base);
  border-bottom: 1px solid var(--color-gray-100);
}

.search-result-item:last-child {
  border-bottom: none;
}

.search-result-item:hover {
  background: var(--color-primary-50);
}

.result-content {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-0-5);
}

.result-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-900);
}

.result-meta {
  font-size: var(--text-xs);
  color: var(--color-gray-500);
}

/* Filters */
.filter-group {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  transition: all var(--transition-base);
}

.filter-group:focus-within {
  border-color: var(--color-primary-500);
  box-shadow: 0 0 0 3px var(--color-primary-100);
}

.filter-select {
  border: none;
  background: transparent;
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-gray-700);
  outline: none;
  cursor: pointer;
  padding-right: var(--spacing-2);
}

/* Checkbox */
.checkbox-group {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all var(--transition-base);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-gray-700);
  user-select: none;
}

.checkbox-group:hover {
  border-color: var(--color-primary-500);
  background: white;
}

.checkbox-group input[type="checkbox"] {
  cursor: pointer;
  width: 16px;
  height: 16px;
  accent-color: var(--color-primary-500);
}

/* Stats Badge */
.stats-badge {
  display: flex;
  align-items: baseline;
  gap: var(--spacing-1);
  padding: var(--spacing-2) var(--spacing-3);
  background: linear-gradient(135deg, var(--color-primary-500) 0%, var(--color-primary-600) 100%);
  border-radius: var(--radius-lg);
  color: white;
  font-weight: 600;
}

.stat-number {
  font-size: var(--text-lg);
}

.stat-label {
  font-size: var(--text-xs);
  opacity: 0.9;
}

/* Control Buttons */
.control-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-gray-700);
  cursor: pointer;
  transition: all var(--transition-base);
}

.control-btn:hover {
  background: white;
  border-color: var(--color-primary-500);
  color: var(--color-primary-500);
  box-shadow: var(--shadow-sm);
}

.btn-label {
  font-size: var(--text-sm);
}

.divider {
  width: 1px;
  height: 24px;
  background: var(--color-gray-300);
}

/* Canvas */
.schema-canvas {
  flex: 1;
  overflow: hidden;
  position: relative;
}

/* Empty State */
.empty-state {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  color: var(--color-gray-400);
}

.empty-icon {
  margin: 0 auto var(--spacing-4);
  opacity: 0.5;
}

.empty-state h3 {
  font-size: var(--text-xl);
  font-weight: 600;
  color: var(--color-gray-600);
  margin: 0 0 var(--spacing-2) 0;
}

.empty-state p {
  font-size: var(--text-sm);
  margin: 0 0 var(--spacing-4) 0;
}

.btn-clear-filters {
  padding: var(--spacing-2) var(--spacing-4);
  background: var(--color-primary-500);
  color: white;
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
}

.btn-clear-filters:hover {
  background: var(--color-primary-600);
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

@media (max-width: 1280px) {
  .controls-bar {
    flex-wrap: wrap;
  }

  .controls-left {
    flex: 1 1 100%;
    order: 2;
  }

  .controls-right {
    order: 1;
  }

  .search-container {
    min-width: 100%;
  }
}
</style>
