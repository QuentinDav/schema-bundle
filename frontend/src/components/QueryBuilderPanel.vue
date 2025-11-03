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

// AI settings
const useAi = ref(false)
const aiAvailable = ref(false)
const aiProvider = ref('Local')
const estimatedCost = ref(null)
const strategy = ref('local') // 'local', 'ai', 'hybrid'

// Example prompts
const examples = [
  'List all users',
  'Get all training with address in Paris',
  'List all users with their addresses',
  'Find addresses from users limit 10',
  'Get training where name = test'
]

// Check AI availability on mount
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

// Update strategy based on useAi toggle
const updateStrategy = () => {
  strategy.value = useAi.value ? 'ai' : 'local'
  estimatedCost.value = null

  // Estimate cost immediately if AI is enabled and there's a prompt
  if (useAi.value && prompt.value.trim()) {
    estimateCostForPrompt()
  }
}

// Estimate cost when AI is enabled
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

// Generate SQL from prompt - ALWAYS uses backend API
async function generateSql() {
  if (!prompt.value.trim()) return

  isGenerating.value = true
  showExamples.value = false

  try {
    // Always call backend API (handles both local and AI strategies)
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

// Use example prompt
function useExample(example) {
  prompt.value = example
  showExamples.value = false
  generateSql()
}

// Copy SQL to clipboard
async function copySql() {
  if (!result.value?.sql) return

  try {
    await navigator.clipboard.writeText(result.value.sql)
    // Could add a toast notification here
  } catch (error) {
    console.error('Failed to copy:', error)
  }
}

// Clear result
function clearResult() {
  result.value = null
  prompt.value = ''
  showExamples.value = true
}

// Visualize query in graph
function visualizeQuery() {
  if (!result.value?.entities) return

  const entityFqcns = result.value.entities.map(e => e.fqcn || e.name)
  schemaStore.setSelectedEntities(entityFqcns)
  emit('visualize-query', result.value)
}

// Handle Enter key (with Cmd/Ctrl for submit)
function handleKeydown(event) {
  if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    generateSql()
  }
}
</script>

<template>
  <div class="query-builder-panel">
    <!-- Header -->
    <div class="panel-header">
      <div class="header-title">
        <Icon name="command-line" :size="20" />
        <h2>Query Builder</h2>
      </div>
      <p class="header-subtitle">
        Describe what you want to query in natural language
      </p>
    </div>

    <!-- AI Settings -->
    <div v-if="aiAvailable" class="panel-section ai-settings-section">
      <div class="ai-toggle-container">
        <label class="ai-toggle-label">
          <input
            type="checkbox"
            v-model="useAi"
            @change="updateStrategy(); estimateCostForPrompt()"
            class="ai-checkbox"
          />
          <span class="ai-toggle-text">
            <Icon name="sparkles" :size="16" />
            <span>Enhance with AI ({{ aiProvider }})</span>
          </span>
        </label>
        <span v-if="estimatedCost !== null && useAi" class="cost-badge">
          Est. cost: ${{ estimatedCost.toFixed(4) }}
        </span>
      </div>
    </div>

    <!-- Prompt Input -->
    <div class="panel-section">
      <div class="prompt-container">
        <label class="prompt-label">Your Query</label>
        <textarea
          v-model="prompt"
          @keydown="handleKeydown"
          @input="useAi && estimateCostForPrompt()"
          placeholder="Ex: Get all training where address in Paris and user age > 18"
          class="prompt-textarea"
          rows="4"
        />
        <div class="prompt-hint">
          <Icon name="information-circle" :size="14" />
          <span>Press Cmd+Enter to generate</span>
        </div>
      </div>

      <div class="action-buttons">
        <button
          @click="generateSql"
          :disabled="!prompt.trim() || isGenerating"
          class="generate-btn"
          :class="{ 'ai-enhanced': useAi }"
        >
          <Icon :name="isGenerating ? 'arrow-path' : (useAi ? 'sparkles' : 'code-bracket')" :size="18" />
          <span>{{ isGenerating ? 'Generating...' : (useAi ? 'Generate with AI' : 'Generate SQL') }}</span>
        </button>

        <button
          v-if="result"
          @click="clearResult"
          class="clear-btn"
        >
          <Icon name="x-mark" :size="16" />
          <span>Clear</span>
        </button>
      </div>
    </div>

    <!-- Examples -->
    <div v-if="showExamples && !result" class="panel-section examples-section">
      <h3 class="section-title">
        <Icon name="light-bulb" :size="18" />
        <span>Examples</span>
      </h3>
      <div class="examples-list">
        <button
          v-for="(example, index) in examples"
          :key="index"
          @click="useExample(example)"
          class="example-item"
        >
          <Icon name="arrow-right" :size="14" />
          <span>{{ example }}</span>
        </button>
      </div>
    </div>

    <!-- Result Success -->
    <div v-if="result?.success" class="panel-section result-section">
      <!-- SQL Output -->
      <div class="result-card sql-card">
        <div class="card-header">
          <h3 class="card-title">
            <Icon name="code-bracket" :size="18" />
            <span>Generated SQL</span>
          </h3>
          <div class="card-actions">
            <span v-if="result.provider" class="provider-badge">
              {{ result.provider }}
            </span>
            <span class="confidence-badge" :class="getConfidenceClass(result.confidence)">
              {{ Math.round(result.confidence * 100) }}% confidence
            </span>
            <span v-if="result.cost" class="cost-badge-result">
              ${{ result.cost.actual.toFixed(4) }}
            </span>
            <button @click="copySql" class="icon-btn" title="Copy SQL">
              <Icon name="clipboard" :size="16" />
            </button>
          </div>
        </div>
        <pre class="sql-code">{{ result.sql }}</pre>
      </div>

      <!-- Explanation -->
      <div class="result-card explanation-card">
        <div class="card-header">
          <h3 class="card-title">
            <Icon name="information-circle" :size="18" />
            <span>Explanation</span>
          </h3>
        </div>
        <p class="explanation-text">{{ result.explanation }}</p>
      </div>

      <!-- Entities Used -->
      <div class="result-card entities-card">
        <div class="card-header">
          <h3 class="card-title">
            <Icon name="table-cells" :size="18" />
            <span>Entities Used</span>
          </h3>
        </div>
        <div class="entities-list">
          <span
            v-for="entity in result.entities"
            :key="entity.name"
            class="entity-badge"
          >
            {{ entity.name }}
          </span>
        </div>
      </div>

      <!-- Paths -->
      <div v-if="result.paths && result.paths.length > 0" class="result-card paths-card">
        <div class="card-header">
          <h3 class="card-title">
            <Icon name="map" :size="18" />
            <span>Relation Paths</span>
          </h3>
        </div>
        <div class="paths-list">
          <div
            v-for="(path, index) in result.paths"
            :key="index"
            class="path-item"
          >
            <span class="path-badge">{{ path.length }} hop{{ path.length > 1 ? 's' : '' }}</span>
            <span class="path-formula">
              {{ path.entities.map(e => e.name).join(' → ') }}
            </span>
          </div>
        </div>
      </div>

      <!-- Visualize Button -->
      <button @click="visualizeQuery" class="visualize-btn">
        <Icon name="eye" :size="18" />
        <span>Visualize in Graph</span>
      </button>
    </div>

    <!-- Result Error -->
    <div v-else-if="result && !result.success" class="panel-section error-section">
      <div class="error-card">
        <div class="error-icon">
          <Icon name="exclamation-triangle" :size="48" />
        </div>
        <h3 class="error-title">{{ getErrorTitle(result.error) }}</h3>
        <p class="error-message">{{ result.message }}</p>

        <!-- Missing Paths -->
        <div v-if="result.missingPaths" class="missing-paths">
          <h4>Missing Relations:</h4>
          <ul>
            <li v-for="(path, index) in result.missingPaths" :key="index">
              {{ path.from }} → {{ path.to }}
            </li>
          </ul>
        </div>

        <!-- Suggestions -->
        <div v-if="result.suggestions" class="suggestions">
          <h4>Suggestions:</h4>
          <ul>
            <li v-for="(suggestion, index) in result.suggestions" :key="index">
              {{ suggestion }}
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
function getConfidenceClass(confidence) {
  if (confidence >= 0.8) return 'high'
  if (confidence >= 0.5) return 'medium'
  return 'low'
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

<style scoped>
.query-builder-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
  background: var(--color-gray-50);
  overflow-y: auto;
}

/* Header */
.panel-header {
  padding: var(--spacing-4);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
}

.header-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin-bottom: var(--spacing-1);
}

