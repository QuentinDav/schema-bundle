<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import { VueFlow, useVueFlow, Panel } from '@vue-flow/core'
import { Background } from '@vue-flow/background'
import DatabaseTableNode from './DatabaseTableNode.vue'
import RelationConfigPopover from './RelationConfigPopover.vue'
import ContextMenu from './ContextMenu.vue'
import Icon from './Icon.vue'
import ELK from 'elkjs/lib/elk.bundled.js'
import { useViewsStore } from '@/stores/views'

const elk = new ELK()
const viewsStore = useViewsStore()

const showRelationPopover = ref(false)
const relationPopoverPosition = ref({ x: 0, y: 0 })
const pendingConnection = ref({ source: '', target: '', sourceHandle: null, targetHandle: null })

const showContextMenu = ref(false)
const contextMenuPosition = ref({ x: 0, y: 0 })
const contextMenuItems = ref([])
const contextMenuTarget = ref(null)

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
  isPlaygroundMode: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['entity-click', 'entity-hover', 'entity-double-click', 'toggle-fullscreen', 'context-action'])

const nodeTypes = {
  databaseTable: DatabaseTableNode
}

const { fitView, zoomIn, zoomOut, getViewport, setViewport, getNodes, updateNode, addEdges, getEdges, getSelectedEdges, getSelectedNodes } = useVueFlow()

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

const nodes = ref([])
const currentEntitiesKey = ref('')
const isCalculating = ref(false)
const performanceMode = ref(false)
const detailLevel = ref('full')

const shouldShowLabels = computed(() => {
  return detailLevel.value === 'full'
})

