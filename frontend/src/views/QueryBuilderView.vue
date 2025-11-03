<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSchemaStore } from '@/stores/schema'
import Icon from '@/components/Icon.vue'

const router = useRouter()
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
const strategy = ref('local')

// Example prompts
const examples = [
  { text: 'List all users', icon: 'user-group' },
  { text: 'Get all training with address in Paris', icon: 'map-pin' },
  { text: 'List all users with their addresses', icon: 'table-cells' },
  { text: 'Find addresses from users limit 10', icon: 'funnel' },
  { text: 'Get training where name contains test', icon: 'magnifying-glass' }
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

// Generate SQL from prompt
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

// Use example prompt
function useExample(example) {
  prompt.value = example.text
  showExamples.value = false
  generateSql()
}

// Copy SQL to clipboard
async function copySql() {
  if (!result.value?.sql) return

  try {
    await navigator.clipboard.writeText(result.value.sql)
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
function visualizeInGraph() {
  if (!result.value?.entities) return

  const entityFqcns = result.value.entities.map(e => e.fqcn || e.name)
  schemaStore.setSelectedEntities(entityFqcns)
  router.push('/schema')
}

// Handle Enter key
function handleKeydown(event) {
  if (event.key === 'Enter' && (event.metaKey || event.ctrlKey)) {
    event.preventDefault()
    generateSql()
  }
}

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
    'API_ERROR': 'API Error',
    'UNEXPECTED_ERROR': 'Unexpected Error'
  }
  return titles[error] || 'Error'
}
</script>

<template>
  <div class="query-builder-view">
    <!-- Header -->
    <div class="view-header">
      <div class="header-content">
        <div class="header-left">
          <router-link to="/schema" class="back-button">
            <Icon name="arrow-left" :size="20" />
          </router-link>
          <div class="header-title-group">
            <h1 class="view-title">
              <Icon name="command-line" :size="28" />
              <span>Query Builder</span>
            </h1>
            <p class="view-subtitle">
              Generate SQL queries from natural language descriptions
            </p>
          </div>
        </div>

        <!-- AI Toggle (Header) -->
        <div v-if="aiAvailable" class="header-ai-toggle">
          <label class="ai-toggle-label">
            <input
              type="checkbox"
              v-model="useAi"
              @change="updateStrategy(); estimateCostForPrompt()"
              class="ai-checkbox"
            />
            <span class="ai-toggle-text">
              <Icon name="sparkles" :size="18" />
              <span>{{ aiProvider }}</span>
            </span>
          </label>
          <span v-if="estimatedCost !== null && useAi" class="cost-badge">
            ~${{ estimatedCost.toFixed(4) }}
          </span>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="view-content">
      <div class="content-container">
        <!-- Prompt Section -->
        <div class="prompt-section">
          <div class="prompt-card">
            <label class="prompt-label">
              <Icon name="chat-bubble-left-right" :size="18" />
              <span>Describe your query</span>
            </label>
            <textarea
              v-model="prompt"
              @keydown="handleKeydown"
              @input="useAi && estimateCostForPrompt()"
              placeholder="Example: Get all users where email contains 'test' and address city is Paris"
              class="prompt-textarea"
              rows="5"
              autofocus
            />
            <div class="prompt-footer">
              <div class="prompt-hint">
                <Icon name="information-circle" :size="16" />
                <span>Press <kbd>Cmd</kbd> + <kbd>Enter</kbd> to generate</span>
              </div>

              <div class="action-buttons">
                <button
                  v-if="result"
                  @click="clearResult"
                  class="btn btn-secondary"
                >
                  <Icon name="x-mark" :size="18" />
                  <span>Clear</span>
                </button>

                <button
                  @click="generateSql"
                  :disabled="!prompt.trim() || isGenerating"
                  class="btn btn-primary"
                  :class="{ 'btn-ai': useAi }"
                >
                  <Icon :name="isGenerating ? 'arrow-path' : (useAi ? 'sparkles' : 'code-bracket')" :size="20" :class="{ 'animate-spin': isGenerating }" />
                  <span>{{ isGenerating ? 'Generating...' : 'Generate SQL' }}</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Examples -->
          <div v-if="showExamples && !result" class="examples-section">
            <h3 class="examples-title">
              <Icon name="light-bulb" :size="20" />
              <span>Try these examples</span>
            </h3>
            <div class="examples-grid">
              <button
                v-for="(example, index) in examples"
                :key="index"
                @click="useExample(example)"
                class="example-card"
              >
                <Icon :name="example.icon" :size="20" class="example-icon" />
                <span class="example-text">{{ example.text }}</span>
                <Icon name="arrow-right" :size="16" class="example-arrow" />
              </button>
            </div>
          </div>
        </div>

        <!-- Result Section -->
        <div v-if="result" class="result-section">
          <!-- Success -->
          <div v-if="result.success" class="result-success">
            <!-- SQL Card -->
            <div class="result-card sql-card">
              <div class="card-header">
                <h3 class="card-title">
                  <Icon name="code-bracket" :size="20" />
                  <span>Generated SQL</span>
                </h3>
                <div class="card-badges">
                  <span v-if="result.provider" class="badge badge-provider">
                    {{ result.provider }}
                  </span>
                  <span class="badge badge-confidence" :class="getConfidenceClass(result.confidence)">
                    {{ Math.round(result.confidence * 100) }}%
                  </span>
                  <span v-if="result.cost" class="badge badge-cost">
                    ${{ result.cost.actual.toFixed(4) }}
                  </span>
                  <button @click="copySql" class="icon-btn" title="Copy to clipboard">
                    <Icon name="clipboard" :size="18" />
                  </button>
                </div>
              </div>
              <pre class="sql-code">{{ result.sql }}</pre>
            </div>

            <!-- Info Grid -->
            <div class="info-grid">
              <!-- Explanation -->
              <div class="result-card">
                <div class="card-header">
                  <h3 class="card-title">
                    <Icon name="information-circle" :size="20" />
                    <span>Explanation</span>
                  </h3>
                </div>
                <div class="card-body">
                  <p class="explanation-text">{{ result.explanation }}</p>
                </div>
              </div>

              <!-- Entities -->
              <div class="result-card">
                <div class="card-header">
                  <h3 class="card-title">
                    <Icon name="table-cells" :size="20" />
                    <span>Entities ({{ result.entities?.length || 0 }})</span>
                  </h3>
                </div>
                <div class="card-body">
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
              </div>
            </div>

            <!-- Actions -->
            <div class="result-actions">
              <button @click="visualizeInGraph" class="btn btn-outline-primary btn-lg">
                <Icon name="eye" :size="20" />
                <span>Visualize in Graph</span>
              </button>
            </div>
          </div>

          <!-- Error -->
          <div v-else class="result-error">
            <div class="error-card">
              <div class="error-icon">
                <Icon name="exclamation-triangle" :size="64" />
              </div>
              <h3 class="error-title">{{ getErrorTitle(result.error) }}</h3>
              <p class="error-message">{{ result.message }}</p>

              <div v-if="result.suggestions" class="error-suggestions">
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
      </div>
    </div>
  </div>
</template>

<style scoped>
.query-builder-view {
  height: 100%;
  background: linear-gradient(135deg, #f5f7fa 0%, #e9ecef 100%);
  display: flex;
  flex-direction: column;
  overflow-y: auto;
  overflow-x: hidden;
}

/* Header */
.view-header {
  background: white;
  border-bottom: 2px solid var(--color-gray-200);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  position: sticky;
  top: 0;
  z-index: 100;
}

.header-content {
  max-width: 1400px;
  margin: 0 auto;
  padding: var(--spacing-6) var(--spacing-6);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--spacing-4);
}

.header-left {
  display: flex;
  align-items: center;
  gap: var(--spacing-4);
}

.back-button {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  background: var(--color-gray-100);
  border-radius: var(--radius-lg);
  color: var(--color-gray-700);
  transition: all var(--transition-base);
  text-decoration: none;
}

.back-button:hover {
  background: var(--color-gray-200);
  color: var(--color-gray-900);
  transform: translateX(-2px);
}

.header-title-group {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-1);
}

