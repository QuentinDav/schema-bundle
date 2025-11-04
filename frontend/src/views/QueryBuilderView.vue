<script setup>
import { ref } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import Icon from '@/components/Icon.vue'

const schemaStore = useSchemaStore()

const prompt = ref('')
const result = ref(null)
const isGenerating = ref(false)

const examples = [
  { text: 'List all users', icon: 'user-group' },
  { text: 'Get all training with address in Paris', icon: 'map-pin' },
  { text: 'List all users with their addresses', icon: 'table-cells' },
  { text: 'Find addresses from users limit 10', icon: 'funnel' },
  { text: 'Get training where name contains test', icon: 'magnifying-glass' }
]

async function generateSql() {
  if (!prompt.value.trim()) return

  isGenerating.value = true

  try {
    const response = await fetch('/schema-doc/api/nl-to-sql/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        prompt: prompt.value,
        strategy: 'local'
      })
    })

    if (!response.ok) throw new Error('API error')

    const data = await response.json()
    result.value = data
  } catch (error) {
    console.error('Error generating SQL:', error)
    result.value = { success: false, error: 'Failed to generate SQL' }
  } finally {
    isGenerating.value = false
  }
}

function useExample(example) {
  prompt.value = example.text
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text)
}
</script>

<template>
  <div class="h-full overflow-auto p-6 bg-[var(--color-background)]">
    <div class="max-w-4xl mx-auto space-y-6">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-[var(--color-text-primary)] mb-2">Natural Language Query Builder</h1>
        <p class="text-[var(--color-text-secondary)]">Describe what you want in plain language, get SQL automatically</p>
      </div>

      <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-6">
        <label class="block text-sm font-semibold text-[var(--color-text-primary)] mb-3">
          Your Query
        </label>
        <textarea
          v-model="prompt"
          placeholder="e.g., 'List all users with their email addresses'"
          class="w-full h-32 px-4 py-3 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-[var(--color-text-primary)] placeholder-[var(--color-text-tertiary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)] resize-none"
          @keydown.enter.meta="generateSql"
          @keydown.enter.ctrl="generateSql"
        ></textarea>

        <div class="flex items-center justify-between mt-4">
          <span class="text-xs text-[var(--color-text-tertiary)]">Press Cmd/Ctrl + Enter to generate</span>
          <button
            @click="generateSql"
            :disabled="!prompt.trim() || isGenerating"
            class="flex items-center gap-2 px-6 py-2.5 bg-[var(--color-primary)] hover:bg-[var(--color-primary-hover)] text-white font-semibold rounded-lg transition-all disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <svg v-if="!isGenerating" class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <path d="M13 10V3L4 14h7v7l9-11h-7z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <div v-else class="w-5 h-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
            {{ isGenerating ? 'Generating...' : 'Generate SQL' }}
          </button>
        </div>
      </div>

      <div v-if="!result" class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-6">
        <h3 class="text-sm font-semibold text-[var(--color-text-primary)] mb-4">Try these examples:</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
          <button
            v-for="example in examples"
            :key="example.text"
            @click="useExample(example)"
            class="flex items-center gap-3 p-3 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-left hover:border-[var(--color-primary)] hover:bg-[var(--color-primary-light)] transition-all group"
          >
            <Icon :name="example.icon" class="w-5 h-5 text-[var(--color-text-tertiary)] group-hover:text-[var(--color-primary)]" />
            <span class="text-sm text-[var(--color-text-secondary)] group-hover:text-[var(--color-text-primary)]">{{ example.text }}</span>
          </button>
        </div>
      </div>

      <div v-if="result" class="space-y-4">
        <div v-if="result.success" class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg overflow-hidden">
          <div class="flex items-center justify-between px-4 py-3 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
            <div class="flex items-center gap-2">
              <svg class="w-5 h-5 text-[var(--color-success)]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
              <span class="font-semibold text-[var(--color-text-primary)]">Generated SQL</span>
            </div>
            <button
              @click="copyToClipboard(result.sql)"
              class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-[var(--color-primary)] hover:bg-[var(--color-primary-light)] rounded-lg transition-all"
            >
              <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <rect x="9" y="9" width="13" height="13" rx="2" stroke-width="2"/>
                <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1" stroke-width="2"/>
              </svg>
              Copy
            </button>
          </div>
          <pre class="p-4 text-sm font-mono text-[var(--color-text-primary)] overflow-x-auto"><code>{{ result.sql }}</code></pre>
        </div>

        <div v-if="result.explanation" class="bg-[var(--color-info-light)] border border-[var(--color-info)]/20 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-[var(--color-info)] flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="2"/>
              <path d="M12 16v-4m0-4h.01" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div>
              <h4 class="font-semibold text-[var(--color-text-primary)] mb-1">Explanation</h4>
              <p class="text-sm text-[var(--color-text-secondary)]">{{ result.explanation }}</p>
            </div>
          </div>
        </div>

        <div v-if="result.entities && result.entities.length > 0" class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4">
          <h4 class="font-semibold text-[var(--color-text-primary)] mb-3">Tables Used</h4>
          <div class="flex flex-wrap gap-2">
            <span
              v-for="entity in result.entities"
              :key="entity"
              class="px-3 py-1.5 bg-[var(--color-primary-light)] text-[var(--color-primary)] text-sm font-medium rounded-lg"
            >
              {{ entity }}
            </span>
          </div>
        </div>

        <div v-if="!result.success" class="bg-[var(--color-danger-light)] border border-[var(--color-danger)]/20 rounded-lg p-4">
          <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-[var(--color-danger)] flex-shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="2"/>
              <path d="M15 9l-6 6m0-6l6 6" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <div>
              <h4 class="font-semibold text-[var(--color-text-primary)] mb-1">Error</h4>
              <p class="text-sm text-[var(--color-text-secondary)]">{{ result.error || 'Failed to generate SQL' }}</p>
            </div>
          </div>
        </div>

        <button
          @click="result = null; prompt = ''"
          class="w-full py-2.5 text-sm font-medium text-[var(--color-text-secondary)] hover:text-[var(--color-text-primary)] hover:bg-[var(--color-surface-hover)] rounded-lg transition-all"
        >
          Try another query
        </button>
      </div>
    </div>
  </div>
</template>
