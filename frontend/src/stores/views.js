import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { useSchemaStore } from './schema'

/**
 * Views Store - Custom Views & Perspectives
 *
 * Allows users to:
 * - Save custom schema layouts (node positions, zoom, selected entities)
 * - Create filtered views by bounded contexts or namespaces
 * - Quick-switch between different perspectives
 * - Persist views in localStorage
 */
export const useViewsStore = defineStore('views', () => {
  const schemaStore = useSchemaStore()

  // State
  const savedViews = ref(new Map())
  const currentViewId = ref(null)
  const autoSaveEnabled = ref(true)
  const playgroundModificationCounter = ref(0) // Force reactivity trigger

  // Current view state
  const currentLayout = ref({
    nodes: new Map(), // entityFqcn -> { x, y }
    zoom: 1,
    viewport: { x: 0, y: 0 }
  })

  const currentFilter = ref({
    boundedContexts: [], // Array of namespace prefixes to show
    entityTypes: [], // Filter by specific patterns (e.g., "Repository", "Entity")
    excludeEntities: new Set(), // Specific entities to hide
    showOrphans: true // Show entities with no relations
  })

  // Computed
  const currentView = computed(() => {
    if (!currentViewId.value) return null
    return savedViews.value.get(currentViewId.value)
  })

  const hasUnsavedChanges = computed(() => {
    if (!currentView.value) return false

    // Check if current layout differs from saved view
    const saved = currentView.value.layout
    if (saved.zoom !== currentLayout.value.zoom) return true
    if (saved.viewport.x !== currentLayout.value.viewport.x) return true
    if (saved.viewport.y !== currentLayout.value.viewport.y) return true

    // Check if node positions changed
    for (const [fqcn, pos] of currentLayout.value.nodes) {
      const savedPos = saved.nodes.get(fqcn)
      if (!savedPos || savedPos.x !== pos.x || savedPos.y !== pos.y) {
        return true
      }
    }

    return false
  })

  const filteredEntities = computed(() => {
    let entities = schemaStore.entities

    // Apply bounded context filter
    if (currentFilter.value.boundedContexts.length > 0) {
      entities = entities.filter((entity) => {
        const fqcn = entity.fqcn || entity.name
        return currentFilter.value.boundedContexts.some((context) =>
          fqcn.startsWith(context)
        )
      })
    }

    // Apply entity type filter
    if (currentFilter.value.entityTypes.length > 0) {
      entities = entities.filter((entity) => {
        const name = entity.name
        return currentFilter.value.entityTypes.some((type) =>
          name.includes(type)
        )
      })
    }

    // Apply exclusions
    if (currentFilter.value.excludeEntities.size > 0) {
      entities = entities.filter((entity) => {
        const fqcn = entity.fqcn || entity.name
        return !currentFilter.value.excludeEntities.has(fqcn)
      })
    }

    // Filter orphans if needed
    if (!currentFilter.value.showOrphans) {
      entities = entities.filter((entity) => {
        return (entity.relations && entity.relations.length > 0) ||
               hasIncomingRelations(entity)
      })
    }

    return entities
  })

  const availableBoundedContexts = computed(() => {
    const contexts = new Set()

    schemaStore.entities.forEach((entity) => {
      const fqcn = entity.fqcn || entity.name
      const parts = fqcn.split('\\')

      // Extract namespace parts (exclude class name)
      for (let i = 1; i < parts.length; i++) {
        const context = parts.slice(0, i).join('\\')
        contexts.add(context)
      }
    })

    return Array.from(contexts).sort()
  })

  // Actions
  function saveView(name, description = '', type = 'filter') {
    const viewId = Date.now().toString()
    const view = {
      id: viewId,
      name,
      description,
      type, // 'filter' or 'playground'
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      selectedEntities: Array.from(schemaStore.selectedEntities), // Save selected entities
      layout: {
        nodes: new Map(currentLayout.value.nodes),
        zoom: currentLayout.value.zoom,
        viewport: { ...currentLayout.value.viewport }
      },
      filter: {
        ...currentFilter.value,
        excludeEntities: new Set(currentFilter.value.excludeEntities)
      },
      // Playground-specific data
      virtualChanges: type === 'playground' ? {
        addedEntities: [],
        modifiedEntities: [],
        addedRelations: [],
        removedEntities: [],
        removedRelations: []
      } : null
    }

    savedViews.value.set(viewId, view)
    currentViewId.value = viewId
    persistViews()

    return viewId
  }

  function updateView(viewId) {
    const view = savedViews.value.get(viewId)
    if (!view) return false

    view.updatedAt = new Date().toISOString()
    view.selectedEntities = Array.from(schemaStore.selectedEntities) // Update selected entities
    view.layout = {
      nodes: new Map(currentLayout.value.nodes),
      zoom: currentLayout.value.zoom,
      viewport: { ...currentLayout.value.viewport }
    }
    view.filter = {
      ...currentFilter.value,
      excludeEntities: new Set(currentFilter.value.excludeEntities)
    }

    persistViews()
    return true
  }

  function loadView(viewId) {
    const view = savedViews.value.get(viewId)
    if (!view) return false

    currentViewId.value = viewId
    currentLayout.value = {
      nodes: new Map(view.layout.nodes || []),
      zoom: typeof view.layout.zoom === 'number' ? view.layout.zoom : 1,
      viewport: (view.layout.viewport && typeof view.layout.viewport.x === 'number')
        ? { ...view.layout.viewport }
        : { x: 0, y: 0 }
    }

    currentFilter.value = {
      ...view.filter,
      excludeEntities: new Set(view.filter.excludeEntities)
    }

    // Restore selected entities
    if (view.selectedEntities && view.selectedEntities.length > 0) {
      let selectedEntities = view.selectedEntities

      // For playground views, filter out removed entities
      if (view.type === 'playground' && view.virtualChanges?.removedEntities) {
        const removedSet = new Set(view.virtualChanges.removedEntities)
        selectedEntities = selectedEntities.filter(fqcn => !removedSet.has(fqcn))
      }

      schemaStore.setSelectedEntities(selectedEntities)
    }

    return true
  }

  function deleteView(viewId) {
    if (currentViewId.value === viewId) {
      currentViewId.value = null
    }
    savedViews.value.delete(viewId)
    persistViews()
  }

  function duplicateView(viewId, newName) {
    const view = savedViews.value.get(viewId)
    if (!view) return null

    const newViewId = Date.now().toString()
    const duplicated = {
      ...view,
      id: newViewId,
      name: newName || `${view.name} (Copy)`,
      type: view.type || 'filter',
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
      selectedEntities: view.selectedEntities ? [...view.selectedEntities] : [],
      layout: {
        nodes: new Map(view.layout.nodes),
        zoom: view.layout.zoom,
        viewport: { ...view.layout.viewport }
      },
      filter: {
        ...view.filter,
        excludeEntities: new Set(view.filter.excludeEntities)
      },
      virtualChanges: view.virtualChanges ? {
        addedEntities: [...view.virtualChanges.addedEntities],
        modifiedEntities: [...view.virtualChanges.modifiedEntities],
        addedRelations: [...view.virtualChanges.addedRelations],
        removedEntities: [...view.virtualChanges.removedEntities]
      } : null
    }

    savedViews.value.set(newViewId, duplicated)
    persistViews()

    return newViewId
  }

  function updateNodePosition(entityFqcn, x, y) {
    currentLayout.value.nodes.set(entityFqcn, { x, y })

    if (autoSaveEnabled.value && currentViewId.value) {
      updateView(currentViewId.value)
    }
  }

  function updateViewport(zoom, x, y) {
    currentLayout.value.zoom = zoom
    currentLayout.value.viewport = { x, y }

    if (autoSaveEnabled.value && currentViewId.value) {
      updateView(currentViewId.value)
    }
  }

  function setBoundedContextFilter(contexts) {
    currentFilter.value.boundedContexts = contexts
  }

  function setEntityTypeFilter(types) {
    currentFilter.value.entityTypes = types
  }

  function toggleEntityVisibility(entityFqcn) {
    if (currentFilter.value.excludeEntities.has(entityFqcn)) {
      currentFilter.value.excludeEntities.delete(entityFqcn)
    } else {
      currentFilter.value.excludeEntities.add(entityFqcn)
    }
  }

  function toggleShowOrphans() {
    currentFilter.value.showOrphans = !currentFilter.value.showOrphans
  }

  function resetFilter() {
    currentFilter.value = {
      boundedContexts: [],
      entityTypes: [],
      excludeEntities: new Set(),
      showOrphans: true
    }
  }

  function resetLayout() {
    currentLayout.value = {
      nodes: new Map(),
      zoom: 1,
      viewport: { x: 0, y: 0 }
    }
  }

  function clearCurrentView() {
    currentViewId.value = null
    resetLayout()
    resetFilter()
  }

  // Persistence
  function persistViews() {
    try {
      const serialized = []

      for (const [id, view] of savedViews.value) {
        serialized.push({
          ...view,
          type: view.type || 'filter',
          selectedEntities: view.selectedEntities || [],
          layout: {
            ...view.layout,
            nodes: Array.from(view.layout.nodes.entries())
          },
          filter: {
            ...view.filter,
            excludeEntities: Array.from(view.filter.excludeEntities)
          },
          virtualChanges: view.virtualChanges || null
        })
      }

      localStorage.setItem('qd_schema_views', JSON.stringify(serialized))
    } catch (error) {
    }
  }

  function loadPersistedViews() {
    try {
      const data = localStorage.getItem('qd_schema_views')
      if (!data) return

      const serialized = JSON.parse(data)
      savedViews.value.clear()

      for (const view of serialized) {
        // Ensure virtualChanges has the correct structure (migration)
        let virtualChanges = view.virtualChanges
        if (virtualChanges && view.type === 'playground') {
          virtualChanges = {
            addedEntities: virtualChanges.addedEntities || [],
            modifiedEntities: virtualChanges.modifiedEntities || [],
            addedRelations: virtualChanges.addedRelations || [],
            removedEntities: virtualChanges.removedEntities || [],
            removedRelations: virtualChanges.removedRelations || []
          }
        }

        savedViews.value.set(view.id, {
          ...view,
          type: view.type || 'filter',
          selectedEntities: view.selectedEntities || [],
          layout: {
            ...view.layout,
            nodes: new Map(view.layout.nodes)
          },
          filter: {
            ...view.filter,
            excludeEntities: new Set(view.filter.excludeEntities)
          },
          virtualChanges
        })
      }
    } catch (error) {
    }
  }

  // Helpers
  function hasIncomingRelations(entity) {
    const fqcn = entity.fqcn || entity.name

    return schemaStore.entities.some((other) => {
      return other.relations?.some((rel) => rel.target === fqcn)
    })
  }

  function exportView(viewId) {
    const view = savedViews.value.get(viewId)
    if (!view) return null

    return JSON.stringify({
      ...view,
      type: view.type || 'filter',
      selectedEntities: view.selectedEntities || [],
      layout: {
        ...view.layout,
        nodes: Array.from(view.layout.nodes.entries())
      },
      filter: {
        ...view.filter,
        excludeEntities: Array.from(view.filter.excludeEntities)
      },
      virtualChanges: view.virtualChanges || null
    }, null, 2)
  }

  function importView(jsonString) {
    try {
      const data = JSON.parse(jsonString)
      const viewId = Date.now().toString()

      const view = {
        ...data,
        id: viewId,
        type: data.type || 'filter',
        createdAt: new Date().toISOString(),
        selectedEntities: data.selectedEntities || [],
        layout: {
          ...data.layout,
          nodes: new Map(data.layout.nodes)
        },
        filter: {
          ...data.filter,
          excludeEntities: new Set(data.filter.excludeEntities)
        },
        virtualChanges: data.virtualChanges || null
      }

      savedViews.value.set(viewId, view)
      persistViews()

      return viewId
    } catch (error) {
      return null
    }
  }

  // History management
  function saveToHistory(view, action, data) {
    if (!view.history) {
      view.history = []
    }

    const historyEntry = {
      id: Date.now(),
      timestamp: new Date().toISOString(),
      action,
      data,
      snapshot: {
        virtualChanges: JSON.parse(JSON.stringify(view.virtualChanges || {}))
      }
    }

    view.history.push(historyEntry)

    // Keep only last 50 actions
    if (view.history.length > 50) {
      view.history = view.history.slice(-50)
    }
  }

  // Virtual Changes Management (Playground)
  function addVirtualEntity(entity) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return false

    if (!view.virtualChanges) {
      view.virtualChanges = {
        addedEntities: [],
        modifiedEntities: [],
        addedRelations: [],
        removedEntities: [],
        removedRelations: []
      }
    }

    saveToHistory(view, 'add_entity', { entity })

    const updatedView = {
      ...view,
      updatedAt: new Date().toISOString(),
      version: Date.now(),
      virtualChanges: {
        ...view.virtualChanges,
        addedEntities: [...view.virtualChanges.addedEntities, entity]
      }
    }

    const newMap = new Map(savedViews.value)
    newMap.set(currentViewId.value, updatedView)
    savedViews.value = newMap

    playgroundModificationCounter.value++
    persistViews()
    return true
  }

  function updateVirtualEntity(entityFqcn, updatedEntity) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground' || !view.virtualChanges) return false

    const index = view.virtualChanges.addedEntities.findIndex(
      e => (e.fqcn || e.name) === entityFqcn
    )

    if (index === -1) return false

    // Save to history before modifying
    saveToHistory(view, 'update_entity', {
      entityFqcn,
      oldEntity: view.virtualChanges.addedEntities[index],
      newEntity: updatedEntity
    })

    // Create new array to trigger Vue reactivity
    const newAddedEntities = [...view.virtualChanges.addedEntities]
    newAddedEntities[index] = updatedEntity

    const updatedView = {
      ...view,
      updatedAt: new Date().toISOString(),
      version: Date.now(), // Version timestamp to force reactivity
      virtualChanges: {
        ...view.virtualChanges,
        addedEntities: newAddedEntities
      }
    }

    // Create new Map to trigger Vue reactivity
    const newMap = new Map(savedViews.value)
    newMap.set(currentViewId.value, updatedView)
    savedViews.value = newMap

    playgroundModificationCounter.value++
    persistViews()
    return true
  }

  function restoreFromHistory(historyId) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground' || !view.history) return false

    const historyIndex = view.history.findIndex(h => h.id === historyId)
    if (historyIndex === -1) return false

    const historyEntry = view.history[historyIndex]

    const updatedView = {
      ...view,
      updatedAt: new Date().toISOString(),
      version: Date.now(),
      virtualChanges: historyEntry.snapshot.virtualChanges,
      history: view.history.slice(0, historyIndex + 1) // Keep history up to this point
    }

    const newMap = new Map(savedViews.value)
    newMap.set(currentViewId.value, updatedView)
    savedViews.value = newMap

    playgroundModificationCounter.value++
    persistViews()
    return true
  }

  function clearHistory() {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return false

    const updatedView = {
      ...view,
      version: Date.now(),
      history: []
    }

    const newMap = new Map(savedViews.value)
    newMap.set(currentViewId.value, updatedView)
    savedViews.value = newMap

    persistViews()
    return true
  }

  function removeVirtualEntity(entityFqcn) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground' || !view.virtualChanges) return false

    // Remove from added entities
    view.virtualChanges.addedEntities = view.virtualChanges.addedEntities.filter(
      e => (e.fqcn || e.name) !== entityFqcn
    )

    // Add to removed entities if it's a real entity
    const realEntity = schemaStore.entities.find(e => (e.fqcn || e.name) === entityFqcn)
    if (realEntity && !view.virtualChanges.removedEntities.includes(entityFqcn)) {
      view.virtualChanges.removedEntities.push(entityFqcn)
    }

    // Remove from selected entities in schema store
    schemaStore.removeEntityFromSelection(entityFqcn)

    // Update selectedEntities in the view
    view.selectedEntities = Array.from(schemaStore.selectedEntities)

    view.updatedAt = new Date().toISOString()
    // Force reactivity
    savedViews.value.set(currentViewId.value, { ...view })
    playgroundModificationCounter.value++
    persistViews()
    return true
  }

  function modifyVirtualEntity(entityFqcn, changes) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return false

    if (!view.virtualChanges) {
      view.virtualChanges = {
        addedEntities: [],
        modifiedEntities: [],
        addedRelations: [],
        removedEntities: [],
        removedRelations: []
      }
    }

    // Check if already modified
    const existingIdx = view.virtualChanges.modifiedEntities.findIndex(
      m => m.fqcn === entityFqcn
    )

    if (existingIdx >= 0) {
      // Update existing modification
      view.virtualChanges.modifiedEntities[existingIdx] = {
        fqcn: entityFqcn,
        changes: { ...view.virtualChanges.modifiedEntities[existingIdx].changes, ...changes }
      }
    } else {
      // Add new modification
      view.virtualChanges.modifiedEntities.push({
        fqcn: entityFqcn,
        changes
      })
    }

    view.updatedAt = new Date().toISOString()
    persistViews()
    return true
  }

  function addVirtualRelation(relation) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return false

    if (!view.virtualChanges) {
      view.virtualChanges = {
        addedEntities: [],
        modifiedEntities: [],
        addedRelations: [],
        removedEntities: [],
        removedRelations: []
      }
    }

    view.virtualChanges.addedRelations.push(relation)
    view.updatedAt = new Date().toISOString()
    persistViews()
    return true
  }

  function removeVirtualRelation(source, target) {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return false

    if (!view.virtualChanges) {
      view.virtualChanges = {
        addedEntities: [],
        modifiedEntities: [],
        addedRelations: [],
        removedEntities: [],
        removedRelations: []
      }
    }

    // Try to remove from added relations first (virtual relations)
    const initialLength = view.virtualChanges.addedRelations.length
    view.virtualChanges.addedRelations = view.virtualChanges.addedRelations.filter(
      rel => !(rel.source === source && rel.target === target)
    )

    const removedFromAdded = initialLength !== view.virtualChanges.addedRelations.length

    // If not found in added relations, it's a real relation - add to removed
    if (!removedFromAdded) {
      const relationKey = `${source}|${target}`
      if (!view.virtualChanges.removedRelations.includes(relationKey)) {
        view.virtualChanges.removedRelations.push(relationKey)
      }
    }

    view.updatedAt = new Date().toISOString()
    // Force reactivity by reassigning to the Map
    savedViews.value.set(currentViewId.value, { ...view })
    persistViews()
    return true
  }

  function clearVirtualChanges() {
    if (!currentViewId.value) return false
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return false

    view.virtualChanges = {
      addedEntities: [],
      modifiedEntities: [],
      addedRelations: [],
      removedEntities: [],
      removedRelations: []
    }

    view.updatedAt = new Date().toISOString()
    persistViews()
    return true
  }

  // Computed: Get merged entities (real + virtual)
  const playgroundEntities = computed(() => {
    // Force reactivity with modification counter
    const _ = playgroundModificationCounter.value

    // Use currentView computed instead of direct Map access for better reactivity
    const view = currentView.value
    if (!view || view.type !== 'playground' || !view.virtualChanges) {
      return schemaStore.entities
    }

    let entities = [...schemaStore.entities]

    // Remove entities marked as removed
    if (view.virtualChanges.removedEntities.length > 0) {
      entities = entities.filter(e =>
        !view.virtualChanges.removedEntities.includes(e.fqcn || e.name)
      )
    }

    // Apply modifications
    if (view.virtualChanges.modifiedEntities.length > 0) {
      entities = entities.map(entity => {
        const mod = view.virtualChanges.modifiedEntities.find(
          m => m.fqcn === (entity.fqcn || entity.name)
        )
        return mod ? { ...entity, ...mod.changes } : entity
      })
    }

    // Add virtual entities
    if (view.virtualChanges.addedEntities.length > 0) {
      entities = [...entities, ...view.virtualChanges.addedEntities]
    }

    return entities
  })

  // Computed: Get current playground history
  const playgroundHistory = computed(() => {
    if (!currentViewId.value) return []
    const view = savedViews.value.get(currentViewId.value)
    if (!view || view.type !== 'playground') return []
    return view.history || []
  })

  // Initialize
  loadPersistedViews()

  return {
    // State
    savedViews,
    currentViewId,
    currentView,
    currentLayout,
    currentFilter,
    autoSaveEnabled,

    // Computed
    hasUnsavedChanges,
    filteredEntities,
    availableBoundedContexts,
    playgroundEntities,
    playgroundHistory,

    // Actions
    saveView,
    updateView,
    loadView,
    deleteView,
    duplicateView,
    updateNodePosition,
    updateViewport,
    setBoundedContextFilter,
    setEntityTypeFilter,
    toggleEntityVisibility,
    toggleShowOrphans,
    resetFilter,
    resetLayout,
    clearCurrentView,
    exportView,
    importView,

    // Playground Actions
    addVirtualEntity,
    updateVirtualEntity,
    removeVirtualEntity,
    modifyVirtualEntity,
    addVirtualRelation,
    removeVirtualRelation,
    clearVirtualChanges,

    // History Actions
    restoreFromHistory,
    clearHistory
  }
})
