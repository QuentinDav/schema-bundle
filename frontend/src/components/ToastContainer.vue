<script setup>
import { useToastStore } from '@/stores/toast'

const toastStore = useToastStore()

function getIcon(type) {
  switch (type) {
    case 'success':
      return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M20 6L9 17l-5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      </svg>`
    case 'error':
      return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="12" cy="12" r="10" stroke-width="2"/>
        <path d="M15 9l-6 6M9 9l6 6" stroke-width="2" stroke-linecap="round"/>
      </svg>`
    case 'warning':
      return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <path d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round"/>
      </svg>`
    default:
      return `<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor">
        <circle cx="12" cy="12" r="10" stroke-width="2"/>
        <path d="M12 16v-4m0-4h.01" stroke-width="2" stroke-linecap="round"/>
      </svg>`
  }
}

function getColorClasses(type) {
  switch (type) {
    case 'success':
      return 'bg-gradient-to-r from-[#10b981] to-[#059669] text-white shadow-lg shadow-[#10b981]/30'
    case 'error':
      return 'bg-gradient-to-r from-[#ef4444] to-[#dc2626] text-white shadow-lg shadow-[#ef4444]/30'
    case 'warning':
      return 'bg-gradient-to-r from-[#f59e0b] to-[#d97706] text-white shadow-lg shadow-[#f59e0b]/30'
    default:
      return 'bg-gradient-to-r from-[#3b82f6] to-[#2563eb] text-white shadow-lg shadow-[#3b82f6]/30'
  }
}
</script>

<template>
  <div class="fixed top-5 right-5 z-[var(--z-tooltip)] flex flex-col gap-3 pointer-events-none">
    <TransitionGroup name="toast">
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg min-w-[300px] max-w-[500px] pointer-events-auto cursor-pointer transition-all duration-300 hover:-translate-x-1"
        :class="getColorClasses(toast.type)"
        @click="toastStore.removeToast(toast.id)"
      >
        <div class="flex-shrink-0" v-html="getIcon(toast.type)"></div>
        <div class="flex-1 text-sm font-medium">{{ toast.message }}</div>
        <button
          class="flex-shrink-0 p-1 opacity-70 hover:opacity-100 hover:bg-white/20 rounded transition-all"
          @click.stop="toastStore.removeToast(toast.id)"
        >
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M18 6L6 18M6 6l12 12" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.3s ease;
}

.toast-enter-from {
  opacity: 0;
  transform: translateX(100px);
}

.toast-leave-to {
  opacity: 0;
  transform: translateX(100px) scale(0.8);
}

.toast-move {
  transition: transform 0.3s ease;
}
</style>
