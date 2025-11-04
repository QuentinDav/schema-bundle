<script setup>
import { ref, computed, watch } from 'vue'
import { VueFlow, useVueFlow, Panel } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
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

const { fitView, zoomIn, zoomOut, getViewport } = useVueFlow()

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
const performanceMode = ref(false)
const detailLevel = ref('full')

const shouldShowLabels = computed(() => {
  return detailLevel.value === 'full'
})

const nodes = computed(() => {
  const entitiesKey = props.entities.map(e => e.fqcn || e.name).sort().join(',')

  if (isCalculating.value && nodesWithLayout.value.length > 0) {
    return nodesWithLayout.value
  }

  if (nodesWithLayout.value.length > 0 && entitiesKey === currentEntitiesKey.value) {
    return nodesWithLayout.value
  }

  if (props.entities.length === 0) {
    return []
  }

  if (nodesWithLayout.value.length > 0) {
    return nodesWithLayout.value
  }

  return []
})

const edges = computed(() => {
  const showLabels = shouldShowLabels.value

  return props.relations.map((relation, index) => {
    const sourceId = relation.from.fqcn || relation.from.name
    const targetId = relation.to.fqcn || relation.to.name
    const edgeColor = getEdgeColor(relation.type)

    const edge = {
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
      data: {
        type: relation.type,
        typeLabel: getRelationTypeLabel(relation.type),
        field: relation.field,
        isOwning: relation.isOwning,
      },
    }

    if (showLabels && props.entities.length < 100) {
      edge.label = relation.field
      edge.labelStyle = {
        fill: edgeColor,
        fontWeight: 600,
        fontSize: 11,
      }
      edge.labelBgStyle = {
        fill: '#141414',
        fillOpacity: 0.95,
      }
      edge.labelBgPadding = [4, 6]
      edge.labelBgBorderRadius = 4
    }

    return edge
  })
})

