<script setup>
import { ref, onMounted, watch, nextTick } from 'vue'
import * as d3 from 'd3'
import Icon from './Icon.vue'

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

const emit = defineEmits(['entity-click', 'entity-hover'])

const svgRef = ref(null)
const containerRef = ref(null)
const simulation = ref(null)
const transform = ref({ x: 0, y: 0, k: 1 })
const showMinimap = ref(true)
const groupByNamespace = ref(true)

// Performance: Debounce helper
function debounce(fn, delay) {
  let timeoutId
  return function(...args) {
    clearTimeout(timeoutId)
    timeoutId = setTimeout(() => fn.apply(this, args), delay)
  }
}

// D3 selections
let svg, g, link, node, nodeGroup, linkGroup, zoom

onMounted(() => {
  initGraph()

  // Performance: Debounce graph updates to avoid too many re-renders
  const debouncedUpdate = debounce(() => {
    updateGraph()
  }, 100)

  watch(() => props.entities, () => {
    debouncedUpdate()
  }, { deep: false }) // Shallow watch for better performance

  watch(() => props.focusedEntity, (newFocus) => {
    handleFocusChange(newFocus)
  })
})

function initGraph() {
  // Setup SVG
  svg = d3.select(svgRef.value)
  const width = containerRef.value.clientWidth
  const height = containerRef.value.clientHeight

  // Clear previous content
  svg.selectAll('*').remove()

  // Add zoom behavior
  zoom = d3.zoom()
    .scaleExtent([0.1, 4])
    .on('zoom', (event) => {
      g.attr('transform', event.transform)
      transform.value = event.transform
    })

  svg.call(zoom)

  // Main group for zoom/pan
  g = svg.append('g')

  // Add groups for links and nodes
  linkGroup = g.append('g').attr('class', 'links')
  nodeGroup = g.append('g').attr('class', 'nodes')

  // Initialize force simulation (disabled for now, using grid layout)
  simulation.value = d3.forceSimulation()
    .force('link', null)
    .force('charge', null)
    .force('center', null)
    .force('collision', null)
    .force('x', null)
    .force('y', null)
    .stop()

  updateGraph()
}

