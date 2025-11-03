<script setup>
import { ref, computed, watch } from 'vue'
import { VueFlow, useVueFlow, Panel } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import { Controls } from '@vue-flow/controls'
import DatabaseTableNode from './DatabaseTableNode.vue'
import Icon from './Icon.vue'
import ELK from 'elkjs/lib/elk.bundled.js'

const elk = new ELK()

const props = defineProps({
  entities: {
    type: Array,
    required: true,
  },
  relations: {
    type: Array,
    default: () => [],
  },
  focusedEntity: {
    type: String,
    default: null,
  },
})

const emit = defineEmits(['entity-click', 'entity-hover', 'entity-double-click'])

const nodeTypes = {
  databaseTable: DatabaseTableNode
}

const { fitView, zoomIn, zoomOut, setCenter, getNodes, getEdges } = useVueFlow()

function extractNamespace(fqcn) {
  const parts = fqcn.split('\\')
  return parts.slice(0, -1).join('\\') || 'Default'
}

function getEdgeColor(type) {
  const colors = {
    1: '#10b981',
    2: '#3b82f6',
    4: '#f59e0b',
    8: '#ef4444',
  }
  return colors[type] || '#6b7280'
}

function getRelationTypeLabel(type) {
  const labels = {
    1: '1:1',
    2: 'N:1',
    4: '1:N',
    8: 'N:N',
  }
  return labels[type] || ''
}

const nodesWithLayout = ref([])
const currentEntitiesKey = ref('')
const isCalculating = ref(false)

const nodes = computed(() => {
  const entitiesKey = props.entities.map(e => e.fqcn || e.name).sort().join(',')

  // If currently calculating and we have old layout, keep showing it
  if (isCalculating.value && nodesWithLayout.value.length > 0) {
    return nodesWithLayout.value
  }

  // If layout ready for current entities, show it
  if (nodesWithLayout.value.length > 0 && entitiesKey === currentEntitiesKey.value) {
    return nodesWithLayout.value
  }

  // If no entities, return empty
  if (props.entities.length === 0) {
    return []
  }

  // Otherwise keep old layout until new one is calculated
  if (nodesWithLayout.value.length > 0) {
    return nodesWithLayout.value
  }

  // First render: return empty, layout will be calculated
  return []
})

const edges = computed(() => {
  return props.relations.map((relation, index) => {
    const sourceId = relation.from.fqcn || relation.from.name
    const targetId = relation.to.fqcn || relation.to.name
    const edgeColor = getEdgeColor(relation.type)

    return {
      id: `edge-${index}-${sourceId}-${targetId}`,
      source: sourceId,
      target: targetId,
      sourceHandle: 'bottom',
      targetHandle: 'top',
      type: 'smoothstep',
      animated: false,
      style: {
        stroke: edgeColor,
        strokeWidth: relation.isOwning ? 2.5 : 2,
        strokeDasharray: relation.isOwning ? '0' : '5,5',
      },
      markerEnd: {
        type: 'arrowclosed',
        color: edgeColor,
        width: 20,
        height: 20,
      },
      label: relation.field,
      labelStyle: {
        fill: edgeColor,
        fontWeight: 600,
        fontSize: 11,
      },
      labelBgStyle: {
        fill: 'white',
        fillOpacity: 0.9,
      },
      labelBgPadding: [4, 6],
      labelBgBorderRadius: 4,
      data: {
        type: relation.type,
        typeLabel: getRelationTypeLabel(relation.type),
        field: relation.field,
        isOwning: relation.isOwning,
      },
    }
  })
})

async function calculateLayout() {
  if (props.entities.length === 0) {
    nodesWithLayout.value = []
    currentEntitiesKey.value = ''
    return
  }

  // Mark as calculating (keeps old layout visible)
  isCalculating.value = true

  const elkNodes = props.entities.map(entity => ({
    id: entity.fqcn || entity.name,
    width: 280,
    height: 80 + Math.min((entity.fields || []).length, 8) * 22,
  }))

  const elkEdges = props.relations.map((relation, index) => ({
    id: `edge-${index}`,
    sources: [relation.from.fqcn || relation.from.name],
    targets: [relation.to.fqcn || relation.to.name],
  }))

  const graph = {
    id: 'root',
    layoutOptions: {
      'elk.algorithm': 'layered',
      'elk.direction': 'DOWN',
      'elk.spacing.nodeNode': '80',
      'elk.layered.spacing.nodeNodeBetweenLayers': '120',
      'elk.layered.nodePlacement.strategy': 'SIMPLE',
    },
    children: elkNodes,
    edges: elkEdges,
  }

  try {
    const layoutedGraph = await elk.layout(graph)

    currentEntitiesKey.value = props.entities.map(e => e.fqcn || e.name).sort().join(',')

    nodesWithLayout.value = props.entities.map((entity) => {
      const elkNode = layoutedGraph.children?.find(n => n.id === (entity.fqcn || entity.name))

      return {
        id: entity.fqcn || entity.name,
        type: 'databaseTable',
        position: {
          x: elkNode?.x ?? 0,
          y: elkNode?.y ?? 0,
        },
        data: {
          name: entity.name,
          table: entity.table,
          fields: entity.fields || [],
          namespace: extractNamespace(entity.fqcn || entity.name),
          entity: entity,
        },
        draggable: true,
        width: 280,
        height: 80 + Math.min((entity.fields || []).length, 8) * 22,
      }
    })

  } catch (error) {
    console.error('ELK layout error:', error)
  } finally {
    // Done calculating
    isCalculating.value = false
  }
}

