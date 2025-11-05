<script setup>
import { ref, computed, onMounted } from 'vue'
import { useSchemaStore } from '@/stores/schema'
import Icon from './Icon.vue'

const emit = defineEmits(['visualize-query'])

const schemaStore = useSchemaStore()

const prompt = ref('')
const result = ref(null)
const isGenerating = ref(false)
const showExamples = ref(true)

const useAi = ref(false)
const aiAvailable = ref(false)
const aiProvider = ref('Local')
const estimatedCost = ref(null)
const strategy = ref('local')

const examples = [
  'List all users',
  'Get all training with address in Paris',
  'List all users with their addresses',
  'Find addresses from users limit 10',
  'Get training where name = test'
]

onMounted(async () => {
  try {
    const response = await fetch('/schema-doc/api/nl-to-sql/status')
    const data = await response.json()
    aiAvailable.value = data.ai_available
    if (data.ai_available && data.ai_model) {
      aiProvider.value = data.ai_model
    }
  } catch (error) {
    console.warn('Failed to check AI availability:', error)
  }
})

const updateStrategy = () => {
  strategy.value = useAi.value ? 'ai' : 'local'
  estimatedCost.value = null

  if (useAi.value && prompt.value.trim()) {
    estimateCostForPrompt()
  }
}

const estimateCostForPrompt = async () => {
  if (!useAi.value || !prompt.value.trim()) {
    estimatedCost.value = null
    return
  }

  try {
    const response = await fetch('/schema-doc/api/nl-to-sql/estimate-cost', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        prompt: prompt.value,
        strategy: strategy.value
      })
    })
    const data = await response.json()
    if (data.success && data.estimate) {
      estimatedCost.value = data.estimate.amount
    }
  } catch (error) {
    console.warn('Failed to estimate cost:', error)
  }
}

async function generateSql() {
  if (!prompt.value.trim()) return

  isGenerating.value = true
  showExamples.value = false

  try {
    const response = await fetch('/schema-doc/api/nl-to-sql/generate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        prompt: prompt.value,
        strategy: strategy.value
      })
    })

    if (!response.ok) {
      throw new Error(`API error: ${response.statusText}`)
    }

    result.value = await response.json()
  } catch (error) {
    result.value = {
      success: false,
      error: 'API_ERROR',
      message: `Failed to communicate with server: ${error.message}`
    }
  } finally {
    isGenerating.value = false
  }
}

function useExample(example) {
  prompt.value = example
  showExamples.value = false
  generateSql()
}

async function copySql() {
  if (!result.value?.sql) return

  try {
    await navigator.clipboard.writeText(result.value.sql)
  } catch (error) {
    console.error('Failed to copy:', error)
  }
}

function clearResult() {
  result.value = null
  prompt.value = ''
  showExamples.value = true
}

function visualizeQuery() {
  if (!result.value?.entities) return

  const entityFqcns = result.value.entities.map(e => e.fqcn || e.name)
  schemaStore.setSelectedEntities(entityFqcns)
  emit('visualize-query', result.value)
}

function handleKeydown(event) {
  if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    generateSql()
  }
}

function getConfidenceClass(confidence) {
  if (confidence >= 0.8) return 'bg-[#ecfdf5] text-[#047857] border border-[#34d399]/30'
  if (confidence >= 0.5) return 'bg-[#fffbeb] text-[#d97706] border border-[#fbbf24]/30'
  return 'bg-[#fef2f2] text-[#dc2626] border border-[#fca5a5]/30'
}

function getErrorTitle(error) {
  const titles = {
    'ENTITY_NOT_FOUND': 'Entity Not Found',
    'NO_PATH_FOUND': 'Cannot Connect Entities',
    'PARSER_ERROR': 'Parser Error',
    'UNEXPECTED_ERROR': 'Unexpected Error'
  }
  return titles[error] || 'Error'
}
</script>