function updateGraph() {
  if (!simulation.value) return

  // Performance: Clear connection cache when graph updates
  connectionCache.clear()

  const width = containerRef.value.clientWidth
  const height = containerRef.value.clientHeight

  // Prepare nodes data with fixed grid positions
  const nodes = props.entities.map((entity, index) => {
    const cols = Math.ceil(Math.sqrt(props.entities.length))
    const col = index % cols
    const row = Math.floor(index / cols)
    const spacingX = 300
    const spacingY = 350

    return {
      id: entity.fqcn || entity.name,
      name: entity.name,
      table: entity.table,
      fields: entity.fields || [],
      namespace: extractNamespace(entity.fqcn || entity.name),
      x: col * spacingX + 200,
      y: row * spacingY + 200,
      fx: col * spacingX + 200, // Fixed x position
      fy: row * spacingY + 200, // Fixed y position
      ...entity,
    }
  })

  // Prepare links data
  const links = props.relations.map(rel => ({
    source: rel.from.fqcn || rel.from.name,
    target: rel.to.fqcn || rel.to.name,
    field: rel.field,
    type: rel.type,
    isOwning: rel.isOwning,
  }))

  // Update simulation with nodes (no forces applied)
  simulation.value.nodes(nodes)
  simulation.value.on('tick', ticked)

  // Draw links - need to manually calculate positions since we're not using link force
  link = linkGroup
    .selectAll('line')
    .data(links)
    .join('line')
    .attr('class', d => `link link-type-${d.type}`)
    .attr('stroke', d => getLinkColor(d.type))
    .attr('stroke-width', d => d.isOwning ? 2 : 1)
    .attr('stroke-dasharray', d => d.isOwning ? '0' : '5,5')
    .attr('opacity', 0.6)
    .attr('marker-end', d => `url(#arrow-${d.type})`)
    .attr('x1', d => {
      const sourceNode = nodes.find(n => n.id === d.source)
      return sourceNode ? sourceNode.x : 0
    })
    .attr('y1', d => {
      // Start from bottom of source card
      const sourceNode = nodes.find(n => n.id === d.source)
      if (!sourceNode) return 0
      // Performance: Max 5 fields
      const fieldsToShow = Math.min(sourceNode.fields.length, 5)
      const cardHeight = 80 + fieldsToShow * 22
      return sourceNode.y + cardHeight - 40 // Bottom of card
    })
    .attr('x2', d => {
      const targetNode = nodes.find(n => n.id === d.target)
      return targetNode ? targetNode.x : 0
    })
    .attr('y2', d => {
      // Arrive at top of target card
      const targetNode = nodes.find(n => n.id === d.target)
      return targetNode ? targetNode.y - 40 : 0 // Top of card
    })

  // Draw nodes
  const nodeElements = nodeGroup
    .selectAll('g.node')
    .data(nodes, d => d.id)
    .join('g')
    .attr('class', 'node')
    .attr('transform', d => `translate(${d.x},${d.y})`)
    .on('click', (event, d) => {
      event.stopPropagation()
      emit('entity-click', d)
    })
    .on('mouseenter', (event, d) => {
      emit('entity-hover', d)
      highlightConnections(d)
    })
    .on('mouseleave', () => {
      resetHighlights()
    })

  // Node background
  nodeElements
    .selectAll('rect.node-bg')
    .data(d => [d])
    .join('rect')
    .attr('class', 'node-bg')
    .attr('width', 220)
    .attr('height', d => {
      // Performance: Show max 5 fields
      const fieldsToShow = Math.min(d.fields.length, 5)
      return 80 + fieldsToShow * 22
    })
    .attr('x', -110)
    .attr('y', -40)
    .attr('rx', 8)
    .attr('fill', '#ffffff')
    .attr('stroke', d => getNamespaceColor(d.namespace))
    .attr('stroke-width', 2)
    .style('filter', 'drop-shadow(0 4px 6px rgba(0,0,0,0.1))')

  // Node header background
  nodeElements
    .selectAll('rect.node-header')
    .data(d => [d])
    .join('rect')
    .attr('class', 'node-header')
    .attr('width', 220)
    .attr('height', 50)
    .attr('x', -110)
    .attr('y', -40)
    .attr('rx', 8)
    .attr('fill', d => getNamespaceColor(d.namespace))

  // Node title
  nodeElements
    .selectAll('text.node-title')
    .data(d => [d])
    .join('text')
    .attr('class', 'node-title')
    .attr('text-anchor', 'middle')
    .attr('y', -20)
    .attr('fill', '#ffffff')
    .attr('font-weight', 'bold')
    .attr('font-size', '14px')
    .text(d => d.name)

  // Node table name
  nodeElements
    .selectAll('text.node-table')
    .data(d => [d])
    .join('text')
    .attr('class', 'node-table')
    .attr('text-anchor', 'middle')
    .attr('y', -2)
    .attr('fill', '#ffffff')
    .attr('fill-opacity', 0.8)
    .attr('font-size', '11px')
    .text(d => d.table || '')

  // Fields list - OPTIMIZED: Reduce initial field display for performance
  nodeElements.each(function(d) {
    const node = d3.select(this)

    // Performance: Show fewer fields initially (max 5 instead of 8)
    const fieldsToShow = Math.min(d.fields.length, 5)
    const fields = d.fields.slice(0, fieldsToShow)

    // Remove old fields
    node.selectAll('g.field-group').remove()

    // Add field groups (with 30px spacing from header instead of 20px)
    const fieldGroups = node
      .selectAll('g.field-group')
      .data(fields)
      .join('g')
      .attr('class', 'field-group')
      .attr('transform', (field, i) => `translate(-100, ${30 + i * 22})`)

    // Field name
    fieldGroups
      .append('text')
      .attr('class', 'field-name')
      .attr('x', 10)
      .attr('y', 0)
      .attr('fill', '#374151')
      .attr('font-size', '11px')
      .attr('font-weight', '500')
      .text(field => field.name)

    // Field type
    fieldGroups
      .append('text')
      .attr('class', 'field-type')
      .attr('x', 200)
      .attr('y', 0)
      .attr('text-anchor', 'end')
      .attr('fill', '#9ca3af')
      .attr('font-size', '10px')
      .text(field => field.type)

    // More fields indicator
    if (d.fields.length > fieldsToShow) {
      node
        .selectAll('text.more-fields')
        .data([d])
        .join('text')
        .attr('class', 'more-fields')
        .attr('text-anchor', 'middle')
        .attr('y', 30 + fieldsToShow * 22 + 12)
        .attr('fill', '#9ca3af')
        .attr('font-size', '10px')
        .attr('font-style', 'italic')
        .text(`+${d.fields.length - fieldsToShow} more...`)
    }
  })

  // Add arrow markers
  svg.selectAll('defs').remove()
  const defs = svg.append('defs')

  const relationTypes = [
    { type: 1, color: '#10b981' }, // OneToOne - Green
    { type: 2, color: '#3b82f6' }, // ManyToOne - Blue
    { type: 4, color: '#f59e0b' }, // OneToMany - Orange
    { type: 8, color: '#ef4444' }, // ManyToMany - Red
  ]

  relationTypes.forEach(({ type, color }) => {
    defs.append('marker')
      .attr('id', `arrow-${type}`)
      .attr('viewBox', '0 -5 10 10')
      .attr('refX', 115)
      .attr('refY', 0)
      .attr('markerWidth', 8)
      .attr('markerHeight', 8)
      .attr('orient', 'auto')
      .append('path')
      .attr('d', 'M0,-5L10,0L0,5')
      .attr('fill', color)
  })

  node = nodeElements

  // No need to restart simulation since positions are fixed
  // Just trigger one tick to position everything
  ticked()
}

