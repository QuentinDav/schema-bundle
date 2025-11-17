<template>
  <div class="relative inline-flex">
    <!-- Trigger Button -->
    <button
      @click.stop="toggleDropdown"
      class="flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 border border-white/30 hover:border-white/50 rounded-lg text-sm font-semibold transition-all hover:-translate-y-0.5"
      :class="{ 'bg-white/30 border-white/50': isOpen }"
    >
      <EyeIcon v-if="!viewsStore.currentView || viewsStore.currentView.type === 'filter'" class="w-4 h-4" />
      <ComputerDesktopIcon v-else class="w-4 h-4" />
      <span>{{ currentViewName }}</span>
      <span v-if="viewsStore.currentView?.type === 'playground'" class="px-1.5 py-0.5 bg-purple-500/30 text-purple-100 text-[10px] font-bold rounded uppercase">Lab</span>
      <ChevronDownIcon class="w-3 h-3 transition-transform" :class="{ 'rotate-180': isOpen }" />
      <span v-if="viewsStore.hasUnsavedChanges" class="w-2 h-2 bg-orange-400 rounded-full" title="Unsaved changes"></span>
    </button>

    <!-- Dropdown Menu -->
    <Transition
      enter-active-class="transition-all duration-150"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition-all duration-100"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        v-click-outside="() => isOpen = false"
        class="absolute top-full mt-2 right-0 w-64 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-xl overflow-hidden z-50"
      >
        <!-- Current View Info -->
        <div v-if="viewsStore.currentView" class="px-3 py-2 bg-blue-50 dark:bg-blue-900/20 border-b border-blue-200 dark:border-blue-800">
          <div class="text-xs font-semibold text-blue-900 dark:text-blue-100 mb-1">Active View</div>
          <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-blue-700 dark:text-blue-300">{{ viewsStore.currentView.name }}</span>
            <button
              @click.stop="clearView"
              class="text-xs px-2 py-0.5 text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-900/40 rounded transition-colors"
            >
              Clear
            </button>
          </div>
        </div>

        <!-- Views List -->
        <div class="max-h-80 overflow-y-auto">
          <div v-if="savedViewsList.length === 0" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
            <EyeIcon class="w-12 h-12 mx-auto mb-2 text-gray-300 dark:text-gray-600" />
            <p class="text-sm">No saved views yet</p>
          </div>

          <button
            v-for="view in savedViewsList"
            :key="view.id"
            @click="loadView(view.id)"
            class="w-full px-3 py-2 text-left hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
            :class="{ 'bg-gray-50 dark:bg-gray-700': view.id === viewsStore.currentViewId }"
          >
            <div class="flex items-center justify-between">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <div class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                    {{ view.name }}
                  </div>
                  <span v-if="view.type === 'playground'" class="px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300 text-[10px] font-bold rounded uppercase flex-shrink-0">Lab</span>
                </div>
                <div v-if="view.selectedEntities?.length" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                  {{ view.selectedEntities.length }} {{ view.selectedEntities.length === 1 ? 'entity' : 'entities' }}
                  <span v-if="view.type === 'playground' && view.virtualChanges">
                    · {{ (view.virtualChanges.addedEntities?.length || 0) + (view.virtualChanges.modifiedEntities?.length || 0) }} virtual
                  </span>
                </div>
              </div>
              <CheckCircleIcon v-if="view.id === viewsStore.currentViewId" class="w-5 h-5 text-blue-500 flex-shrink-0 ml-2" />
            </div>
          </button>
        </div>

        <!-- Actions -->
        <div class="border-t border-gray-200 dark:border-gray-700 p-2">
          <button
            @click.stop="openManager"
            class="w-full px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded transition-colors flex items-center gap-2"
          >
            <Cog6ToothIcon class="w-4 h-4" />
            <span>Manage Views...</span>
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useViewsStore } from '@/stores/views'
import { EyeIcon, ComputerDesktopIcon, ChevronDownIcon, CheckCircleIcon, Cog6ToothIcon } from '@heroicons/vue/24/outline'

const viewsStore = useViewsStore()
const emit = defineEmits(['open-manager'])

const isOpen = ref(false)

const currentViewName = computed(() => {
  return viewsStore.currentView?.name || 'No View'
})

const savedViewsList = computed(() => {
  return Array.from(viewsStore.savedViews.values())
    .sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt))
    .slice(0, 10) // Show only 10 most recent
})

function loadView(viewId) {
  viewsStore.loadView(viewId)
  isOpen.value = false
}

function clearView() {
  viewsStore.clearCurrentView()
  isOpen.value = false
}

function openManager() {
  emit('open-manager')
  isOpen.value = false
}

function toggleDropdown() {
  // Always toggle - standard dropdown behavior
  isOpen.value = !isOpen.value
}

// Click outside directive
const vClickOutside = {
  mounted(el, binding) {
    el.clickOutsideEvent = (event) => {
      if (!(el === event.target || el.contains(event.target))) {
        binding.value()
      }
    }
    document.addEventListener('click', el.clickOutsideEvent)
  },
  unmounted(el) {
    document.removeEventListener('click', el.clickOutsideEvent)
  }
}
</script>