.view-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  margin: 0;
  font-size: var(--text-2xl);
  font-weight: 800;
  color: var(--color-gray-900);
}

.view-subtitle {
  margin: 0;
  font-size: var(--text-base);
  color: var(--color-gray-600);
}

.header-ai-toggle {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-3) var(--spacing-4);
  background: var(--color-blue-50);
  border: 2px solid var(--color-blue-200);
  border-radius: var(--radius-lg);
}

.ai-toggle-label {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  cursor: pointer;
  font-weight: 600;
  color: var(--color-gray-800);
}

.ai-checkbox {
  width: 20px;
  height: 20px;
  cursor: pointer;
}

.ai-toggle-text {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-base);
}

.cost-badge {
  padding: 6px var(--spacing-3);
  background: var(--color-yellow-100);
  color: var(--color-yellow-800);
  font-size: var(--text-sm);
  font-weight: 700;
  border-radius: var(--radius-full);
}

/* Main Content */
.view-content {
  flex: 1;
  padding: var(--spacing-8) var(--spacing-6);
}

.content-container {
  max-width: 1400px;
  margin: 0 auto;
  display: grid;
  gap: var(--spacing-6);
}

/* Prompt Section */
.prompt-section {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.prompt-card {
  background: white;
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
  padding: var(--spacing-6);
  border: 2px solid var(--color-gray-200);
  transition: all var(--transition-base);
}

.prompt-card:focus-within {
  border-color: var(--color-primary-500);
  box-shadow: 0 6px 24px rgba(59, 130, 246, 0.15);
}

.prompt-label {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-900);
  margin-bottom: var(--spacing-4);
}