function ticked() {
  // Positions are now fixed, no need to update on tick
  // This function is kept for compatibility but does nothing
}

// Drag disabled for fixed layout
// function drag(simulation) { ... }

// Performance: Cache connected nodes to avoid recalculation
let connectionCache = new Map()

function highlightConnections(selectedNode) {
  const cacheKey = selectedNode.id

  // Check cache first
  if (!connectionCache.has(cacheKey)) {
    const connectedIds = new Set()
    connectedIds.add(selectedNode.id)

    // First pass: identify all connected nodes
    link.each(d => {
      const sourceId = typeof d.source === 'object' ? d.source.id : d.source
      const targetId = typeof d.target === 'object' ? d.target.id : d.target

      if (sourceId === selectedNode.id || targetId === selectedNode.id) {
        connectedIds.add(sourceId)
        connectedIds.add(targetId)
      }
    })

    connectionCache.set(cacheKey, connectedIds)
  }

  const connectedIds = connectionCache.get(cacheKey)

  // Update links: reduce opacity for non-connected, keep normal for connected
  link
    .attr('opacity', d => {
      const sourceId = typeof d.source === 'object' ? d.source.id : d.source
      const targetId = typeof d.target === 'object' ? d.target.id : d.target
      const isConnected = sourceId === selectedNode.id || targetId === selectedNode.id
      return isConnected ? 0.8 : 0.15
    })
    .attr('stroke-width', d => {
      const sourceId = typeof d.source === 'object' ? d.source.id : d.source
      const targetId = typeof d.target === 'object' ? d.target.id : d.target
      const isConnected = sourceId === selectedNode.id || targetId === selectedNode.id
      return isConnected ? 3 : 1
    })

  // Update nodes: reduce opacity for non-connected
  node
    .selectAll('rect.node-bg')
    .attr('opacity', d => connectedIds.has(d.id) ? 1 : 0.2)
    .attr('stroke-width', d => d.id === selectedNode.id ? 4 : 2)

  node
    .selectAll('rect.node-header')
    .attr('opacity', d => connectedIds.has(d.id) ? 1 : 0.3)

  // Reduce opacity for field groups in non-connected nodes
  node
    .selectAll('g.field-group')
    .attr('opacity', d => {
      // Get parent node data
      const parentNode = node.filter(function(n) {
        return d3.select(this).selectAll('g.field-group').data().includes(d)
      }).datum()
      return parentNode && connectedIds.has(parentNode.id) ? 1 : 0.3
    })
}

