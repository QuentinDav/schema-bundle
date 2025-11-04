<script setup>
import { ref, computed, watch } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import Icon from './Icon.vue'

const schemaStore = useSchemaStore()

const searchQuery = ref('')
const collapsedNamespaces = ref(new Set())
const showOnlyWithRelations = ref(false)
const showQuickActions = ref(false)

const filteredNamespaces = computed(() => {
  const query = searchQuery.value.toLowerCase().trim()

  if (!query && !showOnlyWithRelations.value) {
    return schemaStore.namespaces
  }

  return schemaStore.namespaces
    .map(ns => {
      let filteredEntities = ns.entities

      if (query) {
        filteredEntities = filteredEntities.filter(entity =>
          entity.name.toLowerCase().includes(query) ||
          entity.table?.toLowerCase().includes(query) ||
          (entity.fqcn || entity.name).toLowerCase().includes(query)
        )
      }

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

const totalVisibleEntities = computed(() => {
  return filteredNamespaces.value.reduce((acc, ns) => acc + ns.entities.length, 0)
})

function toggleNamespace(namespaceName) {
  const newSet = new Set(collapsedNamespaces.value)
  if (newSet.has(namespaceName)) {
    newSet.delete(namespaceName)
  } else {
    newSet.add(namespaceName)
  }
  collapsedNamespaces.value = newSet
}

function isCollapsed(namespaceName) {
  return collapsedNamespaces.value.has(namespaceName)
}

function handleEntityClick(entity, event) {
  const fqcn = entity.fqcn || entity.name

  if (event.metaKey || event.ctrlKey) {
    schemaStore.toggleEntitySelection(fqcn)
  } else {
    schemaStore.setSelectedEntities([fqcn])
  }
}

function isSelected(entity) {
  const fqcn = entity.fqcn || entity.name
  return schemaStore.selectedEntities.has(fqcn)
}

function clearSelection() {
  schemaStore.clearSelectedEntities()
}

function selectAllVisible() {
  const allFqcns = filteredNamespaces.value
    .flatMap(ns => ns.entities)
    .map(e => e.fqcn || e.name)

  schemaStore.setSelectedEntities(allFqcns)
}

function selectNamespace(namespace) {
  const fqcns = namespace.entities.map(e => e.fqcn || e.name)
  const currentSelection = new Set(schemaStore.selectedEntities)

  fqcns.forEach(fqcn => currentSelection.add(fqcn))

  schemaStore.setSelectedEntities(Array.from(currentSelection))
}

function selectOnlyNamespace(namespace) {
  const fqcns = namespace.entities.map(e => e.fqcn || e.name)
  schemaStore.setSelectedEntities(fqcns)
}

function selectWithRelations() {
  const entitiesWithRels = schemaStore.entities
    .filter(e => e.relations && e.relations.length > 0)
    .map(e => e.fqcn || e.name)

  schemaStore.setSelectedEntities(entitiesWithRels)
}

function selectCoreEntities() {
  const sorted = [...schemaStore.entities]
    .sort((a, b) => (b.relations?.length || 0) - (a.relations?.length || 0))
    .slice(0, Math.min(20, schemaStore.entities.length))

  const fqcns = sorted.map(e => e.fqcn || e.name)
  schemaStore.setSelectedEntities(fqcns)
}

function selectByPrefix() {
  const prefix = prompt('Enter table name prefix (e.g., "User", "Product"):')
  if (!prefix) return

  const matching = schemaStore.entities
    .filter(e => e.name.toLowerCase().startsWith(prefix.toLowerCase()))
    .map(e => e.fqcn || e.name)

  if (matching.length === 0) {
    alert(`No entities found with prefix "${prefix}"`)
    return
  }

  schemaStore.setSelectedEntities(matching)
}

function expandAll() {
  collapsedNamespaces.value = new Set()
}

function collapseAll() {
  const allNs = filteredNamespaces.value.map(ns => ns.name)
  collapsedNamespaces.value = new Set(allNs)
}

watch(searchQuery, (newQuery) => {
  if (newQuery.trim()) {
    collapsedNamespaces.value = new Set()
  }
})
</script>

<template>
  <div class="h-full flex flex-col bg-[var(--color-surface)] border-r border-[var(--color-border)]">
    <div class="flex items-center justify-between px-4 py-3 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <div class="flex items-center gap-2">
        <Icon name="table-cells" :size="20" class="text-[var(--color-primary)]" />
        <h2 class="m-0 text-base font-bold text-[var(--color-text-primary)]">Entities</h2>
        <span class="text-xs text-[var(--color-text-tertiary)] ml-1">({{ schemaStore.totalEntities }})</span>
      </div>

      <div class="flex gap-2">
        <button
          @click="showQuickActions = !showQuickActions"
          class="flex items-center justify-center w-8 h-8 bg-[var(--color-primary-light)] border border-[var(--color-primary)]/30 rounded-md cursor-pointer transition-all duration-200 hover:bg-[var(--color-primary)]/20"
          :class="{ 'bg-[var(--color-primary)] text-white': showQuickActions }"
          title="Quick selection tools"
        >
          <Icon name="bolt" :size="16" />
        </button>
        <button
          v-if="schemaStore.selectedEntities.size > 0"
          @click="clearSelection"
          class="flex items-center justify-center w-8 h-8 bg-[var(--color-danger-light)] border border-[#ef4444]/30 rounded-md cursor-pointer transition-all duration-200 hover:bg-[#ef4444]/20"
          title="Clear selection"
        >
          <Icon name="x-mark" :size="16" class="text-[#ef4444]" />
        </button>
      </div>
    </div>

    <div v-if="showQuickActions" class="px-3 py-3 bg-[var(--color-primary-light)] border-b border-[var(--color-primary)]/20">
      <div class="text-xs font-semibold text-[var(--color-text-primary)] mb-2">Quick Selection</div>
      <div class="grid grid-cols-2 gap-2">
        <button @click="selectAllVisible" class="px-2 py-1.5 bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-xs font-medium text-[var(--color-text-primary)] cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-primary)]/30">
          <div class="flex items-center gap-1">
            <Icon name="squares-2x2" :size="12" />
            <span>All visible</span>
          </div>
        </button>
        <button @click="selectWithRelations" class="px-2 py-1.5 bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-xs font-medium text-[var(--color-text-primary)] cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-primary)]/30">
          <div class="flex items-center gap-1">
            <Icon name="link" :size="12" />
            <span>With relations</span>
          </div>
        </button>
        <button @click="selectCoreEntities" class="px-2 py-1.5 bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-xs font-medium text-[var(--color-text-primary)] cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-primary)]/30">
          <div class="flex items-center gap-1">
            <Icon name="star" :size="12" />
            <span>Top 20 core</span>
          </div>
        </button>
        <button @click="selectByPrefix" class="px-2 py-1.5 bg-[var(--color-surface)] border border-[var(--color-border)] rounded text-xs font-medium text-[var(--color-text-primary)] cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-primary)]/30">
          <div class="flex items-center gap-1">
            <Icon name="funnel" :size="12" />
            <span>By prefix...</span>
          </div>
        </button>
      </div>
    </div>

    <div class="px-3 py-3 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <div class="relative flex items-center bg-[var(--color-background)] border border-[var(--color-border)] rounded-md px-2 py-2 mb-2 transition-all duration-200 focus-within:border-[var(--color-primary)] focus-within:bg-[var(--color-surface)]">
        <Icon name="magnifying-glass" :size="16" class="text-[var(--color-text-tertiary)] mr-2" />
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search entities..."
          class="flex-1 border-0 bg-transparent text-sm outline-none text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)]"
        />
        <button
          v-if="searchQuery"
          @click="searchQuery = ''"
          class="bg-transparent border-0 p-1 cursor-pointer text-[var(--color-text-tertiary)] flex items-center rounded transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-secondary)]"
        >
          <Icon name="x-mark" :size="14" />
        </button>
      </div>

      <div class="flex items-center justify-between gap-2">
        <label class="flex items-center gap-1.5 text-xs text-[var(--color-text-secondary)] cursor-pointer select-none">
          <input type="checkbox" v-model="showOnlyWithRelations" class="cursor-pointer w-3.5 h-3.5" style="accent-color: var(--color-primary)" />
          <span>With relations</span>
        </label>

        <div class="flex gap-1">
          <button @click="expandAll" class="flex items-center justify-center w-6 h-6 bg-[var(--color-surface)] border border-[var(--color-border)] rounded cursor-pointer transition-all duration-200 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]" title="Expand all">
            <Icon name="chevron-down" :size="12" />
          </button>
          <button @click="collapseAll" class="flex items-center justify-center w-6 h-6 bg-[var(--color-surface)] border border-[var(--color-border)] rounded cursor-pointer transition-all duration-200 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:text-[var(--color-text-primary)]" title="Collapse all">
            <Icon name="chevron-up" :size="12" />
          </button>
        </div>
      </div>
    </div>

    <div class="flex-1 overflow-y-auto overflow-x-hidden">
      <div v-if="filteredNamespaces.length === 0" class="flex flex-col items-center justify-center p-8 text-center text-[var(--color-text-tertiary)]">
        <Icon name="inbox" :size="48" class="mb-3 opacity-50" />
        <p class="m-0 mb-3 text-sm text-[var(--color-text-secondary)]">No entities found</p>
        <button @click="searchQuery = ''; showOnlyWithRelations = false" class="px-3 py-2 bg-[var(--color-primary)] text-white border-0 rounded-md text-sm font-semibold cursor-pointer transition-all duration-200 hover:bg-[var(--color-primary-hover)]">
          Reset filters
        </button>
      </div>

      <div v-else class="p-2">
        <div
          v-for="namespace in filteredNamespaces"
          :key="namespace.name"
          class="mb-2"
        >
          <div class="flex items-center gap-1 mb-1">
            <div
              @click="toggleNamespace(namespace.name)"
              class="flex-1 flex items-center gap-2 px-2 py-2 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 select-none hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-primary)]/30"
            >
              <Icon
                :name="isCollapsed(namespace.name) ? 'chevron-right' : 'chevron-down'"
                :size="16"
                class="text-[var(--color-text-tertiary)] transition-transform duration-200"
              />
              <Icon name="folder" :size="16" class="text-[var(--color-primary)]" />
              <span class="flex-1 text-sm font-semibold text-[var(--color-text-primary)] whitespace-nowrap overflow-hidden text-ellipsis">{{ namespace.name }}</span>
              <span class="flex items-center justify-center min-w-[24px] h-5 px-1.5 bg-[var(--color-primary-light)] text-[var(--color-primary)] text-xs font-bold rounded-full">{{ namespace.entities.length }}</span>
            </div>

            <button
              @click="selectNamespace(namespace)"
              class="flex items-center justify-center w-8 h-8 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 hover:bg-[var(--color-primary-light)] hover:border-[var(--color-primary)]/30"
              title="Add namespace to selection"
            >
              <Icon name="plus" :size="14" class="text-[var(--color-text-secondary)]" />
            </button>

            <button
              @click="selectOnlyNamespace(namespace)"
              class="flex items-center justify-center w-8 h-8 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 hover:bg-[var(--color-primary)] hover:text-white"
              title="Select only this namespace"
            >
              <Icon name="check" :size="14" class="text-[var(--color-text-secondary)]" />
            </button>
          </div>

          <div v-if="!isCollapsed(namespace.name)" class="pl-6">
            <div
              v-for="entity in namespace.entities"
              :key="entity.fqcn || entity.name"
              @click="handleEntityClick(entity, $event)"
              class="flex items-center gap-2 px-2 py-2 mb-1 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 select-none hover:bg-[var(--color-primary-light)] hover:border-[var(--color-primary)]/30 hover:translate-x-0.5"
              :class="{ 'bg-[var(--color-primary-light)] border-[var(--color-primary)] !border-2 !p-[7px]': isSelected(entity) }"
              :title="`${entity.name} (${entity.table})\nCmd/Ctrl+Click for multi-select`"
            >
              <Icon name="table-cells" :size="14" class="flex-shrink-0" :class="isSelected(entity) ? 'text-[var(--color-primary)]' : 'text-[var(--color-text-tertiary)]'" />
              <div class="flex-1 min-w-0 flex flex-col gap-1">
                <span class="text-sm font-semibold text-[var(--color-text-primary)] whitespace-nowrap overflow-hidden text-ellipsis">{{ entity.name }}</span>
                <div class="flex gap-1 flex-wrap">
                  <span class="inline-flex items-center px-1.5 py-px text-[10px] font-semibold rounded-sm bg-[#3b82f6]/10 text-[#3b82f6] whitespace-nowrap">
                    {{ entity.fields?.length || 0 }} fields
                  </span>
                  <span
                    v-if="entity.relations && entity.relations.length > 0"
                    class="inline-flex items-center px-1.5 py-px text-[10px] font-semibold rounded-sm bg-[#8b5cf6]/10 text-[#8b5cf6] whitespace-nowrap"
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

    <div class="flex items-center justify-between gap-3 px-3 py-3 bg-[var(--color-surface-raised)] border-t border-[var(--color-border)]">
      <div class="flex flex-col gap-1">
        <div class="flex items-center gap-1.5 text-xs text-[var(--color-text-secondary)]">
          <Icon name="table-cells" :size="14" />
          <span>{{ totalVisibleEntities }} visible</span>
        </div>
        <div v-if="schemaStore.selectedEntities.size > 0" class="flex items-center gap-1.5 text-xs text-[var(--color-primary)] font-semibold">
          <Icon name="check-circle" :size="14" />
          <span>{{ schemaStore.selectedEntities.size }} selected</span>
        </div>
      </div>

      <button
        v-if="totalVisibleEntities > 0 && schemaStore.selectedEntities.size === 0"
        @click="selectAllVisible"
        class="px-3 py-1.5 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-md text-xs font-semibold text-[var(--color-text-primary)] cursor-pointer transition-all duration-200 whitespace-nowrap hover:bg-[var(--color-primary)] hover:border-[var(--color-primary)] hover:text-white"
        title="Select all visible entities"
      >
        Select all
      </button>
    </div>
  </div>
</template>