watch([() => props.entities, () => props.relations], () => {
  calculateLayout()
}, { immediate: true, deep: true })

function onNodeClick(event) {
  emit('entity-click', event.node.data.entity)
}

function onNodeDoubleClick(event) {
  emit('entity-double-click', event.node.data.entity)
}

function onNodeMouseEnter(event) {
  emit('entity-hover', event.node.data.entity)
}

function exportSVG() {
  const vueFlowElement = document.querySelector('.vue-flow')
  if (!vueFlowElement) {
    console.error('Vue Flow element not found')
    return
  }

  const svg = vueFlowElement.querySelector('.vue-flow__renderer')
  if (!svg) {
    console.error('Vue Flow renderer not found')
    return
  }

  const bbox = svg.getBBox()
  const padding = 20

  const svgData = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="${bbox.width + padding * 2}" height="${bbox.height + padding * 2}"
     viewBox="${bbox.x - padding} ${bbox.y - padding} ${bbox.width + padding * 2} ${bbox.height + padding * 2}">
  ${svg.innerHTML}
</svg>`

  const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = `schema-${Date.now()}.svg`
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
}

function exportPNG() {
  const vueFlowElement = document.querySelector('.vue-flow')
  if (!vueFlowElement) {
    console.error('Vue Flow element not found')
    return
  }

  const svg = vueFlowElement.querySelector('.vue-flow__renderer')
  if (!svg) {
    console.error('Vue Flow renderer not found')
    return
  }

  const bbox = svg.getBBox()
  const padding = 20
  const scale = 2

  const svgData = `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"
     width="${bbox.width + padding * 2}" height="${bbox.height + padding * 2}"
     viewBox="${bbox.x - padding} ${bbox.y - padding} ${bbox.width + padding * 2} ${bbox.height + padding * 2}">
  ${svg.innerHTML}
</svg>`

  const img = new Image()
  const blob = new Blob([svgData], { type: 'image/svg+xml;charset=utf-8' })
  const url = URL.createObjectURL(blob)

  img.onload = () => {
    const canvas = document.createElement('canvas')
    canvas.width = (bbox.width + padding * 2) * scale
    canvas.height = (bbox.height + padding * 2) * scale
    const ctx = canvas.getContext('2d')

    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)

    ctx.scale(scale, scale)

    ctx.drawImage(img, 0, 0)

    canvas.toBlob((pngBlob) => {
      const pngUrl = URL.createObjectURL(pngBlob)
      const link = document.createElement('a')
      link.href = pngUrl
      link.download = `schema-${Date.now()}.png`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(pngUrl)
      URL.revokeObjectURL(url)
    }, 'image/png')
  }

  img.onerror = () => {
    console.error('Failed to load SVG image for PNG export')
    URL.revokeObjectURL(url)
  }

  img.src = url
}

function handleFitView() {
  fitView({ padding: 0.2, duration: 800 })
}

function handleZoomIn() {
  zoomIn({ duration: 300 })
}

function handleZoomOut() {
  zoomOut({ duration: 300 })
}

defineExpose({
  fitView: handleFitView,
  zoomIn: handleZoomIn,
  zoomOut: handleZoomOut,
  exportSVG,
  exportPNG,
})
</script>

<template>
  <div class="schema-graph-container">
    <VueFlow
      :nodes="nodes"
      :edges="edges"
      :node-types="nodeTypes"
      :default-viewport="{ zoom: 1, x: 0, y: 0 }"
      :min-zoom="0.1"
      :max-zoom="4"
      :snap-to-grid="false"
      :zoom-on-scroll="true"
      :pan-on-scroll="false"
      :zoom-on-double-click="false"
      :nodes-connectable="false"
      :nodes-draggable="true"
      :elements-selectable="false"
      :connect-on-click="false"
      @node-click="onNodeClick"
      @node-double-click="onNodeDoubleClick"
      @node-mouse-enter="onNodeMouseEnter"
      class="schema-flow"
    >
      <!-- Background with dots pattern -->
      <Background
        pattern-color="#e5e7eb"
        :gap="16"
        :size="1"
        variant="dots"
      />

      <!-- Controls Panel -->
      <Controls
        :show-zoom="true"
        :show-fit-view="true"
        :show-interactive="false"
        position="top-right"
      />

      <!-- Custom Legend Panel -->
      <Panel position="bottom-left" class="legend-panel">
        <div class="graph-legend">
          <div class="legend-title">Relations</div>
          <div class="legend-item">
            <div class="legend-line" style="background: #10b981"></div>
            <span>One to One (1:1)</span>
          </div>
          <div class="legend-item">
            <div class="legend-line" style="background: #3b82f6"></div>
            <span>Many to One (N:1)</span>
          </div>
          <div class="legend-item">
            <div class="legend-line" style="background: #f59e0b"></div>
            <span>One to Many (1:N)</span>
          </div>
          <div class="legend-item">
            <div class="legend-line" style="background: #ef4444"></div>
            <span>Many to Many (N:N)</span>
          </div>
        </div>
      </Panel>

      <!-- Custom Controls Panel -->
      <Panel position="top-right" class="controls-panel">
        <div class="graph-controls">
          <button @click="handleFitView" class="control-btn" title="Fit to view">
            <Icon name="arrows-pointing-out" :size="20" />
          </button>
          <button @click="handleZoomIn" class="control-btn" title="Zoom in">
            <Icon name="plus" :size="20" />
          </button>
          <button @click="handleZoomOut" class="control-btn" title="Zoom out">
            <Icon name="minus" :size="20" />
          </button>
          <div class="divider"></div>
          <button @click="exportSVG" class="control-btn" title="Export SVG">
            <Icon name="arrow-down-tray" :size="20" />
          </button>
          <button @click="exportPNG" class="control-btn" title="Export PNG">
            <Icon name="photo" :size="20" />
          </button>
        </div>
      </Panel>
    </VueFlow>
  </div>
</template>

<style>
/* Import Vue Flow base styles */
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
@import '@vue-flow/controls/dist/style.css';
</style>

<style scoped>
.schema-graph-container {
  width: 100%;
  height: 100%;
  background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
}

.schema-flow {
  width: 100%;
  height: 100%;
}

/* Custom Controls */
.controls-panel {
  background: transparent;
  border: none;
  box-shadow: none;
}

.graph-controls {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.control-btn {
  width: 44px;
  height: 44px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all var(--transition-base);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.control-btn:hover {
  background: var(--color-gray-50);
  border-color: var(--color-primary-500);
  color: var(--color-primary-500);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  transform: translateY(-1px);
}

.control-btn:active {
  transform: translateY(0);
}

.divider {
  width: 100%;
  height: 1px;
  background: var(--color-gray-200);
  margin: var(--spacing-1) 0;
}

/* Legend */
.legend-panel {
  background: transparent;
  border: none;
  box-shadow: none;
}

.graph-legend {
  background: white;
  padding: var(--spacing-4);
  border-radius: var(--radius-lg);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
  border: 1px solid var(--color-gray-200);
}

.legend-title {
  font-weight: 700;
  font-size: var(--text-sm);
  margin-bottom: var(--spacing-3);
  color: var(--color-gray-900);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-2);
  font-size: var(--text-xs);
  color: var(--color-gray-600);
}

.legend-item:last-child {
  margin-bottom: 0;
}

.legend-line {
  width: 32px;
  height: 3px;
  border-radius: 2px;
}

/* Override Vue Flow default styles for light theme */
:deep(.vue-flow__node) {
  border-radius: 8px;
}

:deep(.vue-flow__edge-path) {
  transition: stroke-width 0.2s ease;
  stroke-linecap: round;
}

:deep(.vue-flow__edge:hover .vue-flow__edge-path) {
  stroke-width: 4px !important;
}

:deep(.vue-flow__edge-text) {
  font-size: 11px;
}

:deep(.vue-flow__controls) {
  display: none; /* Hide default controls, we use custom ones */
}

/* Light theme background */
:deep(.vue-flow__background) {
  background-color: #fafbfc;
}

/* Disable selection styling - no blue outline */
:deep(.vue-flow__node.selected) {
  box-shadow: none;
}

:deep(.vue-flow__node.selected .database-table-node) {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

/* Edge labels with better visibility */
:deep(.vue-flow__edge-textbg) {
  fill: white;
  fill-opacity: 0.95;
  rx: 4px;
}

:deep(.vue-flow__edge-text) {
  fill: var(--color-gray-700);
  font-weight: 600;
}

/* Prevent handle connection UI from showing */
:deep(.vue-flow__handle-connecting) {
  display: none;
}

:deep(.vue-flow__handle-valid) {
  display: none;
}

:deep(.vue-flow__connectionline) {
  display: none;
}
</style>