function resetHighlights() {
  link
    .attr('opacity', 0.6)
    .attr('stroke-width', d => d.isOwning ? 2 : 1)

  node
    .selectAll('rect.node-bg')
    .attr('opacity', 1)
    .attr('stroke-width', 2)

  node
    .selectAll('text')
    .attr('opacity', 1)

  node
    .selectAll('rect.node-header')
    .attr('opacity', 1)

  node
    .selectAll('g.field-group')
    .attr('opacity', 1)
}

function handleFocusChange(focusedEntityId) {
  if (!focusedEntityId) {
    resetHighlights()
    return
  }

  const focusedNode = props.entities.find(e => e.fqcn === focusedEntityId || e.name === focusedEntityId)
  if (focusedNode) {
    highlightConnections(focusedNode)
    centerOnNode(focusedNode)
  }
}

function centerOnNode(node) {
  const width = containerRef.value.clientWidth
  const height = containerRef.value.clientHeight

  const scale = 1.5
  const x = -node.x * scale + width / 2
  const y = -node.y * scale + height / 2

  svg
    .transition()
    .duration(750)
    .call(zoom.transform, d3.zoomIdentity.translate(x, y).scale(scale))
}

function resetZoom() {
  svg
    .transition()
    .duration(750)
    .call(zoom.transform, d3.zoomIdentity)
}

function fitToView() {
  if (!g || !containerRef.value) return

  const bounds = g.node().getBBox()
  const width = containerRef.value.clientWidth
  const height = containerRef.value.clientHeight

  // If bounds are empty or invalid, skip
  if (bounds.width === 0 || bounds.height === 0) return

  const dx = bounds.width
  const dy = bounds.height
  const x = bounds.x + bounds.width / 2
  const y = bounds.y + bounds.height / 2

  // Adaptive zoom based on number of entities
  const entityCount = props.entities.length
  let targetScale

  if (entityCount === 1) {
    // Single table: zoom to 1.5x (very close)
    targetScale = 1.5
  } else if (entityCount === 2) {
    // 2 tables: zoom to 1.2x
    targetScale = 1.2
  } else if (entityCount <= 4) {
    // 3-4 tables: zoom to 1.0x
    targetScale = 1.0
  } else if (entityCount <= 8) {
    // 5-8 tables: fit with 85% padding
    targetScale = Math.min(width / dx, height / dy) * 0.85
  } else {
    // Many tables: fit with 90% padding
    targetScale = Math.min(width / dx, height / dy) * 0.9
  }

  // For small numbers of entities, ensure we don't zoom out too much
  if (entityCount <= 4) {
    const fitScale = Math.min(width / dx, height / dy) * 0.7
    targetScale = Math.min(targetScale, fitScale)
  }

  const translate = [width / 2 - targetScale * x, height / 2 - targetScale * y]

  svg
    .transition()
    .duration(750)
    .call(zoom.transform, d3.zoomIdentity.translate(translate[0], translate[1]).scale(targetScale))
}

function exportSVG() {
  const svgData = svgRef.value.outerHTML
  const blob = new Blob([svgData], { type: 'image/svg+xml' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = 'schema-graph.svg'
  link.click()
  URL.revokeObjectURL(url)
}

function exportPNG() {
  const svgData = new XMLSerializer().serializeToString(svgRef.value)
  const canvas = document.createElement('canvas')
  const ctx = canvas.getContext('2d')
  const img = new Image()

  canvas.width = containerRef.value.clientWidth * 2
  canvas.height = containerRef.value.clientHeight * 2

  img.onload = () => {
    ctx.fillStyle = '#ffffff'
    ctx.fillRect(0, 0, canvas.width, canvas.height)
    ctx.drawImage(img, 0, 0)

    canvas.toBlob(blob => {
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = 'schema-graph.png'
      link.click()
      URL.revokeObjectURL(url)
    })
  }

  img.src = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgData)))
}

function getLinkColor(type) {
  const colors = {
    1: '#10b981', // OneToOne - Green
    2: '#3b82f6', // ManyToOne - Blue
    4: '#f59e0b', // OneToMany - Orange
    8: '#ef4444', // ManyToMany - Red
  }
  return colors[type] || '#6b7280'
}

function getNamespaceColor(namespace) {
  const colors = [
    '#667eea', // Purple
    '#3b82f6', // Blue
    '#10b981', // Green
    '#f59e0b', // Orange
    '#06b6d4', // Cyan
    '#8b5cf6', // Violet
    '#ec4899', // Pink
    '#14b8a6'  // Teal
  ]
  const hash = namespace.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  return colors[hash % colors.length]
}

