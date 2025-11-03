<script setup>
import { computed, onMounted, ref } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import SchemaGraph from '@/components/SchemaGraph.vue'
import EntitySidebar from '@/components/EntitySidebar.vue'
import PathFinderPanel from '@/components/PathFinderPanel.vue'
import Icon from '@/components/Icon.vue'
import { getEntitiesFromPaths, getRelationsFromPaths } from '@/utils/pathFinder'

const schemaStore = useSchemaStore()

const viewMode = ref('schema') // 'schema', 'pathfinder', or 'query'
const pathFinderPaths = ref([])

onMounted(() => {
  // Auto-select first 5 entities if none selected (for initial view in schema mode)
  if (schemaStore.selectedEntities.size === 0 && schemaStore.entities.length > 0) {
    const firstFive = schemaStore.entities
      .slice(0, Math.min(5, schemaStore.entities.length))
      .map(e => e.fqcn || e.name)

    schemaStore.setSelectedEntities(firstFive)
  }
})

// Entities to display in graph
const graphEntities = computed(() => {
  // Path Finder mode: show entities from found paths
  if (viewMode.value === 'pathfinder' && pathFinderPaths.value.length > 0) {
    return getEntitiesFromPaths(pathFinderPaths.value)
  }

  // Schema mode: show selected entities + their relations
  if (schemaStore.selectedEntities.size === 0) {
    return []
  }

  return schemaStore.selectedEntitiesWithRelations
})

// Relations between visible entities
const graphRelations = computed(() => {
  // Path Finder mode: show relations from paths
  if (viewMode.value === 'pathfinder' && pathFinderPaths.value.length > 0) {
    return getRelationsFromPaths(pathFinderPaths.value, graphEntities.value)
  }

  // Schema mode: compute relations from visible entities
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

// Switch view mode
function switchMode(mode) {
  viewMode.value = mode

  if (mode === 'pathfinder') {
    // Clear path finder results when switching
    pathFinderPaths.value = []
  }
}

// Handle visualize query from Query Builder
function handleVisualizeQuery(result) {
  // Switch to schema mode and show the entities
  viewMode.value = 'schema'
}

// Handle entity click from graph (open drawer)
function handleEntityClick(entity) {
  const fqcn = entity.fqcn || entity.name

  // Open drawer with entity details
  schemaStore.selectEntity(fqcn)
}

// Handle entity double-click from graph (add to selection in schema mode)
function handleEntityDoubleClick(entity) {
  if (viewMode.value === 'schema') {
    const fqcn = entity.fqcn || entity.name
    schemaStore.addEntityToSelection(fqcn)
  }
}

// Handle paths found from PathFinderPanel
function handlePathsFound(paths) {
  pathFinderPaths.value = paths
}

// Handle show path from PathFinderPanel
function handleShowPath(paths) {
  pathFinderPaths.value = paths
}
</script>

<template>
  <div class="schema-view-layout">
    <!-- Left Sidebar (35%) -->
    <aside class="schema-sidebar">
      <!-- Tabs -->
      <div class="sidebar-tabs">
        <button
          @click="switchMode('schema')"
          class="tab-btn"
          :class="{ active: viewMode === 'schema' }"
        >
          <Icon name="table-cells" :size="18" />
          <span>Schema</span>
        </button>
        <button
          @click="switchMode('pathfinder')"
          class="tab-btn"
          :class="{ active: viewMode === 'pathfinder' }"
        >
          <Icon name="map" :size="18" />
          <span>Path Finder</span>
        </button>
      </div>

      <!-- Content based on mode -->
      <div class="sidebar-content">
        <EntitySidebar v-if="viewMode === 'schema'" />
        <PathFinderPanel
          v-else-if="viewMode === 'pathfinder'"
          @paths-found="handlePathsFound"
          @show-path="handleShowPath"
        />
      </div>
    </aside>

    <!-- Right Content (65%) -->
    <main class="schema-main">
      <!-- Top Bar with info -->
      <div v-if="graphEntities.length > 0" class="info-bar">
        <!-- Schema mode info -->
        <div v-if="viewMode === 'schema'" class="info-content">
          <Icon name="check-circle" :size="16" />
          <span>
            <strong>{{ schemaStore.selectedEntities.size }}</strong> {{ schemaStore.selectedEntities.size === 1 ? 'entity' : 'entities' }} selected
          </span>
          <span class="separator">•</span>
          <span class="relations-info">
            Showing {{ graphEntities.length }} entities with {{ graphRelations.length }} relations
          </span>
        </div>

        <!-- Path Finder mode info -->
        <div v-else-if="viewMode === 'pathfinder'" class="info-content pathfinder-info">
          <Icon name="map" :size="16" />
          <span>
            <strong>{{ pathFinderPaths.length }}</strong> {{ pathFinderPaths.length === 1 ? 'path' : 'paths' }} found
          </span>
          <span class="separator">•</span>
          <span class="relations-info">
            {{ graphEntities.length }} entities, {{ graphRelations.length }} relations
          </span>
        </div>

        <button
          v-if="viewMode === 'schema'"
          @click="schemaStore.clearSelectedEntities()"
          class="clear-btn"
        >
          <Icon name="x-mark" :size="16" />
          <span>Clear</span>
        </button>
      </div>

      <!-- Graph Canvas -->
      <div class="graph-container">
        <!-- Empty state -->
        <div v-if="graphEntities.length === 0" class="empty-state">
          <Icon
            :name="viewMode === 'schema' ? 'cursor-arrow-rays' : 'map'"
            :size="64"
            class="empty-icon"
          />

          <!-- Schema mode empty state -->
          <div v-if="viewMode === 'schema'">
            <h3>Select entities to visualize</h3>
            <p>Choose one or more entities from the sidebar to see their relationships</p>
            <div class="tips">
              <div class="tip-item">
                <Icon name="hand-raised" :size="16" />
                <span>Click to select entity</span>
              </div>
              <div class="tip-item">
                <Icon name="command-line" :size="16" />
                <span>Cmd/Ctrl+Click for multi-select</span>
              </div>
              <div class="tip-item">
                <Icon name="magnifying-glass" :size="16" />
                <span>Search to find entities quickly</span>
              </div>
            </div>
          </div>

          <!-- Path Finder mode empty state -->
          <div v-else-if="viewMode === 'pathfinder'">
            <h3>Find connection paths</h3>
            <p>Select a source and target entity to discover how they're connected</p>
            <div class="tips">
              <div class="tip-item">
                <Icon name="arrow-up-circle" :size="16" />
                <span>Choose source entity</span>
              </div>
              <div class="tip-item">
                <Icon name="arrow-down-circle" :size="16" />
                <span>Choose target entity</span>
              </div>
              <div class="tip-item">
                <Icon name="magnifying-glass-circle" :size="16" />
                <span>Click "Find Paths" to discover connections</span>
              </div>
            </div>
          </div>

          <!-- Query Builder mode empty state -->
          <div v-else-if="viewMode === 'query'">
            <h3>Generate SQL from natural language</h3>
            <p>Describe your query in plain language and get SQL automatically</p>
            <div class="tips">
              <div class="tip-item">
                <Icon name="command-line" :size="16" />
                <span>Type your query in natural language</span>
              </div>
              <div class="tip-item">
                <Icon name="sparkles" :size="16" />
                <span>Click "Generate SQL" to get the query</span>
              </div>
              <div class="tip-item">
                <Icon name="eye" :size="16" />
                <span>Visualize entities in the graph</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Graph -->
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

<style scoped>
.schema-view-layout {
  height: 100%;
  display: flex;
  background: var(--color-gray-50);
}

/* Sidebar */
.schema-sidebar {
  width: 35%;
  min-width: 320px;
  max-width: 500px;
  height: 100%;
  flex-shrink: 0;
  background: white;
  display: flex;
  flex-direction: column;
}

/* Tabs */
.sidebar-tabs {
  display: flex;
  background: white;
  border-bottom: 2px solid var(--color-gray-200);
}

.tab-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-4);
  background: var(--color-gray-50);
  border: none;
  border-bottom: 3px solid transparent;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-600);
  cursor: pointer;
  transition: all var(--transition-base);
}

