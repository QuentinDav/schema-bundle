<template>
  <Teleport to="body">
    <Transition
      enter-active-class="transition-all duration-200"
      leave-active-class="transition-all duration-150"
      enter-from-class="opacity-0 scale-95"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="isOpen"
        :style="{ top: `${position.y}px`, left: `${position.x}px`, zIndex: 99999 }"
        class="fixed bg-white dark:bg-gray-800 rounded-lg shadow-2xl border-2 border-purple-500 dark:border-purple-600 p-4 w-80"
        @click.stop
      >
        <div class="flex items-center gap-2 mb-3">
          <LinkIcon class="w-5 h-5 text-purple-600 dark:text-purple-400" />
          <h3 class="font-semibold text-gray-900 dark:text-gray-100">Create Relation</h3>
        </div>

        <div class="space-y-3">
          <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">From → To</label>
            <div class="flex items-center gap-2 text-sm">
              <span class="px-2 py-1 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded font-medium">
                {{ sourceEntity }}
              </span>
              <ArrowRightIcon class="w-4 h-4 text-gray-400" />
              <span class="px-2 py-1 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 rounded font-medium">
                {{ targetEntity }}
              </span>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Relation Type</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="type in relationTypes"
                :key="type.value"
                @click="selectedType = type.value"
                class="p-2 text-left rounded-lg border-2 transition-all"
                :class="selectedType === type.value
                  ? 'border-purple-500 bg-purple-50 dark:bg-purple-900/20'
                  : 'border-gray-300 dark:border-gray-600 hover:border-purple-400'"
              >
                <div class="text-xs font-semibold" :class="selectedType === type.value ? 'text-purple-700 dark:text-purple-300' : 'text-gray-700 dark:text-gray-300'">
                  {{ type.label }}
                </div>
                <div class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">{{ type.notation }}</div>
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Field Name</label>
            <input
              v-model="fieldName"
              type="text"
              :placeholder="suggestedFieldName"
              class="w-full px-3 py-2 text-sm border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:ring-2 focus:ring-purple-500 focus:border-transparent"
              @keydown.enter="createRelation"
            />
          </div>

          <div class="flex items-center gap-2">
            <input
              v-model="isOwning"
              type="checkbox"
              id="owning-side"
              class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500"
            />
            <label for="owning-side" class="text-xs text-gray-700 dark:text-gray-300">
              Owning side (holds the foreign key)
            </label>
          </div>
        </div>

        <div class="flex items-center gap-2 mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
          <button
            @click="createRelation"
            :disabled="!fieldName.trim()"
            class="flex-1 px-3 py-2 bg-purple-600 hover:bg-purple-700 disabled:bg-gray-300 dark:disabled:bg-gray-600 text-white rounded-lg font-medium transition-colors text-sm disabled:cursor-not-allowed"
          >
            Create
          </button>
          <button
            @click="cancel"
            class="px-3 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition-colors text-sm"
          >
            Cancel
          </button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { LinkIcon, ArrowRightIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  isOpen: {
    type: Boolean,
    default: false
  },
  position: {
    type: Object,
    default: () => ({ x: 0, y: 0 })
  },
  sourceEntity: {
    type: String,
    default: ''
  },
  targetEntity: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['create', 'cancel'])

const relationTypes = [
  { value: 'OneToMany', label: 'One to Many', notation: '1 → ∞' },
  { value: 'ManyToOne', label: 'Many to One', notation: '∞ → 1' },
  { value: 'ManyToMany', label: 'Many to Many', notation: '∞ ↔ ∞' },
  { value: 'OneToOne', label: 'One to One', notation: '1 ─ 1' }
]

const selectedType = ref('OneToMany')
const fieldName = ref('')
const isOwning = ref(true)

const suggestedFieldName = computed(() => {
  if (!props.targetEntity) return ''
  const name = props.targetEntity.toLowerCase()
  if (selectedType.value === 'OneToMany' || selectedType.value === 'ManyToMany') {
    return name.endsWith('s') ? name : `${name}s`
  }
  return name
})

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    selectedType.value = 'OneToMany'
    fieldName.value = ''
    isOwning.value = true
  }
})

watch(selectedType, () => {
  fieldName.value = ''
})

function createRelation() {
  if (!fieldName.value.trim()) return

  emit('create', {
    type: selectedType.value,
    field: fieldName.value.trim(),
    isOwning: isOwning.value,
    source: props.sourceEntity,
    target: props.targetEntity
  })

  fieldName.value = ''
}

function cancel() {
  emit('cancel')
}
</script>
