<script setup>
import { ref, computed, watch } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import Icon from './Icon.vue'

const schemaStore = useSchemaStore()

const searchQuery = ref('')
const collapsedNamespaces = ref(new Set())
const showOnlyWithRelations = ref(false)

// Computed: filtered namespaces based on search
const filteredNamespaces = computed(() => {
  const query = searchQuery.value.toLowerCase().trim()

  if (!query && !showOnlyWithRelations.value) {
    return schemaStore.namespaces
  }

  return schemaStore.namespaces
    .map(ns => {
      let filteredEntities = ns.entities

      // Filter by search query
      if (query) {
        filteredEntities = filteredEntities.filter(entity =>
          entity.name.toLowerCase().includes(query) ||
          entity.table?.toLowerCase().includes(query) ||
          (entity.fqcn || entity.name).toLowerCase().includes(query)
        )
      }

      // Filter by relations
      if (showOnlyWithRelations.value) {
        filteredEntities = filteredEntities.filter(entity =>
          entity.relations && entity.relations.length > 0
        )
      }

      return {
        ...ns,
        entities: filteredEntities,
        matchCount: filteredEntities.length
      }
    })
    .filter(ns => ns.entities.length > 0)
})

// Computed: total visible entities
const totalVisibleEntities = computed(() => {
  return filteredNamespaces.value.reduce((acc, ns) => acc + ns.entities.length, 0)
})

// Toggle namespace collapse
function toggleNamespace(namespaceName) {
  const newSet = new Set(collapsedNamespaces.value)
  if (newSet.has(namespaceName)) {
    newSet.delete(namespaceName)
  } else {
    newSet.add(namespaceName)
  }
  collapsedNamespaces.value = newSet
}

// Check if namespace is collapsed
function isCollapsed(namespaceName) {
  return collapsedNamespaces.value.has(namespaceName)
}

// Handle entity click
function handleEntityClick(entity, event) {
  const fqcn = entity.fqcn || entity.name

  if (event.metaKey || event.ctrlKey) {
    // Multi-selection with Cmd/Ctrl
    schemaStore.toggleEntitySelection(fqcn)
  } else {
    // Single selection (replace)
    schemaStore.setSelectedEntities([fqcn])
  }
}

// Check if entity is selected
function isSelected(entity) {
  const fqcn = entity.fqcn || entity.name
  return schemaStore.selectedEntities.has(fqcn)
}

// Clear selection
function clearSelection() {
  schemaStore.clearSelectedEntities()
}

// Select all visible entities
function selectAllVisible() {
  const allFqcns = filteredNamespaces.value
    .flatMap(ns => ns.entities)
    .map(e => e.fqcn || e.name)

  schemaStore.setSelectedEntities(allFqcns)
}

// Expand all namespaces
function expandAll() {
  collapsedNamespaces.value = new Set()
}

// Collapse all namespaces
function collapseAll() {
  const allNs = filteredNamespaces.value.map(ns => ns.name)
  collapsedNamespaces.value = new Set(allNs)
}

// Auto-expand namespaces when searching
watch(searchQuery, (newQuery) => {
  if (newQuery.trim()) {
    // Expand all when searching
    collapsedNamespaces.value = new Set()
  }
})
</script>

