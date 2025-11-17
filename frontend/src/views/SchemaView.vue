<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import { useViewsStore } from '@/stores/views'
import SchemaGraph from '@/components/SchemaGraph.vue'
import EntitySidebar from '@/components/EntitySidebar.vue'
import PlaygroundSidebar from '@/components/PlaygroundSidebar.vue'
import PathFinderPanel from '@/components/PathFinderPanel.vue'
import ViewManager from '@/components/ViewManager.vue'
import ViewDropdown from '@/components/ViewDropdown.vue'
import Icon from '@/components/Icon.vue'
import { getEntitiesFromPaths, getRelationsFromPaths } from '@/utils/pathFinder'

const schemaStore = useSchemaStore()
const viewsStore = useViewsStore()

const viewMode = ref('schema')
const pathFinderPaths = ref([])
const viewManagerRef = ref(null)
const playgroundSidebarRef = ref(null)
const schemaGraphRef = ref(null)
const isFullscreen = ref(false)

const isPlaygroundMode = computed(() => {
  return viewsStore.currentView?.type === 'playground'
})

onMounted(() => {
  // Use filtered entities if views are active
  const baseEntities = viewsStore.currentViewId
    ? viewsStore.filteredEntities
    : schemaStore.entities

  if (schemaStore.selectedEntities.size === 0 && baseEntities.length > 0) {
    const firstFive = baseEntities
      .slice(0, Math.min(5, baseEntities.length))
      .map(e => e.fqcn || e.name)
    schemaStore.setSelectedEntities(firstFive)
  }
})

const graphEntities = computed(() => {
  if (viewMode.value === 'pathfinder' && pathFinderPaths.value.length > 0) {
    return getEntitiesFromPaths(pathFinderPaths.value)
  }

  // Use playground entities if in playground mode
  if (isPlaygroundMode.value) {
    // Get all playground entities (includes virtual and filters removed)
    const allPlaygroundEntities = viewsStore.playgroundEntities

    // Filter by selected entities
    if (schemaStore.selectedEntities.size === 0) {
      return []
    }

    const selectedFqcns = new Set(schemaStore.selectedEntities)
    return allPlaygroundEntities.filter(entity => {
      const fqcn = entity.fqcn || entity.name
      return selectedFqcns.has(fqcn) || entity.isVirtual
    })
  }

  let entities = []

  // If a view is active, apply its filters
  if (viewsStore.currentViewId || viewsStore.currentFilter.boundedContexts.length > 0 || viewsStore.currentFilter.excludeEntities.size > 0) {
    // Get filtered entities
    const filteredFqcns = new Set(viewsStore.filteredEntities.map(e => e.fqcn || e.name))

    // Return only selected entities that pass the filter
    if (schemaStore.selectedEntities.size === 0) {
      entities = []
    } else {
      entities = schemaStore.selectedEntitiesWithRelations.filter(entity => {
        return filteredFqcns.has(entity.fqcn || entity.name)
      })
    }
  } else {
    // Default behavior
    if (schemaStore.selectedEntities.size === 0) {
      entities = []
    } else {
      entities = schemaStore.selectedEntitiesWithRelations
    }
  }

  return entities
})

const graphRelations = computed(() => {
  if (viewMode.value === 'pathfinder' && pathFinderPaths.value.length > 0) {
    return getRelationsFromPaths(pathFinderPaths.value, graphEntities.value)
  }
  let rels = []
  const seenPairs = new Set()
  const visibleEntities = graphEntities.value
  const entityMapByFqcn = new Map(visibleEntities.map(e => [e.fqcn || e.name, e]))
  const entityMapByName = new Map(visibleEntities.map(e => [e.name, e]))

  visibleEntities.forEach((entity) => {
    if (entity.relations) {
      entity.relations.forEach((relation) => {
        const target = entityMapByName.get(relation.target) || entityMapByFqcn.get(relation.target)
        if (target) {
          const entityA = entity.fqcn || entity.name
          const entityB = target.fqcn || target.name
          const pairKey = [entityA, entityB].sort().join('|')
          if (!seenPairs.has(pairKey)) {
            seenPairs.add(pairKey)
            rels.push({
              from: entity,
              to: target,
              field: relation.field,
              type: relation.type,
              isOwning: relation.isOwning,
              isVirtual: false
            })
          }
        }
      })
    }
  })

  // Add virtual relations if in playground mode
  if (isPlaygroundMode.value && viewsStore.currentView?.virtualChanges?.addedRelations) {
    viewsStore.currentView.virtualChanges.addedRelations.forEach((vRel) => {
      const source = entityMapByFqcn.get(vRel.source) || entityMapByName.get(vRel.source)
      const target = entityMapByFqcn.get(vRel.target) || entityMapByName.get(vRel.target)

      if (source && target) {
        rels.push({
          from: source,
          to: target,
          field: vRel.field,
          type: vRel.type,
          isOwning: vRel.isOwning,
          isVirtual: true,
          sourceHandle: vRel.sourceHandle,
          targetHandle: vRel.targetHandle
        })
      }
    })
  }

  // Filter out removed relations in playground mode
  if (isPlaygroundMode.value && viewsStore.currentView?.virtualChanges?.removedRelations) {
    const removedSet = new Set(viewsStore.currentView.virtualChanges.removedRelations)
    rels = rels.filter(rel => {
      const fromId = rel.from.fqcn || rel.from.name
      const toId = rel.to.fqcn || rel.to.name
      const key = `${fromId}|${toId}`
      return !removedSet.has(key)
    })
  }

  return rels
})

