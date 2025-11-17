<script setup>
import { onMounted, ref } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import { useCommentsStore } from '@/stores/comments'
import AppHeader from '@/components/AppHeader.vue'
import AppSidebar from '@/components/AppSidebar.vue'
import TableDetails from '@/components/TableDetails.vue'
import ToastContainer from '@/components/ToastContainer.vue'
import CommandPalette from '@/components/CommandPalette.vue'
import PlaygroundPanel from '@/components/PlaygroundPanel.vue'

const schemaStore = useSchemaStore()
const commentsStore = useCommentsStore()
const commandPaletteRef = ref(null)

onMounted(() => {
  schemaStore.fetchSchema()
  commentsStore.fetchComments()
  commentsStore.fetchUsers()
})

function openCommandPalette() {
  commandPaletteRef.value?.open()
}
</script>

<template>
  <div class="flex flex-col h-screen overflow-hidden bg-[var(--color-background)]">
    <AppHeader @open-command-palette="openCommandPalette" />

    <div class="flex flex-1 overflow-hidden">
      <AppSidebar />

      <main class="flex-1 overflow-hidden relative">
        <div v-if="schemaStore.loading" class="absolute inset-0 flex flex-col items-center justify-center gap-4">
          <div class="w-12 h-12 border-4 border-[var(--color-border)] border-t-[var(--color-primary)] rounded-full animate-spin"></div>
          <p class="text-sm text-[var(--color-text-secondary)]">Loading schema...</p>
        </div>

        <div v-else-if="schemaStore.error" class="absolute inset-0 flex flex-col items-center justify-center gap-4">
          <svg class="w-16 h-16 text-[var(--color-danger)]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="2" />
            <path d="M12 8v4m0 4h.01" stroke-width="2" stroke-linecap="round" />
          </svg>
          <h3 class="text-xl font-semibold text-[var(--color-text-primary)]">Error loading schema</h3>
          <p class="text-sm text-[var(--color-text-secondary)]">{{ schemaStore.error }}</p>
          <button
            @click="schemaStore.fetchSchema()"
            class="mt-2 px-6 py-3 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white font-semibold rounded-lg transition-all duration-200 hover:-translate-y-0.5"
          >
            Try Again
          </button>
        </div>

        <RouterView v-else />
      </main>
    </div>

    <TableDetails />
    <ToastContainer />
    <CommandPalette ref="commandPaletteRef" />
    <PlaygroundPanel />
  </div>
</template>