.header-title h2 {
  margin: 0;
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-900);
}

.header-subtitle {
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-gray-600);
}

/* Section */
.panel-section {
  padding: var(--spacing-4);
  background: white;
  border-bottom: 1px solid var(--color-gray-200);
}

.section-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0 0 var(--spacing-3) 0;
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-gray-900);
}

/* Prompt Input */
.prompt-container {
  margin-bottom: var(--spacing-3);
}

.prompt-label {
  display: block;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-700);
  margin-bottom: var(--spacing-1-5);
}

.prompt-textarea {
  width: 100%;
  padding: var(--spacing-3);
  background: var(--color-gray-50);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  font-family: inherit;
  color: var(--color-gray-900);
  resize: vertical;
  min-height: 80px;
  transition: all var(--transition-base);
}

.prompt-textarea:focus {
  outline: none;
  border-color: var(--color-primary-500);
  background: white;
  box-shadow: 0 0 0 3px var(--color-primary-100);
}

.prompt-hint {
  display: flex;
  align-items: center;
  gap: var(--spacing-1);
  margin-top: var(--spacing-1);
  font-size: var(--text-xs);
  color: var(--color-gray-500);
}

/* AI Settings */
.ai-settings-section {
  background: var(--color-blue-50);
  border-bottom: 2px solid var(--color-blue-200);
}