function switchMode(mode) {
  viewMode.value = mode
  if (mode === 'pathfinder') {
    pathFinderPaths.value = []
  }
}

function handleEntityClick(entity) {
  const fqcn = entity.fqcn || entity.name
  schemaStore.selectEntity(fqcn)
}

function handleEntityDoubleClick(entity) {
  if (viewMode.value === 'schema') {
    const fqcn = entity.fqcn || entity.name
    schemaStore.addEntityToSelection(fqcn)
  }
}

function handlePathsFound(paths) {
  pathFinderPaths.value = paths
}

function handleShowPath(paths) {
  pathFinderPaths.value = paths
}

function openViewManager() {
  viewManagerRef.value?.open()
}

function toggleFullscreen() {
  isFullscreen.value = !isFullscreen.value
}

function handleContextAction(action, data) {
  switch (action) {
    case 'add-entity':
      playgroundSidebarRef.value?.openAddEntityModal()
      break
    case 'edit-entity':
      const entity = data.data.entity
      playgroundSidebarRef.value?.openEditEntityModal(entity)
      break
  }
}

function handleKeyDown(event) {
  // Toggle fullscreen with F key (only in playground mode)
  if (event.key === 'f' && isPlaygroundMode.value && !event.ctrlKey && !event.metaKey && !event.altKey) {
    // Make sure we're not typing in an input field
    if (event.target.tagName !== 'INPUT' && event.target.tagName !== 'TEXTAREA') {
      event.preventDefault()
      toggleFullscreen()
    }
  }

  // Exit fullscreen with Escape
  if (event.key === 'Escape' && isFullscreen.value) {
    isFullscreen.value = false
  }
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})
</script>