function extractNamespace(fqcn) {
  const parts = fqcn.split('\\')
  return parts.slice(0, -1).join('\\') || 'Default'
}

defineExpose({
  resetZoom,
  fitToView,
  exportSVG,
  exportPNG,
})
</script>

<template>
  <div class="schema-graph-container" ref="containerRef">
    <svg ref="svgRef" class="schema-graph-svg"></svg>

    <!-- Controls -->
    <div class="graph-controls">
      <button @click="fitToView" class="control-btn hover-lift" title="Fit to view">
        <Icon name="arrows-pointing-out" :size="20" />
      </button>
      <button @click="resetZoom" class="control-btn hover-lift" title="Reset zoom">
        <Icon name="arrow-path" :size="20" />
      </button>
      <button @click="showMinimap = !showMinimap" class="control-btn hover-lift" title="Toggle minimap">
        <Icon name="map" :size="20" />
      </button>
      <button @click="exportSVG" class="control-btn hover-lift" title="Export SVG">
        <Icon name="arrow-down-tray" :size="20" />
      </button>
      <button @click="exportPNG" class="control-btn hover-lift" title="Export PNG">
        <Icon name="photo" :size="20" />
      </button>
    </div>

    <!-- Legend -->
    <div class="graph-legend">
      <div class="legend-title">Relations</div>
      <div class="legend-item">
        <div class="legend-line" style="background: #10b981"></div>
        <span>One to One (1)</span>
      </div>
      <div class="legend-item">
        <div class="legend-line" style="background: #3b82f6"></div>
        <span>Many to One (2)</span>
      </div>
      <div class="legend-item">
        <div class="legend-line" style="background: #f59e0b"></div>
        <span>One to Many (4)</span>
      </div>
      <div class="legend-item">
        <div class="legend-line" style="background: #ef4444"></div>
        <span>Many to Many (8)</span>
      </div>
    </div>

    <!-- Minimap -->
    <div v-if="showMinimap" class="minimap">
      <svg class="minimap-svg" width="200" height="150"></svg>
    </div>
  </div>
</template>

<style scoped>
.schema-graph-container {
  position: relative;
  width: 100%;
  height: 100%;
  overflow: hidden;
  background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
}

.schema-graph-svg {
  width: 100%;
  height: 100%;
  cursor: grab;
}

.schema-graph-svg:active {
  cursor: grabbing;
}

.graph-controls {
  position: absolute;
  top: var(--spacing-4);
  right: var(--spacing-4);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
  z-index: 10;
}

.control-btn {
  width: 40px;
  height: 40px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all var(--transition-base);
  box-shadow: var(--shadow-sm);
}

.control-btn:hover {
  background: var(--color-gray-50);
  border-color: var(--color-primary-500);
  color: var(--color-primary-500);
  box-shadow: var(--shadow-md);
}

.graph-legend {
  position: absolute;
  bottom: var(--spacing-4);
  left: var(--spacing-4);
  background: white;
  padding: var(--spacing-4);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-md);
  border: 1px solid var(--color-gray-200);
  z-index: 10;
}

.legend-title {
  font-weight: 600;
  font-size: var(--text-sm);
  margin-bottom: var(--spacing-2);
  color: var(--color-gray-900);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-1);
  font-size: var(--text-xs);
  color: var(--color-gray-600);
}

.legend-line {
  width: 24px;
  height: 2px;
  border-radius: 2px;
}

.minimap {
  position: absolute;
  bottom: var(--spacing-4);
  right: var(--spacing-4);
  background: white;
  border: 2px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  z-index: 10;
}

.minimap-svg {
  display: block;
  background: var(--color-gray-50);
}

/* Node styles are handled by D3, but we can add some global SVG styles */
:deep(.node) {
  cursor: pointer;
  transition: all 0.2s ease;
}

:deep(.node:hover rect.node-bg) {
  filter: drop-shadow(0 8px 12px rgba(0,0,0,0.15)) !important;
}

:deep(.link) {
  transition: all 0.2s ease;
}
</style>