.tab-btn:hover {
  background: var(--color-gray-100);
  color: var(--color-gray-900);
}

.tab-btn.active {
  background: white;
  color: var(--color-primary-600);
  border-bottom-color: var(--color-primary-500);
}

.tab-btn.tab-link {
  text-decoration: none;
}

/* Sidebar Content */
.sidebar-content {
  flex: 1;
  overflow: hidden;
}

/* Main Content */
.schema-main {
  flex: 1;
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Info Bar */
.info-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-3) var(--spacing-4);
  background: linear-gradient(135deg, var(--color-primary-500) 0%, var(--color-primary-600) 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  z-index: 10;
}

.info-bar .pathfinder-info {
  background: linear-gradient(135deg, var(--color-purple-500) 0%, var(--color-purple-600) 100%);
}

.info-content {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-sm);
}

.info-content strong {
  font-weight: 700;
}

.separator {
  opacity: 0.6;
  margin: 0 var(--spacing-1);
}

.relations-info {
  opacity: 0.9;
  font-size: var(--text-sm);
}

.clear-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2) var(--spacing-3);
  background: rgba(255, 255, 255, 0.2);
  border: 1px solid rgba(255, 255, 255, 0.3);
  border-radius: var(--radius-md);
  color: white;
  font-size: var(--text-sm);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
}

.clear-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-1px);
}

/* Graph Container */
.graph-container {
  flex: 1;
  position: relative;
  overflow: hidden;
  background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
}

/* Empty State */
.empty-state {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  text-align: center;
  max-width: 500px;
  padding: var(--spacing-8);
}

.empty-icon {
  margin: 0 auto var(--spacing-4);
  color: var(--color-gray-300);
}

.empty-state h3 {
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--color-gray-700);
  margin: 0 0 var(--spacing-2) 0;
}

.empty-state p {
  font-size: var(--text-base);
  color: var(--color-gray-500);
  margin: 0 0 var(--spacing-6) 0;
  line-height: 1.6;
}

.tips {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
  align-items: flex-start;
  text-align: left;
  margin: 0 auto;
  max-width: 320px;
}

.tip-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-3);
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  width: 100%;
  transition: all var(--transition-base);
}

.tip-item:hover {
  border-color: var(--color-primary-300);
  background: var(--color-primary-50);
  transform: translateX(4px);
}

.tip-item span {
  font-size: var(--text-sm);
  color: var(--color-gray-600);
  font-weight: 500;
}

.tip-item:hover span {
  color: var(--color-primary-700);
}

/* Responsive */
@media (max-width: 1280px) {
  .schema-sidebar {
    width: 40%;
  }
}

@media (max-width: 1024px) {
  .schema-view-layout {
    flex-direction: column;
  }

  .schema-sidebar {
    width: 100%;
    max-width: none;
    height: 40vh;
    border-right: none;
    border-bottom: 2px solid var(--color-gray-300);
  }

  .schema-main {
    height: 60vh;
  }
}
</style>