<template>
  <div class="flex h-full bg-[var(--color-background)]" :class="{ 'fullscreen-mode': isFullscreen }">
    <!-- Fullscreen exit button -->
    <button
      v-if="isFullscreen"
      @click="toggleFullscreen"
      class="fixed top-4 right-4 z-[9999] w-10 h-10 flex items-center justify-center bg-[var(--color-surface)] hover:bg-[var(--color-surface-hover)] border border-[var(--color-border)] hover:border-[var(--color-primary)] rounded-lg shadow-2xl transition-all"
      title="Exit Fullscreen (Esc)"
    >
      <Icon name="x-mark" class="w-5 h-5 text-[var(--color-text-primary)]" />
    </button>

    <aside v-if="!isFullscreen" class="w-[35%] min-w-[320px] max-w-[500px] flex-shrink-0 bg-[var(--color-surface)] flex flex-col border-r border-[var(--color-border)]">
      <div class="flex border-b-2 border-[var(--color-border)]">
        <button
          @click="switchMode('schema')"
          class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition-all border-b-2 -mb-0.5"
          :class="viewMode === 'schema'
            ? 'text-[var(--color-primary)] border-[var(--color-primary)] bg-[var(--color-surface)]'
            : 'text-[var(--color-text-secondary)] border-transparent bg-[var(--color-surface-raised)] hover:text-[var(--color-text-primary)]'"
        >
          <Icon name="table-cells" class="w-4 h-4" />
          <span>Schema</span>
        </button>
        <button
          @click="switchMode('pathfinder')"
          :disabled="isPlaygroundMode"
          class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition-all border-b-2 -mb-0.5"
          :class="isPlaygroundMode
            ? 'text-[var(--color-text-tertiary)] border-transparent bg-[var(--color-surface-raised)] opacity-50 cursor-not-allowed'
            : viewMode === 'pathfinder'
              ? 'text-[var(--color-primary)] border-[var(--color-primary)] bg-[var(--color-surface)]'
              : 'text-[var(--color-text-secondary)] border-transparent bg-[var(--color-surface-raised)] hover:text-[var(--color-text-primary)]'"
        >
          <Icon name="map" class="w-4 h-4" />
          <span>Path Finder</span>
        </button>
      </div>

      <div class="flex-1 overflow-hidden">
        <PlaygroundSidebar ref="playgroundSidebarRef" v-if="viewMode === 'schema' && isPlaygroundMode" />
        <EntitySidebar v-else-if="viewMode === 'schema'" />
        <PathFinderPanel
          v-else-if="viewMode === 'pathfinder'"
          @paths-found="handlePathsFound"
          @show-path="handleShowPath"
        />
      </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
      <div v-if="graphEntities.length > 0 && !isFullscreen" class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white shadow-md z-10">
        <div v-if="viewMode === 'schema'" class="flex items-center gap-2 text-sm">
          <Icon name="check-circle" class="w-4 h-4" />
          <span>
            <strong>{{ schemaStore.selectedEntities.size }}</strong> {{ schemaStore.selectedEntities.size === 1 ? 'entity' : 'entities' }} selected
          </span>
          <span class="opacity-60 mx-1">•</span>
          <span class="opacity-90">
            Showing {{ graphEntities.length }} entities with {{ graphRelations.length }} relations
          </span>
        </div>

        <div v-else-if="viewMode === 'pathfinder'" class="flex items-center gap-2 text-sm">
          <Icon name="map" class="w-4 h-4" />
          <span>
            <strong>{{ pathFinderPaths.length }}</strong> {{ pathFinderPaths.length === 1 ? 'path' : 'paths' }} found
          </span>
          <span class="opacity-60 mx-1">•</span>
          <span class="opacity-90">
            {{ graphEntities.length }} entities, {{ graphRelations.length }} relations
          </span>
        </div>

        <div v-if="viewMode === 'schema'" class="flex items-center gap-2">
          <ViewDropdown @open-manager="openViewManager" />
          <button
            @click="schemaStore.clearSelectedEntities()"
            class="flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 border border-white/30 hover:border-white/50 rounded-lg text-sm font-semibold transition-all hover:-translate-y-0.5"
          >
            <Icon name="x-mark" class="w-4 h-4" />
            <span>Clear</span>
          </button>
        </div>
      </div>

      <div class="flex-1 relative overflow-hidden bg-gradient-to-b from-[var(--color-surface-raised)] to-[var(--color-background)]">
        <div v-if="graphEntities.length === 0" class="absolute inset-0 flex items-center justify-center">
          <div class="text-center max-w-md p-8">
            <Icon
              :name="viewMode === 'schema' ? 'cursor-arrow-rays' : 'map'"
              class="w-16 h-16 mx-auto mb-4 text-[var(--color-text-tertiary)]"
            />

            <div v-if="viewMode === 'schema'">
              <h3 class="text-2xl font-bold text-[var(--color-text-primary)] mb-2">Select entities to visualize</h3>
              <p class="text-base text-[var(--color-text-secondary)] mb-6">Choose one or more entities from the sidebar to see their relationships</p>
              <div class="flex flex-col gap-3 max-w-xs mx-auto">
                <div class="flex items-center gap-3 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] transition-all">
                  <Icon name="hand-raised" class="w-4 h-4 text-[var(--color-text-tertiary)]" />
                  <span class="text-sm text-[var(--color-text-secondary)] font-medium">Click to select entity</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] transition-all">
                  <Icon name="command-line" class="w-4 h-4 text-[var(--color-text-tertiary)]" />
                  <span class="text-sm text-[var(--color-text-secondary)] font-medium">Cmd/Ctrl+Click for multi-select</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] transition-all">
                  <Icon name="magnifying-glass" class="w-4 h-4 text-[var(--color-text-tertiary)]" />
                  <span class="text-sm text-[var(--color-text-secondary)] font-medium">Search to find entities quickly</span>
                </div>
              </div>
            </div>

            <div v-else-if="viewMode === 'pathfinder'">
              <h3 class="text-2xl font-bold text-[var(--color-text-primary)] mb-2">Find connection paths</h3>
              <p class="text-base text-[var(--color-text-secondary)] mb-6">Select a source and target entity to discover how they're connected</p>
              <div class="flex flex-col gap-3 max-w-xs mx-auto">
                <div class="flex items-center gap-3 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] transition-all">
                  <Icon name="arrow-up-circle" class="w-4 h-4 text-[var(--color-text-tertiary)]" />
                  <span class="text-sm text-[var(--color-text-secondary)] font-medium">Choose source entity</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] transition-all">
                  <Icon name="arrow-down-circle" class="w-4 h-4 text-[var(--color-text-tertiary)]" />
                  <span class="text-sm text-[var(--color-text-secondary)] font-medium">Choose target entity</span>
                </div>
                <div class="flex items-center gap-3 p-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] transition-all">
                  <Icon name="magnifying-glass-circle" class="w-4 h-4 text-[var(--color-text-tertiary)]" />
                  <span class="text-sm text-[var(--color-text-secondary)] font-medium">Click "Find Paths" to discover connections</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <SchemaGraph
          ref="schemaGraphRef"
          v-else
          :entities="graphEntities"
          :relations="graphRelations"
          :focused-entity="null"
          :is-playground-mode="isPlaygroundMode"
          @entity-click="handleEntityClick"
          @entity-double-click="handleEntityDoubleClick"
          @toggle-fullscreen="toggleFullscreen"
          @context-action="handleContextAction"
        />
      </div>
    </main>

    <!-- ViewManager Sidebar (hidden by default) -->
    <ViewManager ref="viewManagerRef" />
  </div>
</template>