const edges = computed(() => {
  // Don't render edges if nodes aren't ready yet
  if (nodes.value.length === 0) {
    return []
  }

  const showLabels = shouldShowLabels.value
  const nodeIds = new Set(nodes.value.map(n => n.id))

  return props.relations
    .filter(relation => {
      const sourceId = relation.from.fqcn || relation.from.name
      const targetId = relation.to.fqcn || relation.to.name
      // Only create edge if both nodes exist
      return nodeIds.has(sourceId) && nodeIds.has(targetId)
    })
    .map((relation, index) => {
      const sourceId = relation.from.fqcn || relation.from.name
      const targetId = relation.to.fqcn || relation.to.name
      const isVirtual = relation.isVirtual || false
      const edgeColor = isVirtual ? '#9333ea' : getEdgeColor(relation.type)

      const edge = {
        id: `edge-${index}-${sourceId}-${targetId}`,
        source: sourceId,
        target: targetId,
        sourceHandle: relation.sourceHandle || 'bottom',
        targetHandle: relation.targetHandle || 'top',
        type: 'smoothstep',
        animated: isVirtual,
        style: {
          stroke: edgeColor,
          strokeWidth: isVirtual ? 3 : (relation.isOwning ? 2.5 : 2),
          strokeDasharray: isVirtual ? '8,4' : (relation.isOwning ? '0' : '5,5'),
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
          isVirtual,
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

    // Include entity content hash/version for playground mode to detect updates
    const entitiesSignature = props.isPlaygroundMode
      ? props.entities.map(e => {
          const fqcn = e.fqcn || e.name
          const fieldsHash = (e.fields || []).map(f => `${f.name}:${f.type}`).join('|')
          return `${fqcn}:${e.name}:${fieldsHash}`
        }).sort().join(',')
      : props.entities.map(e => e.fqcn || e.name).sort().join(',')

    const needsFullRecreation = entitiesSignature !== currentEntitiesKey.value || nodes.value.length === 0

    currentEntitiesKey.value = entitiesSignature

    if (needsFullRecreation) {
      // Full recreation when entities change
      nodes.value = props.entities.map((entity) => {
        const elkNode = layoutedGraph.children?.find(n => n.id === (entity.fqcn || entity.name))
        const entityFqcn = entity.fqcn || entity.name

        // Check if we have a saved position for this entity
        const savedPosition = viewsStore.currentLayout.nodes.get(entityFqcn)

        return {
          id: entityFqcn,
          type: 'databaseTable',
          position: savedPosition || {
            x: elkNode?.x ?? 0,
            y: elkNode?.y ?? 0,
          },
          data: {
            name: entity.name,
            table: entity.table,
            fields: entity.fields || [],
            namespace: extractNamespace(entityFqcn),
            entity: entity,
            isVirtual: entity.isVirtual || false,
            isPlaygroundMode: props.isPlaygroundMode,
          },
          draggable: true,
          selectable: props.isPlaygroundMode,
          connectable: props.isPlaygroundMode,
          width: 280,
          height: 80 + Math.min((entity.fields || []).length, 8) * 22,
        }
      })
    } else {
      // Just update positions if entities are the same (e.g., when loading a view)
      // Only update if we have saved positions, otherwise keep current positions
      if (viewsStore.currentLayout.nodes.size > 0) {
        nodes.value.forEach(node => {
          const savedPosition = viewsStore.currentLayout.nodes.get(node.id)
          if (savedPosition && typeof savedPosition.x === 'number' && typeof savedPosition.y === 'number') {
            // Mutate existing position object instead of replacing it
            node.position.x = savedPosition.x
            node.position.y = savedPosition.y
          }
        })
      }

      // Update node data (isPlaygroundMode, selectable and connectable)
      nodes.value.forEach(node => {
        node.data.isPlaygroundMode = props.isPlaygroundMode
        node.selectable = props.isPlaygroundMode
        node.connectable = props.isPlaygroundMode
      })

      // Restore viewport when switching views
      if (viewsStore.currentLayout.viewport) {
        const vp = viewsStore.currentLayout.viewport
        if (typeof vp.x === 'number' && typeof vp.y === 'number' && typeof viewsStore.currentLayout.zoom === 'number') {
          setTimeout(() => {
            setViewport({
              x: vp.x,
              y: vp.y,
              zoom: viewsStore.currentLayout.zoom
            })
          }, 50)
        }
      }
    }

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

watch([() => props.entities, () => props.relations, () => props.isPlaygroundMode], () => {
  calculateLayout()
}, { immediate: true, deep: true })

// Watch for view changes and restore viewport
watch(() => viewsStore.currentViewId, (newViewId) => {
  if (newViewId && viewsStore.currentLayout.viewport) {
    const vp = viewsStore.currentLayout.viewport
    // Only restore viewport if values are valid
    if (typeof vp.x === 'number' && typeof vp.y === 'number' && typeof viewsStore.currentLayout.zoom === 'number') {
      setTimeout(() => {
        setViewport({
          x: vp.x,
          y: vp.y,
          zoom: viewsStore.currentLayout.zoom
        })
      }, 100)
    }
  }
}, { immediate: false })

function onNodeClick(event) {
  if (!props.isPlaygroundMode) {
    emit('entity-click', event.node.data.entity)
  }
}

function onNodeDoubleClick(event) {
  emit('entity-double-click', event.node.data.entity)
}

function onNodeMouseEnter(event) {
  emit('entity-hover', event.node.data.entity)
}

function onConnect(connection) {
  console.log('🔗 onConnect called!', connection)
  const sourceEntity = props.entities.find(e => (e.fqcn || e.name) === connection.source)
  const targetEntity = props.entities.find(e => (e.fqcn || e.name) === connection.target)

  if (!sourceEntity || !targetEntity) {
    return
  }

  pendingConnection.value = {
    source: connection.source,
    target: connection.target,
    sourceHandle: connection.sourceHandle,
    targetHandle: connection.targetHandle,
    sourceName: sourceEntity.name,
    targetName: targetEntity.name
  }

  console.log('📍 Connection handles:', {
    sourceHandle: connection.sourceHandle,
    targetHandle: connection.targetHandle
  })

  relationPopoverPosition.value = {
    x: window.innerWidth / 2 - 160,
    y: window.innerHeight / 2 - 150
  }

  showRelationPopover.value = true
}

function handleCreateRelation(config) {
  viewsStore.addVirtualRelation({
    source: pendingConnection.value.source,
    target: pendingConnection.value.target,
    sourceHandle: pendingConnection.value.sourceHandle,
    targetHandle: pendingConnection.value.targetHandle,
    type: config.type,
    field: config.field,
    isOwning: config.isOwning,
    isVirtual: true
  })

  showRelationPopover.value = false
  pendingConnection.value = { source: '', target: '', sourceHandle: null, targetHandle: null }
}

function handleCancelRelation() {
  showRelationPopover.value = false
  pendingConnection.value = { source: '', target: '', sourceHandle: null, targetHandle: null }
}

function isValidConnection(connection) {
  console.log('🔍 isValidConnection called:', connection)
  // Simple validation: source and target must be different
  const isValid = connection.source !== connection.target
  console.log('✅ Valid?', isValid)
  return isValid
}

function onConnectStart(params) {
  console.log('🚀 onConnectStart:', params)
  console.log('📌 Starting from handle:', params.handleId, 'on node:', params.nodeId)
}

function onConnectEnd(event) {
  console.log('🏁 onConnectEnd:', event)
}

function handleKeyDown(event) {
  if (!props.isPlaygroundMode) return

  // Delete or Backspace key
  if (event.key === 'Delete' || event.key === 'Backspace') {
    // Don't interfere with typing in input fields, textareas, or contenteditable elements
    const target = event.target
    if (
      target.tagName === 'INPUT' ||
      target.tagName === 'TEXTAREA' ||
      target.isContentEditable ||
      target.getAttribute('contenteditable') === 'true'
    ) {
      return
    }

    event.preventDefault()
    event.stopPropagation()

    const selectedNodes = getSelectedNodes.value
    const selectedEdges = getSelectedEdges.value

    if (selectedNodes && selectedNodes.length > 0) {
      selectedNodes.forEach(node => {
        viewsStore.removeVirtualEntity(node.id)
      })
    }

    if (selectedEdges && selectedEdges.length > 0) {
      selectedEdges.forEach(edge => {
        viewsStore.removeVirtualRelation(edge.source, edge.target)
      })
    }
  }
}

function handlePaneContextMenu(event) {
  if (!props.isPlaygroundMode) return

  event.preventDefault()

  contextMenuTarget.value = { type: 'canvas' }
  contextMenuPosition.value = { x: event.clientX, y: event.clientY }
  contextMenuItems.value = [
    { icon: 'plus-circle', label: 'Add Entity', action: 'add-entity' },
    { type: 'separator' },
    { icon: 'photo', label: 'Export PNG', action: 'export-png' },
    { icon: 'arrow-down-tray', label: 'Export SVG', action: 'export-svg' },
    { type: 'separator' },
    { icon: 'arrows-pointing-out', label: 'Fit View', action: 'fit-view' },
    { icon: 'arrows-pointing-out', label: 'Fullscreen Mode', action: 'fullscreen', shortcut: 'F' }
  ]
  showContextMenu.value = true
}

function handleNodeContextMenu({ event, node }) {
  if (!props.isPlaygroundMode) return

  event.preventDefault()

  contextMenuTarget.value = { type: 'node', data: node }
  contextMenuPosition.value = { x: event.clientX, y: event.clientY }

  const items = []

  // Edit is only for virtual entities
  if (node.data.isVirtual) {
    items.push({ icon: 'pencil-square', label: 'Edit Entity', action: 'edit-entity' })
  }

  items.push({ icon: 'document-duplicate', label: 'Copy Entity', action: 'copy-entity', shortcut: 'Ctrl+C', disabled: true })

  // Always allow hiding/deleting entities
  items.push({ type: 'separator' })
  items.push({
    icon: 'trash',
    label: node.data.isVirtual ? 'Delete Entity' : 'Hide Entity',
    action: 'delete-entity',
    danger: true,
    shortcut: 'Del'
  })

  contextMenuItems.value = items
  showContextMenu.value = true
}

function handleEdgeContextMenu({ event, edge }) {
  if (!props.isPlaygroundMode) return

  event.preventDefault()

  contextMenuTarget.value = { type: 'edge', data: edge }
  contextMenuPosition.value = { x: event.clientX, y: event.clientY }

  const items = []

  // Allow editing virtual relations only, but allow hiding any relation
  if (edge.data?.isVirtual) {
    items.push({ icon: 'pencil-square', label: 'Edit Relation', action: 'edit-relation' })
    items.push({ type: 'separator' })
  }

  items.push({
    icon: 'trash',
    label: edge.data?.isVirtual ? 'Delete Relation' : 'Hide Relation',
    action: 'delete-relation',
    danger: true,
    shortcut: 'Del'
  })

  contextMenuItems.value = items
  showContextMenu.value = true
}

function handleContextMenuAction(item) {
  const target = contextMenuTarget.value

  switch (item.action) {
    case 'add-entity':
      emit('context-action', 'add-entity')
      break

    case 'edit-entity':
      emit('context-action', 'edit-entity', target.data)
      break

    case 'copy-entity':
      console.log('TODO: Copy entity', target.data)
      break

    case 'delete-entity':
      // Always allow hiding/deleting entities in playground mode
      viewsStore.removeVirtualEntity(target.data.id)
      break

    case 'delete-relation':
      // Always allow hiding/deleting relations in playground mode
      viewsStore.removeVirtualRelation(target.data.source, target.data.target)
      break

    case 'edit-relation':
      console.log('TODO: Edit relation modal', target.data)
      break

    case 'export-png':
      exportPNG()
      break

    case 'export-svg':
      exportSVG()
      break

    case 'fit-view':
      handleFitView()
      break

    case 'fullscreen':
      emit('toggle-fullscreen')
      break
  }

  showContextMenu.value = false
}

function closeContextMenu() {
  showContextMenu.value = false
  contextMenuTarget.value = null
}

onMounted(() => {
  window.addEventListener('keydown', handleKeyDown)
})

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown)
})

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

function onNodeDragStop(event) {
  console.log('🎯 onNodeDragStop called', event.node.id)
  const entityFqcn = event.node.id
  const { x, y } = event.node.position

  // Always update the current layout (even without active view)
  viewsStore.updateNodePosition(entityFqcn, x, y)
}

function onMoveEnd(event) {
  const viewport = getViewport()

  // Always update the current layout viewport (even without active view)
  viewsStore.updateViewport(viewport.zoom, viewport.x, viewport.y)
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
      v-model:nodes="nodes"
      :edges="edges"
      :node-types="nodeTypes"
      :default-viewport="{ zoom: 1, x: 0, y: 0 }"
      :min-zoom="0.1"
      :max-zoom="4"
      :snap-to-grid="false"
      :zoom-on-scroll="true"
      :pan-on-scroll="false"
      :zoom-on-double-click="false"
      :nodes-connectable="true"
      :nodes-draggable="true"
      :elements-selectable="props.isPlaygroundMode"
      :connect-on-click="false"
      :delete-key-code="null"
      :is-valid-connection="isValidConnection"
      @node-click="onNodeClick"
      @node-double-click="onNodeDoubleClick"
      @node-drag-stop="onNodeDragStop"
      @node-context-menu="handleNodeContextMenu"
      @edge-context-menu="handleEdgeContextMenu"
      @pane-context-menu="handlePaneContextMenu"
      @move-end="onMoveEnd"
      @connect="onConnect"
      @connect-start="onConnectStart"
      @connect-end="onConnectEnd"
      class="w-full h-full basic-flow"
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

    <!-- Relation Config Popover -->
    <RelationConfigPopover
      :is-open="showRelationPopover"
      :position="relationPopoverPosition"
      :source-entity="pendingConnection.sourceName"
      :target-entity="pendingConnection.targetName"
      @create="handleCreateRelation"
      @cancel="handleCancelRelation"
    />

    <!-- Context Menu -->
    <ContextMenu
      :is-open="showContextMenu"
      :position="contextMenuPosition"
      :items="contextMenuItems"
      @close="closeContextMenu"
      @item-click="handleContextMenuAction"
    />
  </div>
</template>

<style>
@import '@vue-flow/core/dist/style.css';
@import '@vue-flow/core/dist/theme-default.css';
</style>

<style scoped>

/* Show connection line during drag */
:deep(.vue-flow__connectionline) {
  stroke: #9333ea;
  stroke-width: 2;
  stroke-dasharray: 5,5;
  animation: dash 0.5s linear infinite;
}

@keyframes dash {
  to {
    stroke-dashoffset: -10;
  }
}

/* Selected edges styling in playground mode */
:deep(.vue-flow__edge.selected) {
  z-index: 1001 !important;
}

:deep(.vue-flow__edge.selected path) {
  stroke-width: 4 !important;
  filter: drop-shadow(0 0 8px currentColor);
}

:deep(.vue-flow__edge.selectable) {
  cursor: pointer;
}

:deep(.vue-flow__edge.selectable:hover path) {
  stroke-width: 3.5 !important;
}
</style>