.prompt-textarea {
  width: 100%;
  padding: var(--spacing-4);
  background: var(--color-gray-50);
  border: 2px solid var(--color-gray-300);
  border-radius: var(--radius-lg);
  font-size: var(--text-lg);
  font-family: inherit;
  color: var(--color-gray-900);
  resize: vertical;
  min-height: 120px;
  transition: all var(--transition-base);
  line-height: 1.6;
}

.prompt-textarea:focus {
  outline: none;
  border-color: var(--color-primary-500);
  background: white;
  box-shadow: 0 0 0 4px var(--color-primary-100);
}

.prompt-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-top: var(--spacing-4);
  gap: var(--spacing-4);
}

.prompt-hint {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  font-size: var(--text-sm);
  color: var(--color-gray-600);
}

.prompt-hint kbd {
  padding: 2px 8px;
  background: var(--color-gray-200);
  border: 1px solid var(--color-gray-300);
  border-radius: var(--radius-sm);
  font-size: var(--text-xs);
  font-family: monospace;
  font-weight: 600;
}

.action-buttons {
  display: flex;
  gap: var(--spacing-3);
}

/* Buttons */
.btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--spacing-2);
  padding: var(--spacing-3) var(--spacing-5);
  border: none;
  border-radius: var(--radius-lg);
  font-size: var(--text-base);
  font-weight: 600;
  cursor: pointer;
  transition: all var(--transition-base);
  white-space: nowrap;
}

.btn-lg {
  padding: var(--spacing-4) var(--spacing-6);
  font-size: var(--text-lg);
}

.btn-primary {
  background: linear-gradient(135deg, var(--color-primary-500) 0%, var(--color-primary-600) 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
}

.btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(59, 130, 246, 0.35);
}

.btn-primary.btn-ai {
  background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
  box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);
}

.btn-primary.btn-ai:hover:not(:disabled) {
  box-shadow: 0 4px 16px rgba(139, 92, 246, 0.35);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.btn-secondary {
  background: white;
  border: 2px solid var(--color-gray-300);
  color: var(--color-gray-700);
}

.btn-secondary:hover {
  background: var(--color-gray-50);
  border-color: var(--color-gray-400);
}

.btn-outline-primary {
  background: white;
  border: 2px solid var(--color-primary-500);
  color: var(--color-primary-600);
}

.btn-outline-primary:hover {
  background: var(--color-primary-50);
  transform: translateY(-2px);
}

.animate-spin {
  animation: spin 1s linear infinite;
}

@keyframes spin {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* Examples */
.examples-section {
  background: white;
  border-radius: var(--radius-xl);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  padding: var(--spacing-6);
  border: 1px solid var(--color-gray-200);
}

.examples-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0 0 var(--spacing-4) 0;
  font-size: var(--text-lg);
  font-weight: 700;
  color: var(--color-gray-900);
}

.examples-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: var(--spacing-3);
}

.example-card {
  display: flex;
  align-items: center;
  gap: var(--spacing-3);
  padding: var(--spacing-4);
  background: var(--color-gray-50);
  border: 2px solid var(--color-gray-200);
  border-radius: var(--radius-lg);
  cursor: pointer;
  transition: all var(--transition-base);
  text-align: left;
}

.example-card:hover {
  background: var(--color-primary-50);
  border-color: var(--color-primary-400);
  transform: translateX(4px);
}

.example-icon {
  color: var(--color-primary-600);
  flex-shrink: 0;
}

