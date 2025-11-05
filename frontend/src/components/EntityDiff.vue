<script setup>
import { ref } from 'vue'
import Icon from './Icon.vue'

const props = defineProps({
  entityName: {
    type: String,
    required: true
  },
  diff: {
    type: Object,
    required: true
  },
  oldSchema: {
    type: Object,
    default: null
  },
  newSchema: {
    type: Object,
    default: null
  }
})

const isExpanded = ref(false)

function toggleExpand() {
  isExpanded.value = !isExpanded.value
}

function hasChanges() {
  const d = props.diff
  return (
    (d.fields_added?.length > 0) ||
    (d.fields_removed?.length > 0) ||
    (d.fields_changed?.length > 0) ||
    (d.rels_added?.length > 0) ||
    (d.rels_removed?.length > 0) ||
    (d.rels_changed?.length > 0)
  )
}

function getFieldSpec(field) {
  const parts = []
  parts.push(field.type || 'unknown')
  if (field.length) parts.push(`len=${field.length}`)
  if (field.nullable === false) parts.push('not-null')
  if (field.nullable === true) parts.push('nullable')
  if (field.unique) parts.push('unique')
  return parts.join(', ')
}

function getRelationSpec(rel) {
  const parts = []
  const typeMap = { 1: 'OneToOne', 2: 'ManyToOne', 4: 'OneToMany', 8: 'ManyToMany' }
  parts.push(typeMap[rel.type] || `type=${rel.type}`)
  if (rel.isOwning) parts.push('owning')
  if (rel.nullable) parts.push('nullable')
  return parts.join(', ')
}

function getChangedProperties(from, to) {
  const changes = []

  if (from.type !== to.type) {
    changes.push(`type: ${from.type} → ${to.type}`)
  }
  if (from.length !== to.length) {
    changes.push(`length: ${from.length || 'none'} → ${to.length || 'none'}`)
  }
  if (from.nullable !== to.nullable) {
    changes.push(`nullable: ${from.nullable ? 'yes' : 'no'} → ${to.nullable ? 'yes' : 'no'}`)
  }
  if (from.unique !== to.unique) {
    changes.push(`unique: ${from.unique ? 'yes' : 'no'} → ${to.unique ? 'yes' : 'no'}`)
  }

  return changes
}

function getRelationChangedProperties(from, to) {
  const changes = []
  const typeMap = { 1: 'OneToOne', 2: 'ManyToOne', 4: 'OneToMany', 8: 'ManyToMany' }

  if (from.type !== to.type) {
    changes.push(`type: ${typeMap[from.type]} → ${typeMap[to.type]}`)
  }
  if (from.isOwning !== to.isOwning) {
    changes.push(`owning: ${from.isOwning ? 'yes' : 'no'} → ${to.isOwning ? 'yes' : 'no'}`)
  }
  if (from.nullable !== to.nullable) {
    changes.push(`nullable: ${from.nullable ? 'yes' : 'no'} → ${to.nullable ? 'yes' : 'no'}`)
  }

  return changes
}
</script>