<template>
  <div class="h-full flex flex-col bg-[var(--color-surface)] overflow-y-auto">
    <div class="px-4 py-4 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <div class="flex items-center gap-2 mb-1">
        <Icon name="command-line" :size="20" class="text-[var(--color-primary)]" />
        <h2 class="m-0 text-base font-bold text-[var(--color-text-primary)]">Query Builder</h2>
      </div>
      <p class="m-0 text-sm text-[var(--color-text-secondary)]">
        Describe what you want to query in natural language
      </p>
    </div>

    <div v-if="aiAvailable" class="px-4 py-4 bg-gradient-to-r from-[#3b82f6]/10 to-[#8b5cf6]/10 border-b-2 border-[#3b82f6]/30">
      <div class="flex items-center justify-between gap-2">
        <label class="flex items-center gap-2 cursor-pointer text-sm font-semibold text-[var(--color-text-primary)]">
          <input
            type="checkbox"
            v-model="useAi"
            @change="updateStrategy(); estimateCostForPrompt()"
            class="w-4 h-4 cursor-pointer"
            style="accent-color: var(--color-primary)"
          />
          <span class="flex items-center gap-1.5">
            <Icon name="sparkles" :size="16" />
            <span>Enhance with AI ({{ aiProvider }})</span>
          </span>
        </label>
        <span v-if="estimatedCost !== null && useAi" class="px-2 py-1 bg-[#fef3c7] text-[#d97706] text-xs font-bold rounded-full">
          Est. cost: ${{ estimatedCost.toFixed(4) }}
        </span>
      </div>
    </div>

    <div class="px-4 py-4 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <div class="mb-3">
        <label class="block text-sm font-semibold text-[var(--color-text-primary)] mb-1.5">Your Query</label>
        <textarea
          v-model="prompt"
          @keydown="handleKeydown"
          @input="useAi && estimateCostForPrompt()"
          placeholder="Ex: Get all training where address in Paris and user age > 18"
          class="w-full px-3 py-3 bg-[var(--color-background)] border border-[var(--color-border)] rounded-lg text-sm font-sans text-[var(--color-text-primary)] placeholder:text-[var(--color-text-tertiary)] resize-vertical min-h-[80px] transition-all duration-200 focus:outline-none focus:border-[var(--color-primary)] focus:bg-[var(--color-surface)] focus:shadow-lg focus:shadow-[var(--color-primary)]/10"
          rows="4"
        />
        <div class="flex items-center gap-1 mt-1 text-xs text-[var(--color-text-secondary)]">
          <Icon name="information-circle" :size="14" />
          <span>Press Cmd+Enter to generate</span>
        </div>
      </div>

      <div class="flex gap-2">
        <button
          @click="generateSql"
          :disabled="!prompt.trim() || isGenerating"
          class="flex-1 flex items-center justify-center gap-2 px-3 py-3 border-0 rounded-lg text-sm font-semibold cursor-pointer transition-all duration-200 text-white disabled:opacity-50 disabled:cursor-not-allowed hover:shadow-md hover:-translate-y-px"
          :class="useAi ? 'bg-gradient-to-r from-[#8b5cf6] to-[#7c3aed]' : 'bg-gradient-to-r from-[var(--color-primary)] to-[var(--color-primary-hover)]'"
        >
          <Icon :name="isGenerating ? 'arrow-path' : (useAi ? 'sparkles' : 'code-bracket')" :size="18" :class="{ 'animate-spin': isGenerating }" />
          <span>{{ isGenerating ? 'Generating...' : (useAi ? 'Generate with AI' : 'Generate SQL') }}</span>
        </button>

        <button
          v-if="result"
          @click="clearResult"
          class="flex items-center gap-2 px-3 py-2.5 bg-[var(--color-surface)] border-2 border-[var(--color-border)] rounded-lg text-sm font-semibold text-[var(--color-text-primary)] cursor-pointer transition-all duration-200 hover:border-[var(--color-danger)] hover:text-[var(--color-danger)] hover:bg-[var(--color-danger-light)]"
        >
          <Icon name="x-mark" :size="16" />
          <span>Clear</span>
        </button>
      </div>
    </div>

    <div v-if="showExamples && !result" class="px-4 py-4 bg-[var(--color-surface-raised)] border-b border-[var(--color-border)]">
      <h3 class="flex items-center gap-2 m-0 mb-3 text-base font-bold text-[var(--color-text-primary)]">
        <Icon name="light-bulb" :size="18" />
        <span>Examples</span>
      </h3>
      <div class="flex flex-col gap-2">
        <button
          v-for="(example, index) in examples"
          :key="index"
          @click="useExample(example)"
          class="flex items-center gap-2 px-3 py-3 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg text-sm text-[var(--color-text-primary)] text-left cursor-pointer transition-all duration-200 hover:border-[var(--color-primary)]/30 hover:bg-[var(--color-primary-light)] hover:text-[var(--color-primary)] hover:translate-x-1"
        >
          <Icon name="arrow-right" :size="14" />
          <span>{{ example }}</span>
        </button>
      </div>
    </div>

    <div v-if="result?.success" class="px-4 py-4 flex flex-col gap-3">
      <div class="bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="flex items-center justify-between px-3 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
          <h3 class="flex items-center gap-2 m-0 text-sm font-bold text-[var(--color-text-primary)]">
            <Icon name="code-bracket" :size="18" />
            <span>Generated SQL</span>
          </h3>
          <div class="flex items-center gap-2">
            <span v-if="result.provider" class="px-2 py-1 bg-[#dbeafe] text-[#1e40af] text-xs font-bold rounded uppercase">
              {{ result.provider }}
            </span>
            <span class="px-2 py-1 text-xs font-bold rounded-full" :class="getConfidenceClass(result.confidence)">
              {{ Math.round(result.confidence * 100) }}% confidence
            </span>
            <span v-if="result.cost" class="px-2 py-1 bg-[#d1fae5] text-[#065f46] text-xs font-bold rounded-full">
              ${{ result.cost.actual.toFixed(4) }}
            </span>
            <button @click="copySql" class="flex items-center justify-center w-8 h-8 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-md cursor-pointer transition-all duration-200 text-[var(--color-text-secondary)] hover:bg-[var(--color-surface-hover)] hover:border-[var(--color-primary)] hover:text-[var(--color-primary)]" title="Copy SQL">
              <Icon name="clipboard" :size="16" />
            </button>
          </div>
        </div>
        <pre class="px-4 py-4 bg-[#1e1e1e] text-[#d4d4d4] font-mono text-sm leading-relaxed overflow-x-auto m-0">{{ result.sql }}</pre>
      </div>

      <div class="bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-3 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
          <h3 class="flex items-center gap-2 m-0 text-sm font-bold text-[var(--color-text-primary)]">
            <Icon name="information-circle" :size="18" />
            <span>Explanation</span>
          </h3>
        </div>
        <p class="px-4 py-4 m-0 text-sm text-[var(--color-text-primary)] leading-relaxed">{{ result.explanation }}</p>
      </div>

      <div class="bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-3 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
          <h3 class="flex items-center gap-2 m-0 text-sm font-bold text-[var(--color-text-primary)]">
            <Icon name="table-cells" :size="18" />
            <span>Entities Used</span>
          </h3>
        </div>
        <div class="flex flex-wrap gap-2 px-3 py-3">
          <span
            v-for="entity in result.entities"
            :key="entity.name"
            class="px-3 py-1.5 bg-[var(--color-primary-light)] text-[var(--color-primary)] text-sm font-semibold rounded-full"
          >
            {{ entity.name }}
          </span>
        </div>
      </div>

      <div v-if="result.paths && result.paths.length > 0" class="bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg overflow-hidden">
        <div class="px-3 py-3 bg-[var(--color-surface)] border-b border-[var(--color-border)]">
          <h3 class="flex items-center gap-2 m-0 text-sm font-bold text-[var(--color-text-primary)]">
            <Icon name="map" :size="18" />
            <span>Relation Paths</span>
          </h3>
        </div>
        <div class="px-3 py-3 flex flex-col gap-2">
          <div
            v-for="(path, index) in result.paths"
            :key="index"
            class="flex items-center gap-2 px-2 py-2 bg-[var(--color-surface)] rounded-md"
          >
            <span class="px-1.5 py-px bg-[#dbeafe] text-[#1e40af] text-xs font-bold rounded">
              {{ path.length }} hop{{ path.length > 1 ? 's' : '' }}
            </span>
            <span class="text-sm text-[var(--color-text-primary)] font-medium">
              {{ path.entities.map(e => e.name).join(' → ') }}
            </span>
          </div>
        </div>
      </div>

      <button @click="visualizeQuery" class="flex items-center justify-center gap-2 px-3 py-3 bg-[var(--color-surface-raised)] border-2 border-[var(--color-primary)] rounded-lg text-base font-semibold text-[var(--color-primary)] cursor-pointer transition-all duration-200 hover:bg-[var(--color-primary-light)] hover:-translate-y-px">
        <Icon name="eye" :size="18" />
        <span>Visualize in Graph</span>
      </button>
    </div>

    <div v-else-if="result && !result.success" class="px-4 py-4 flex flex-col items-center">
      <div class="w-full text-center px-6 py-6 bg-[var(--color-surface-raised)] border border-[var(--color-danger)]/30 rounded-lg">
        <div class="mb-3 text-[var(--color-danger)]">
          <Icon name="exclamation-triangle" :size="48" />
        </div>
        <h3 class="text-xl font-bold text-[var(--color-danger)] m-0 mb-2">{{ getErrorTitle(result.error) }}</h3>
        <p class="text-base text-[var(--color-text-primary)] m-0 mb-4">{{ result.message }}</p>

        <div v-if="result.missingPaths" class="text-left mt-4 px-3 py-3 bg-[var(--color-surface)] rounded-md">
          <h4 class="text-sm font-bold text-[var(--color-text-primary)] m-0 mb-2">Missing Relations:</h4>
          <ul class="m-0 pl-4 text-sm text-[var(--color-text-secondary)]">
            <li v-for="(path, index) in result.missingPaths" :key="index" class="mb-1">
              {{ path.from }} → {{ path.to }}
            </li>
          </ul>
        </div>

        <div v-if="result.suggestions" class="text-left mt-4 px-3 py-3 bg-[var(--color-surface)] rounded-md">
          <h4 class="text-sm font-bold text-[var(--color-text-primary)] m-0 mb-2">Suggestions:</h4>
          <ul class="m-0 pl-4 text-sm text-[var(--color-text-secondary)]">
            <li v-for="(suggestion, index) in result.suggestions" :key="index" class="mb-1">
              {{ suggestion }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>