async function calculateLayout() {
  if (props.entities.length === 0) {
    nodesWithLayout.value = []
    currentEntitiesKey.value = ''
    return
  }

  const entityCount = props.entities.length

  if (entityCount > 100) {
    performanceMode.value = true
    detailLevel.value = 'medium'
  } else {
    performanceMode.value = false
    detailLevel.value = 'full'
  }

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

  const layoutOptions = {
    'elk.algorithm': 'layered',
    'elk.direction': 'DOWN',
    'elk.spacing.nodeNode': entityCount > 100 ? '60' : '80',
    'elk.layered.spacing.nodeNodeBetweenLayers': entityCount > 100 ? '100' : '120',
    'elk.layered.nodePlacement.strategy': 'SIMPLE',
  }

  if (entityCount > 200) {
    layoutOptions['elk.layered.considerModelOrder.strategy'] = 'NONE'
    layoutOptions['elk.layered.compaction.postCompaction.strategy'] = 'EDGE_LENGTH'
  }

  const graph = {
    id: 'root',
    layoutOptions,
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

    if (props.entities.length > 50) {
      setTimeout(() => {
        fitView({ padding: 0.1, duration: 400 })
      }, 100)
    }

  } catch (error) {
    console.error('ELK layout error:', error)
  } finally {
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

    ctx.fillStyle = '#0a0a0a'
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
  <div class="w-full h-full bg-[var(--color-background)] relative">
    <div v-if="isCalculating" class="absolute top-4 left-1/2 transform -translate-x-1/2 z-50 px-4 py-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-lg flex items-center gap-2">
      <Icon name="arrow-path" :size="16" class="text-[var(--color-primary)] animate-spin" />
      <span class="text-sm text-[var(--color-text-primary)] font-medium">Calculating layout for {{ entities.length }} entities...</span>
    </div>

    <div v-if="performanceMode && !isCalculating" class="absolute top-4 left-1/2 transform -translate-x-1/2 z-50 px-3 py-1.5 bg-[var(--color-warning-light)] border border-[var(--color-warning)]/30 rounded-md flex items-center gap-2">
      <Icon name="bolt" :size="14" class="text-[var(--color-warning)]" />
      <span class="text-xs text-[var(--color-text-primary)] font-medium">Performance mode ({{ entities.length }} entities)</span>
    </div>

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
      class="w-full h-full"
    >
      <Background
        pattern-color="#2a2a2a"
        :gap="16"
        :size="1"
        variant="dots"
      />

      <Panel position="bottom-left" class="!bg-transparent !border-0 !shadow-none">
        <div class="bg-[var(--color-surface)] px-4 py-3 rounded-lg shadow-lg border border-[var(--color-border)]">
          <div class="text-xs font-bold mb-2.5 text-[var(--color-text-primary)]">Relations</div>
          <div class="flex flex-col gap-1.5">
            <div class="flex items-center gap-2">
              <div class="w-8 h-0.5 rounded-full bg-[#10b981]"></div>
              <span class="text-[11px] text-[var(--color-text-secondary)]">One to One (1:1)</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-8 h-0.5 rounded-full bg-[#3b82f6]"></div>
              <span class="text-[11px] text-[var(--color-text-secondary)]">Many to One (N:1)</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-8 h-0.5 rounded-full bg-[#f59e0b]"></div>
              <span class="text-[11px] text-[var(--color-text-secondary)]">One to Many (1:N)</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-8 h-0.5 rounded-full bg-[#ef4444]"></div>
              <span class="text-[11px] text-[var(--color-text-secondary)]">Many to Many (N:N)</span>
            </div>
          </div>
        </div>
      </Panel>

      <Panel position="top-right" class="!bg-transparent !border-0 !shadow-none">
        <div class="flex flex-col gap-2">
          <button @click="handleFitView" class="w-11 h-11 flex items-center justify-center bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)] hover:-translate-y-0.5 shadow-lg" title="Fit to view">
            <Icon name="arrows-pointing-out" :size="20" class="text-[var(--color-text-primary)]" />
          </button>
          <button @click="handleZoomIn" class="w-11 h-11 flex items-center justify-center bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)] hover:-translate-y-0.5 shadow-lg" title="Zoom in">
            <Icon name="plus" :size="20" class="text-[var(--color-text-primary)]" />
          </button>
          <button @click="handleZoomOut" class="w-11 h-11 flex items-center justify-center bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)] hover:-translate-y-0.5 shadow-lg" title="Zoom out">
            <Icon name="minus" :size="20" class="text-[var(--color-text-primary)]" />
          </button>
          <div class="w-full h-px bg-[var(--color-border)] my-1"></div>
          <button @click="exportSVG" class="w-11 h-11 flex items-center justify-center bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)] hover:-translate-y-0.5 shadow-lg" title="Export SVG">
            <Icon name="arrow-down-tray" :size="20" class="text-[var(--color-text-primary)]" />
          </button>
          <button @click="exportPNG" class="w-11 h-11 flex items-center justify-center bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg cursor-pointer transition-all duration-200 hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-border-hover)] hover:-translate-y-0.5 shadow-lg" title="Export PNG">
            <Icon name="photo" :size="20" class="text-[var(--color-text-primary)]" />
          </button>
        </div>
      </Panel>
    </VueFlow>
  </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
</style>

<style scoped>
:deep(.vue-flow__background) {
  background-color: var(--color-background);
}

:deep(.vue-flow__node.selected) {
  box-shadow: none;
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

:deep(.vue-flow__edge-textbg) {
  fill: var(--color-surface);
  fill-opacity: 0.95;
  rx: 4px;
}

:deep(.vue-flow__handle-connecting),
:deep(.vue-flow__handle-valid),
:deep(.vue-flow__connectionline) {
  display: none;
}

:deep(.vue-flow__controls) {
  display: none;
}
</style>