<template>
  <div class="entity-sidebar">
    <!-- Header -->
    <div class="sidebar-header">
      <div class="header-title">
        <Icon name="table-cells" :size="20" />
        <h2>Entities</h2>
      </div>

      <div class="header-actions">
        <button
          v-if="schemaStore.selectedEntities.size > 0"
          @click="clearSelection"
          class="action-btn clear-btn"
          title="Clear selection"
        >
          <Icon name="x-mark" :size="16" />
        </button>
      </div>
    </div>

    <!-- Search & Filters -->
    <div class="sidebar-search">
      <div class="search-input-wrapper">
        <Icon name="magnifying-glass" :size="16" class="search-icon" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search entities..."
          class="search-input"
        />
        <button
          v-if="searchQuery"
          @click="searchQuery = ''"
          class="clear-search-btn"
        >
          <Icon name="x-mark" :size="14" />
        </button>
      </div>

      <!-- Filters Row -->
      <div class="filters-row">
        <label class="filter-checkbox">
          <input type="checkbox" v-model="showOnlyWithRelations" />
          <span>With relations</span>
        </label>

        <div class="expand-collapse-btns">
          <button @click="expandAll" class="tiny-btn" title="Expand all">
            <Icon name="chevron-down" :size="12" />
          </button>
          <button @click="collapseAll" class="tiny-btn" title="Collapse all">
            <Icon name="chevron-up" :size="12" />
          </button>
        </div>
      </div>
    </div>

    <!-- Tree View -->
    <div class="sidebar-content">
      <div v-if="filteredNamespaces.length === 0" class="empty-state">
        <Icon name="inbox" :size="48" class="empty-icon" />
        <p>No entities found</p>
        <button @click="searchQuery = ''; showOnlyWithRelations = false" class="reset-btn">
          Reset filters
        </button>
      </div>

      <div v-else class="namespaces-list">
        <div
          v-for="namespace in filteredNamespaces"
          :key="namespace.name"
          class="namespace-group"
        >
          <!-- Namespace Header -->
          <div
            @click="toggleNamespace(namespace.name)"
            class="namespace-header"
            :class="{ collapsed: isCollapsed(namespace.name) }"
          >
            <Icon
              :name="isCollapsed(namespace.name) ? 'chevron-right' : 'chevron-down'"
              :size="16"
              class="collapse-icon"
            />
            <Icon name="folder" :size="16" class="namespace-icon" />
            <span class="namespace-name">{{ namespace.name }}</span>
            <span class="entity-count">{{ namespace.entities.length }}</span>
          </div>

          <!-- Entities List -->
          <div v-if="!isCollapsed(namespace.name)" class="entities-list">
            <div
              v-for="entity in namespace.entities"
              :key="entity.fqcn || entity.name"
              @click="handleEntityClick(entity, $event)"
              class="entity-item"
              :class="{ selected: isSelected(entity) }"
              :title="`${entity.name} (${entity.table})\nCmd/Ctrl+Click for multi-select`"
            >
              <Icon name="table-cells" :size="14" class="entity-icon" />
              <div class="entity-info">
                <span class="entity-name">{{ entity.name }}</span>
                <div class="entity-badges">
                  <span class="badge badge-fields">
                    {{ entity.fields?.length || 0 }} fields
                  </span>
                  <span
                    v-if="entity.relations && entity.relations.length > 0"
                    class="badge badge-relations"
                  >
                    {{ entity.relations.length }} rel
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer Stats -->
    <div class="sidebar-footer">
      <div class="footer-stats">
        <div class="stat-item">
          <Icon name="table-cells" :size="14" />
          <span>{{ totalVisibleEntities }} / {{ schemaStore.totalEntities }}</span>
        </div>
        <div v-if="schemaStore.selectedEntities.size > 0" class="stat-item selected-count">
          <Icon name="check-circle" :size="14" />
          <span>{{ schemaStore.selectedEntities.size }} selected</span>
        </div>
      </div>

      <button
        v-if="totalVisibleEntities > 0 && schemaStore.selectedEntities.size === 0"
        @click="selectAllVisible"
        class="select-all-btn"
        title="Select all visible entities"
      >
        Select all
      </button>
    </div>
  </div>
</template>

<style scoped>
.entity-sidebar {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--color-gray-50);
  border-right: 1px solid var(--color-gray-200);
}

/* Header */
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-4);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
}

.header-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.header-title h2 {
  margin: 0;
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-900);
}

.header-actions {
  display: flex;
  gap: var(--spacing-2);
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: var(--color-gray-100);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
}

.action-btn:hover {
  background: var(--color-gray-200);
  border-color: var(--color-gray-400);
}

.query-builder-btn {
  background: var(--color-primary-50);
  border-color: var(--color-primary-200);
  color: var(--color-primary-600);
  text-decoration: none;
}

.query-builder-btn:hover {
  background: var(--color-primary-100);
  border-color: var(--color-primary-400);
  color: var(--color-primary-700);
}

.clear-btn {
  background: var(--color-red-50);
  border-color: var(--color-red-200);
  color: var(--color-red-600);
}

.clear-btn:hover {
  background: var(--color-red-100);
  border-color: var(--color-red-300);
}

/* Search */
.sidebar-search {
  padding: var(--spacing-3);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
}

.search-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  padding: var(--spacing-2);
  margin-bottom: var(--spacing-2);
}

.search-input-wrapper:focus-within {
  border-color: var(--color-primary-500);
  background: white;
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

.clear-search-btn {
  background: none;
  border: none;
  padding: var(--spacing-1);
  cursor: pointer;
  color: var(--color-gray-400);
  display: flex;
  align-items: center;
  border-radius: var(--radius-sm);
  transition: all var(--transition-base);
}

.clear-search-btn:hover {
  background: var(--color-gray-200);
  color: var(--color-gray-600);
}

/* Filters Row */
.filters-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-2);
}

