<script setup>
import { computed, onMounted, ref } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import SchemaGraph from '@/components/SchemaGraph.vue'
import EntitySidebar from '@/components/EntitySidebar.vue'
import PathFinderPanel from '@/components/PathFinderPanel.vue'
import Icon from '@/components/Icon.vue'
import { getEntitiesFromPaths, getRelationsFromPaths } from '@/utils/pathFinder'

const schemaStore = useSchemaStore()

const viewMode = ref('schema')
const pathFinderPaths = ref([])

onMounted(() => {
  if (schemaStore.selectedEntities.size === 0 && schemaStore.entities.length > 0) {
    const firstFive = schemaStore.entities
      .slice(0, Math.min(5, schemaStore.entities.length))
      .map(e => e.fqcn || e.name)
    schemaStore.setSelectedEntities(firstFive)
  }
})

const graphEntities = computed(() => {
  if (viewMode.value === 'pathfinder' && pathFinderPaths.value.length > 0) {
    return getEntitiesFromPaths(pathFinderPaths.value)
  }
  if (schemaStore.selectedEntities.size === 0) return []
  return schemaStore.selectedEntitiesWithRelations
})

const graphRelations = computed(() => {
  if (viewMode.value === 'pathfinder' && pathFinderPaths.value.length > 0) {
    return getRelationsFromPaths(pathFinderPaths.value, graphEntities.value)
  }
  const rels = []
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
            })
          }
        }
      })
    }
  })
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
</script>

<template>
  <div class="flex h-full bg-[var(--color-background)]">
    <aside class="w-[35%] min-w-[320px] max-w-[500px] flex-shrink-0 bg-[var(--color-surface)] flex flex-col border-r border-[var(--color-border)]">
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
          class="flex-1 flex items-center justify-center gap-2 px-4 py-3 text-sm font-semibold transition-all border-b-2 -mb-0.5"
          :class="viewMode === 'pathfinder'
            ? 'text-[var(--color-primary)] border-[var(--color-primary)] bg-[var(--color-surface)]'
            : 'text-[var(--color-text-secondary)] border-transparent bg-[var(--color-surface-raised)] hover:text-[var(--color-text-primary)]'"
        >
          <Icon name="map" class="w-4 h-4" />
          <span>Path Finder</span>
        </button>
      </div>

      <div class="flex-1 overflow-hidden">
        <EntitySidebar v-if="viewMode === 'schema'" />
        <PathFinderPanel
          v-else-if="viewMode === 'pathfinder'"
          @paths-found="handlePathsFound"
          @show-path="handleShowPath"
        />
      </div>
    </aside>

    <main class="flex-1 flex flex-col overflow-hidden">
      <div v-if="graphEntities.length > 0" class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)] text-white shadow-md z-10">
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

        <button
          v-if="viewMode === 'schema'"
          @click="schemaStore.clearSelectedEntities()"
          class="flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 border border-white/30 hover:border-white/50 rounded-lg text-sm font-semibold transition-all hover:-translate-y-0.5"
        >
          <Icon name="x-mark" class="w-4 h-4" />
          <span>Clear</span>
        </button>
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
          v-else
          :entities="graphEntities"
          :relations="graphRelations"
          :focused-entity="null"
          @entity-click="handleEntityClick"
          @entity-double-click="handleEntityDoubleClick"
        />
      </div>
    </main>
  </div>
</template>