.ai-toggle-container {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-2);
}

.ai-toggle-label {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  cursor: pointer;
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-700);
}

.ai-checkbox {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.ai-toggle-text {
  display: flex;
  align-items: center;
  gap: var(--spacing-1-5);
}

.cost-badge {
  padding: 4px var(--spacing-2);
  background: var(--color-yellow-100);
  color: var(--color-yellow-800);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-full);
}

.provider-badge {
  padding: 4px var(--spacing-2);
  background: var(--color-blue-100);
  color: var(--color-blue-700);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-sm);
  text-transform: uppercase;
}

.cost-badge-result {
  padding: 4px var(--spacing-2);
  background: var(--color-green-100);
  color: var(--color-green-700);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-full);
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: var(--spacing-2);
}

.generate-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background: linear-gradient(135deg, var(--color-primary-500) 0%, var(--color-primary-600) 100%);
  color: white;
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--text-base);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
}

.generate-btn.ai-enhanced {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
}

.generate-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: var(--shadow-md);
}

.generate-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.clear-btn {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2-5) var(--spacing-3);
  background: white;
  border: 2px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  font-weight: 600;
  color: var(--color-gray-700);
  cursor: pointer;
  transition: all var(--transition-base);
}

.clear-btn:hover {
  border-color: var(--color-red-500);
  color: var(--color-red-600);
  background: var(--color-red-50);
}

/* Examples */
.examples-section {
  background: var(--color-gray-50);
}

.examples-list {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.example-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  color: var(--color-gray-700);
  text-align: left;
  cursor: pointer;
  transition: all var(--transition-base);
}

.example-item:hover {
  border-color: var(--color-primary-300);
  background: var(--color-primary-50);
  color: var(--color-primary-700);
  transform: translateX(4px);
}

/* Result Cards */
.result-section {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-3);
}

.result-card {
  background: white;
  border: 1px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-3);
  background: var(--color-gray-50);
  border-bottom: 1px solid var(--color-gray-200);
}

.card-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0;
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--color-gray-900);
}

