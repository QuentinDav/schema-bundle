<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  position: {
    type: Object,
    default: () => ({ x: 0, y: 0 })
  },
  items: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['close', 'item-click'])

const menuRef = ref(null)

const menuStyle = computed(() => ({
  left: `${props.position.x}px`,
  top: `${props.position.y}px`
}))

function handleItemClick(item) {
  if (!item.disabled) {
    emit('item-click', item)
    emit('close')
  }
}

function handleClickOutside(event) {
  if (menuRef.value && !menuRef.value.contains(event.target)) {
    emit('close')
  }
}

watch(() => props.isOpen, (newValue) => {
  if (newValue) {
    setTimeout(() => {
      document.addEventListener('click', handleClickOutside)
    }, 0)
  } else {
    document.removeEventListener('click', handleClickOutside)
  }
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>

<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      ref="menuRef"
      :style="menuStyle"
      class="fixed z-[9999] min-w-[200px] bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg shadow-2xl py-1 overflow-hidden"
    >
      <template v-for="(item, index) in items" :key="index">
        <div
          v-if="item.type === 'separator'"
          class="h-px bg-[var(--color-border)] my-1"
        />
        <button
          v-else
          @click="handleItemClick(item)"
          :disabled="item.disabled"
          class="w-full px-3 py-2 flex items-center gap-3 text-sm transition-colors"
          :class="item.disabled
            ? 'text-[var(--color-text-tertiary)] cursor-not-allowed opacity-50'
            : item.danger
              ? 'text-[var(--color-danger)] hover:bg-[var(--color-danger-light)]'
              : 'text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)]'"
        >
          <Icon
            v-if="item.icon"
            :name="item.icon"
            class="w-4 h-4 flex-shrink-0"
          />
          <span class="flex-1 text-left">{{ item.label }}</span>
          <span
            v-if="item.shortcut"
            class="text-[10px] text-[var(--color-text-tertiary)] font-mono"
          >
            {{ item.shortcut }}
          </span>
        </button>
      </template>
    </div>
  </Teleport>
</template>