.example-text {
  flex: 1;
  font-size: var(--text-sm);
  font-weight: 500;
  color: var(--color-gray-800);
}

.example-arrow {
  color: var(--color-gray-400);
  flex-shrink: 0;
}

.example-card:hover .example-arrow {
  color: var(--color-primary-600);
}

/* Result Section */
.result-section {
  animation: fadeIn 0.3s ease-out;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.result-success {
  display: flex;
  flex-direction: column;
  gap: var(--spacing-6);
}

.result-card {
  background: white;
  border-radius: var(--radius-xl);
  box-shadow: 0 2px 12px rgba(0, 0, 0, 0.06);
  border: 1px solid var(--color-gray-200);
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: var(--spacing-4) var(--spacing-5);
  background: var(--color-gray-50);
  border-bottom: 1px solid var(--color-gray-200);
}

.card-title {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
  margin: 0;
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-gray-900);
}

.card-badges {
  display: flex;
  align-items: center;
  gap: var(--spacing-2);
}

.badge {
  padding: 6px var(--spacing-3);
  font-size: var(--text-xs);
  font-weight: 700;
  border-radius: var(--radius-full);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.badge-provider {
  background: var(--color-blue-100);
  color: var(--color-blue-700);
}

.badge-confidence {
  padding: 6px var(--spacing-3);
}

.badge-confidence.high {
  background: var(--color-green-100);
  color: var(--color-green-700);
}

.badge-confidence.medium {
  background: var(--color-yellow-100);
  color: var(--color-yellow-700);
}

.badge-confidence.low {
  background: var(--color-red-100);
  color: var(--color-red-700);
}

.badge-cost {
  background: var(--color-green-100);
  color: var(--color-green-700);
}

.icon-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 36px;
  height: 36px;
  background: white;
  border: 2px solid var(--color-gray-300);
  border-radius: var(--radius-md);
  cursor: pointer;
  transition: all var(--transition-base);
  color: var(--color-gray-600);
}

.icon-btn:hover {
  background: var(--color-primary-50);
  border-color: var(--color-primary-500);
  color: var(--color-primary-600);
}

.sql-code {
  padding: var(--spacing-5);
  background: #1e1e1e;
  color: #d4d4d4;
  font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
  font-size: var(--text-base);
  line-height: 1.6;
  overflow-x: auto;
  margin: 0;
}

.card-body {
  padding: var(--spacing-5);
}

.explanation-text {
  margin: 0;
  font-size: var(--text-base);
  color: var(--color-gray-700);
  line-height: 1.7;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: var(--spacing-4);
}

.entities-list {
  display: flex;
  flex-wrap: wrap;
  gap: var(--spacing-2);
}

.entity-badge {
  padding: 8px var(--spacing-4);
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  font-size: var(--text-sm);
  font-weight: 600;
  border-radius: var(--radius-full);
}

.result-actions {
  display: flex;
  justify-content: center;
}

/* Error */
.result-error {
  display: flex;
  justify-content: center;
  padding: var(--spacing-8);
}

.error-card {
  max-width: 600px;
  text-align: center;
  padding: var(--spacing-8);
  background: white;
  border: 2px solid var(--color-red-200);
  border-radius: var(--radius-xl);
  box-shadow: 0 4px 16px rgba(239, 68, 68, 0.1);
}

.error-icon {
  margin-bottom: var(--spacing-4);
  color: var(--color-red-500);
}

.error-title {
  font-size: var(--text-2xl);
  font-weight: 700;
  color: var(--color-red-700);
  margin: 0 0 var(--spacing-3) 0;
}

.error-message {
  font-size: var(--text-lg);
  color: var(--color-gray-700);
  margin: 0 0 var(--spacing-5) 0;
  line-height: 1.6;
}

.error-suggestions {
  text-align: left;
  padding: var(--spacing-4);
  background: var(--color-gray-50);
  border-radius: var(--radius-lg);
  margin-top: var(--spacing-5);
}

.error-suggestions h4 {
  font-size: var(--text-base);
  font-weight: 700;
  color: var(--color-gray-900);
  margin: 0 0 var(--spacing-2) 0;
}

.error-suggestions ul {
  margin: 0;
  padding-left: var(--spacing-5);
  font-size: var(--text-sm);
  color: var(--color-gray-700);
  line-height: 1.7;
}

.error-suggestions li {
  margin-bottom: var(--spacing-1);
}
</style>