.card-actions {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.confidence-badge {
  padding: 4px var(--spacing-2);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-full);
}

.confidence-badge.high {
  background: var(--color-green-100);
  color: var(--color-green-700);
}

.confidence-badge.medium {
  background: var(--color-yellow-100);
  color: var(--color-yellow-700);
}

.confidence-badge.low {
  background: var(--color-red-100);
  color: var(--color-red-700);
}

.icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  background: white;
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
  color: var(--color-gray-600);
}

.icon-btn:hover {
  background: var(--color-gray-100);
  border-color: var(--color-primary-500);
  color: var(--color-primary-600);
}

/* SQL Code */
.sql-code {
  padding: var(--spacing-4);
  background: #1e1e1e;
  color: #d4d4d4;
  font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
  font-size: var(--text-sm);
  line-height: 1.6;
  overflow-x: auto;
  margin: 0;
}

/* Explanation */
.explanation-text {
  padding: var(--spacing-4);
  margin: 0;
  font-size: var(--text-sm);
  color: var(--color-gray-700);
  line-height: 1.6;
}

/* Entities */
.entities-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
}

.entity-badge {
  padding: 6px var(--spacing-3);
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  font-size: var(--text-sm);
  font-weight: 600;
  border-radius: var(--radius-full);
}

/* Paths */
.paths-list {
  padding: var(--spacing-3);
  display: flex;
  flex-direction: column;
  gap: var(--spacing-2);
}

.path-item {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  padding: var(--spacing-2);
  background: var(--color-gray-50);
  border-radius: var(--radius-md);
}

.path-badge {
  padding: 4px var(--spacing-2);
  background: var(--color-blue-100);
  color: var(--color-blue-700);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-sm);
}

.path-formula {
  font-size: var(--text-sm);
  color: var(--color-gray-700);
  font-weight: 500;
}

/* Visualize Button */
.visualize-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3);
  background: white;
  border: 2px solid var(--color-primary-500);
  border-radius: var(--radius-lg);
  font-size: var(--text-base);
  font-weight: 600;
  color: var(--color-primary-600);
  cursor: pointer;
  transition: all var(--transition-base);
}

.visualize-btn:hover {
  background: var(--color-primary-50);
  transform: translateY(-1px);
}

/* Error */
.error-section {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.error-card {
  width: 100%;
  text-align: center;
  padding: var(--spacing-6);
  background: white;
  border: 1px solid var(--color-red-200);
  border-radius: var(--radius-lg);
}

.error-icon {
  margin-bottom: var(--spacing-3);
  color: var(--color-red-500);
}

.error-title {
  font-size: var(--text-xl);
  font-weight: 700;
  color: var(--color-red-700);
  margin: 0 0 var(--spacing-2) 0;
}

.error-message {
  font-size: var(--text-base);
  color: var(--color-gray-700);
  margin: 0 0 var(--spacing-4) 0;
}

.missing-paths,
.suggestions {
  text-align: left;
  margin-top: var(--spacing-4);
  padding: var(--spacing-3);
  background: var(--color-gray-50);
  border-radius: var(--radius-md);
}

.missing-paths h4,
.suggestions h4 {
  font-size: var(--text-sm);
  font-weight: 700;
  color: var(--color-gray-900);
  margin: 0 0 var(--spacing-2) 0;
}

.missing-paths ul,
.suggestions ul {
  margin: 0;
  padding-left: var(--spacing-4);
  font-size: var(--text-sm);
  color: var(--color-gray-700);
}

.missing-paths li,
.suggestions li {
  margin-bottom: var(--spacing-1);
}

/* Scrollbar */
.query-builder-panel::-webkit-scrollbar {
  width: 8px;
}

.query-builder-panel::-webkit-scrollbar-track {
  background: var(--color-gray-100);
}

.query-builder-panel::-webkit-scrollbar-thumb {
  background: var(--color-gray-300);
  border-radius: var(--radius-full);
}

.query-builder-panel::-webkit-scrollbar-thumb:hover {
  background: var(--color-gray-400);
}
</style>