<template>
  <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-xl overflow-hidden transition-all duration-200 hover:border-[var(--color-primary)]/30 hover:shadow-md">
    <div class="flex items-center justify-between px-4 py-4 cursor-pointer select-none transition-colors duration-200 hover:bg-[var(--color-surface-hover)]" @click="toggleExpand">
      <div class="flex items-center gap-2">
        <Icon
          :name="isExpanded ? 'chevron-down' : 'chevron-right'"
          :size="20"
          class="text-[var(--color-text-tertiary)] transition-transform duration-200"
        />
        <h4 class="m-0 text-base font-bold text-[var(--color-text-primary)]">{{ entityName }}</h4>
      </div>

      <div class="flex flex-wrap gap-2">
        <span v-if="diff.fields_added?.length" class="px-2 py-1 rounded-lg text-xs font-semibold bg-[#ecfdf5] text-[#047857] border border-[#34d399]/30">
          +{{ diff.fields_added.length }} field{{ diff.fields_added.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.fields_removed?.length" class="px-2 py-1 rounded-lg text-xs font-semibold bg-[#fef2f2] text-[#dc2626] border border-[#fca5a5]/30">
          -{{ diff.fields_removed.length }} field{{ diff.fields_removed.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.fields_changed?.length" class="px-2 py-1 rounded-lg text-xs font-semibold bg-[#fffbeb] text-[#d97706] border border-[#fbbf24]/30">
          ~{{ diff.fields_changed.length }} field{{ diff.fields_changed.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.rels_added?.length" class="px-2 py-1 rounded-lg text-xs font-semibold bg-[#ecfdf5] text-[#047857] border border-[#34d399]/30">
          +{{ diff.rels_added.length }} rel{{ diff.rels_added.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.rels_removed?.length" class="px-2 py-1 rounded-lg text-xs font-semibold bg-[#fef2f2] text-[#dc2626] border border-[#fca5a5]/30">
          -{{ diff.rels_removed.length }} rel{{ diff.rels_removed.length > 1 ? 's' : '' }}
        </span>
        <span v-if="diff.rels_changed?.length" class="px-2 py-1 rounded-lg text-xs font-semibold bg-[#fffbeb] text-[#d97706] border border-[#fbbf24]/30">
          ~{{ diff.rels_changed.length }} rel{{ diff.rels_changed.length > 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <div v-if="isExpanded" class="border-t border-[var(--color-border)] px-4 py-4 bg-[var(--color-surface-raised)]">
      <div v-if="diff.fields_added?.length > 0" class="mb-4">
        <h5 class="flex items-center gap-2 m-0 mb-3 text-sm font-bold uppercase tracking-wide text-[#047857]">
          <Icon name="plus" :size="16" />
          Added Fields
        </h5>
        <div class="flex flex-col gap-2">
          <div v-for="field in diff.fields_added" :key="field.name" class="px-3 py-3 rounded-lg font-mono text-sm bg-[#f0fdf4] border-l-4 border-[#10b981]">
            <div class="flex items-center gap-2">
              <span class="font-bold text-base text-[#10b981] w-4 flex-shrink-0">+</span>
              <code class="font-bold text-[var(--color-text-primary)] bg-black/5 px-1.5 py-0.5 rounded">{{ field.name }}</code>
              <span class="text-xs text-[var(--color-text-secondary)]">({{ getFieldSpec(field) }})</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.fields_removed?.length > 0" class="mb-4">
        <h5 class="flex items-center gap-2 m-0 mb-3 text-sm font-bold uppercase tracking-wide text-[#dc2626]">
          <Icon name="minus" :size="16" />
          Removed Fields
        </h5>
        <div class="flex flex-col gap-2">
          <div v-for="field in diff.fields_removed" :key="field.name" class="px-3 py-3 rounded-lg font-mono text-sm bg-[#fef2f2] border-l-4 border-[#ef4444]">
            <div class="flex items-center gap-2">
              <span class="font-bold text-base text-[#ef4444] w-4 flex-shrink-0">-</span>
              <code class="font-bold text-[var(--color-text-primary)] bg-black/5 px-1.5 py-0.5 rounded">{{ field.name }}</code>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.fields_changed?.length > 0" class="mb-4">
        <h5 class="flex items-center gap-2 m-0 mb-3 text-sm font-bold uppercase tracking-wide text-[#d97706]">
          <Icon name="arrow-path" :size="16" />
          Modified Fields
        </h5>
        <div class="flex flex-col gap-2">
          <div v-for="field in diff.fields_changed" :key="field.name" class="px-3 py-3 rounded-lg font-mono text-sm bg-[#fffbeb] border-l-4 border-[#f59e0b]">
            <div class="flex items-center gap-2">
              <span class="font-bold text-base text-[#f59e0b] w-4 flex-shrink-0">~</span>
              <code class="font-bold text-[var(--color-text-primary)] bg-black/5 px-1.5 py-0.5 rounded">{{ field.name }}</code>
            </div>
            <div class="mt-2 ml-6 pl-3 border-l-2 border-black/10">
              <div v-for="change in getChangedProperties(field.from, field.to)" :key="change" class="text-xs text-[var(--color-text-secondary)] py-0.5">
                {{ change }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.rels_added?.length > 0" class="mb-4">
        <h5 class="flex items-center gap-2 m-0 mb-3 text-sm font-bold uppercase tracking-wide text-[#047857]">
          <Icon name="plus" :size="16" />
          Added Relations
        </h5>
        <div class="flex flex-col gap-2">
          <div v-for="rel in diff.rels_added" :key="rel.field" class="px-3 py-3 rounded-lg font-mono text-sm bg-[#f0fdf4] border-l-4 border-[#10b981]">
            <div class="flex items-center gap-2">
              <span class="font-bold text-base text-[#10b981] w-4 flex-shrink-0">+</span>
              <code class="font-bold text-[var(--color-text-primary)] bg-black/5 px-1.5 py-0.5 rounded">{{ rel.field }}</code>
              <span class="text-[var(--color-primary)] font-semibold">→ {{ rel.target }}</span>
              <span class="text-xs text-[var(--color-text-secondary)]">({{ getRelationSpec(rel) }})</span>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.rels_removed?.length > 0" class="mb-4">
        <h5 class="flex items-center gap-2 m-0 mb-3 text-sm font-bold uppercase tracking-wide text-[#dc2626]">
          <Icon name="minus" :size="16" />
          Removed Relations
        </h5>
        <div class="flex flex-col gap-2">
          <div v-for="rel in diff.rels_removed" :key="rel.field" class="px-3 py-3 rounded-lg font-mono text-sm bg-[#fef2f2] border-l-4 border-[#ef4444]">
            <div class="flex items-center gap-2">
              <span class="font-bold text-base text-[#ef4444] w-4 flex-shrink-0">-</span>
              <code class="font-bold text-[var(--color-text-primary)] bg-black/5 px-1.5 py-0.5 rounded">{{ rel.field }}</code>
            </div>
          </div>
        </div>
      </div>

      <div v-if="diff.rels_changed?.length > 0" class="mb-4">
        <h5 class="flex items-center gap-2 m-0 mb-3 text-sm font-bold uppercase tracking-wide text-[#d97706]">
          <Icon name="arrow-path" :size="16" />
          Modified Relations
        </h5>
        <div class="flex flex-col gap-2">
          <div v-for="rel in diff.rels_changed" :key="rel.field" class="px-3 py-3 rounded-lg font-mono text-sm bg-[#fffbeb] border-l-4 border-[#f59e0b]">
            <div class="flex items-center gap-2">
              <span class="font-bold text-base text-[#f59e0b] w-4 flex-shrink-0">~</span>
              <code class="font-bold text-[var(--color-text-primary)] bg-black/5 px-1.5 py-0.5 rounded">{{ rel.field }}</code>
              <span class="text-[var(--color-primary)] font-semibold">→ {{ rel.to?.target || rel.from?.target }}</span>
            </div>
            <div class="mt-2 ml-6 pl-3 border-l-2 border-black/10">
              <div v-for="change in getRelationChangedProperties(rel.from, rel.to)" :key="change" class="text-xs text-[var(--color-text-secondary)] py-0.5">
                {{ change }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <div v-if="!hasChanges()" class="flex flex-col items-center justify-center px-6 py-6 text-[var(--color-text-tertiary)] text-center">
        <Icon name="check" :size="24" />
        <p class="m-0 mt-2 text-sm">No changes detected</p>
      </div>
    </div>
  </div>
</template>