.filter-checkbox {
  display: flex;
  align-items: center;
  gap: var(--spacing-1-5);
  font-size: var(--text-xs);
  color: var(--color-gray-600);
  cursor: pointer;
  user-select: none;
}

.filter-checkbox input[type="checkbox"] {
  cursor: pointer;
  width: 14px;
  height: 14px;
  accent-color: var(--color-primary-500);
}

.expand-collapse-btns {
  display: flex;
  gap: var(--spacing-1);
}

.tiny-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  background: var(--color-gray-100);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-sm);
  cursor: pointer;
  transition: all var(--transition-base);
  color: var(--color-gray-600);
}

.tiny-btn:hover {
  background: var(--color-gray-200);
  color: var(--color-gray-800);
}

/* Content */
.sidebar-content {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--spacing-8);
  text-align: center;
  color: var(--color-gray-400);
}

.empty-icon {
  margin-bottom: var(--spacing-3);
  opacity: 0.5;
}

.empty-state p {
  margin: 0 0 var(--spacing-3) 0;
  font-size: var(--text-sm);
  color: var(--color-gray-500);
}

.reset-btn {
  padding: var(--spacing-2) var(--spacing-3);
  background: var(--color-primary-500);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
}

.reset-btn:hover {
  background: var(--color-primary-600);
}

/* Namespaces */
.namespaces-list {
  padding: var(--spacing-2);
}

.namespace-group {
  margin-bottom: var(--spacing-2);
}

.namespace-header {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2);
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
  user-select: none;
}

.namespace-header:hover {
  background: var(--color-gray-50);
  border-color: var(--color-primary-300);
}

.collapse-icon {
  color: var(--color-gray-400);
  transition: transform var(--transition-base);
}

.namespace-icon {
  color: var(--color-primary-500);
}

.namespace-name {
  flex: 1;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-800);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.entity-count {
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 24px;
  height: 20px;
  padding: 0 var(--spacing-1-5);
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-full);
}

/* Entities List */
.entities-list {
  margin-top: var(--spacing-1);
  padding-left: var(--spacing-6);
}

.entity-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2);
  margin-bottom: var(--spacing-1);
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
  user-select: none;
}

.entity-item:hover {
  background: var(--color-primary-50);
  border-color: var(--color-primary-300);
  transform: translateX(2px);
}

.entity-item.selected {
  background: var(--color-primary-100);
  border-color: var(--color-primary-500);
  border-width: 2px;
  padding: calc(var(--spacing-2) - 1px);
}

.entity-icon {
  color: var(--color-gray-500);
  flex-shrink: 0;
}

.entity-item.selected .entity-icon {
  color: var(--color-primary-600);
}

.entity-info {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.entity-name {
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-900);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.entity-badges {
  display: flex;
  gap: var(--spacing-1);
  flex-wrap: wrap;
}

.badge {
  display: inline-flex;
  align-items: center;
  padding: 1px var(--spacing-1-5);
  font-size: 10px;
  font-weight: 600;
  border-radius: var(--radius-sm);
  white-space: nowrap;
}

.badge-fields {
  background: var(--color-blue-100);
  color: var(--color-blue-700);
}

.badge-relations {
  background: var(--color-purple-100);
  color: var(--color-purple-700);
}

/* Footer */
.sidebar-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-3);
  padding: var(--spacing-3);
  background: white;
  border-top: 1px solid var(--color-gray-200);
}

.footer-stats {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.stat-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-1-5);
  font-size: var(--text-xs);
  color: var(--color-gray-600);
}

.stat-item.selected-count {
  color: var(--color-primary-600);
  font-weight: 600;
}

.select-all-btn {
  padding: var(--spacing-1-5) var(--spacing-3);
  background: var(--color-gray-100);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  font-size: var(--text-xs);
  font-weight: 600;
  color: var(--color-gray-700);
  cursor: pointer;
  transition: all var(--transition-base);
  white-space: nowrap;
}

.select-all-btn:hover {
  background: var(--color-primary-500);
  border-color: var(--color-primary-500);
  color: white;
}

/* Scrollbar styling */
.sidebar-content::-webkit-scrollbar {
  width: 8px;
}

.sidebar-content::-webkit-scrollbar-track {
  background: var(--color-gray-100);
}

.sidebar-content::-webkit-scrollbar-thumb {
  background: var(--color-gray-300);
  border-radius: var(--radius-full);
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
  background: var(--color-gray-400);
}
</style>
