<script setup>
import { ref, onMounted, computed } from 'vue'
import { useReleasesStore } from '@/stores/releases'
import Icon from '@/components/Icon.vue'

const releasesStore = useReleasesStore()
const searchQuery = ref('')

onMounted(() => {
  releasesStore.fetchReleases()
})

function formatDate(dateString) {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

function exportMarkdown(releaseId) {
  const url = `/schema-doc/api/releases/${releaseId}/export/markdown`
  window.open(url, '_blank')
}

const filteredReleases = computed(() => {
  let releases = releasesStore.sortedReleases

  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    releases = releases.filter(r =>
      r.name.toLowerCase().includes(query) ||
      r.description?.toLowerCase().includes(query) ||
      r.created_by?.toLowerCase().includes(query)
    )
  }

  return releases
})

const groupedReleases = computed(() => {
  const groups = {}
  const now = new Date()

  filteredReleases.value.forEach(release => {
    const date = new Date(release.created_at)
    const diffDays = Math.floor((now - date) / (1000 * 60 * 60 * 24))

    let groupKey
    if (diffDays === 0) groupKey = 'Today'
    else if (diffDays === 1) groupKey = 'Yesterday'
    else if (diffDays < 7) groupKey = 'This Week'
    else if (diffDays < 30) groupKey = 'This Month'
    else if (diffDays < 90) groupKey = 'Last 3 Months'
    else groupKey = 'Older'

    if (!groups[groupKey]) groups[groupKey] = []
    groups[groupKey].push(release)
  })

  const orderedGroups = []
  const order = ['Today', 'Yesterday', 'This Week', 'This Month', 'Last 3 Months', 'Older']

  order.forEach(key => {
    if (groups[key]) {
      orderedGroups.push({ name: key, releases: groups[key] })
    }
  })

  return orderedGroups
})
</script>

<template>
  <div class="h-full overflow-auto p-6 bg-[var(--color-background)]">
    <div class="max-w-5xl mx-auto space-y-6">
      <div class="flex items-center justify-between mb-8">
        <div>
          <h1 class="text-3xl font-bold text-[var(--color-text-primary)] mb-2">Schema Releases</h1>
          <p class="text-[var(--color-text-secondary)]">Track and compare schema changes over time</p>
        </div>
      </div>

      <div class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 mb-6">
        <div class="relative">
          <Icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[var(--color-text-tertiary)]" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search releases..."
            class="w-full pl-10 pr-4 py-2.5 bg-[var(--color-surface-raised)] border border-[var(--color-border)] rounded-lg text-sm text-[var(--color-text-primary)] placeholder-[var(--color-text-tertiary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-primary)]"
          />
        </div>
      </div>

      <div v-if="releasesStore.loading && releasesStore.releases.length === 0" class="flex flex-col items-center justify-center py-20">
        <div class="w-12 h-12 border-4 border-[var(--color-border)] border-t-[var(--color-primary)] rounded-full animate-spin"></div>
        <p class="mt-4 text-sm text-[var(--color-text-secondary)]">Loading releases...</p>
      </div>

      <div v-else-if="!releasesStore.loading && releasesStore.releases.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
        <Icon name="clock" class="w-20 h-20 mb-4 text-[var(--color-text-tertiary)]" />
        <h2 class="text-xl font-semibold text-[var(--color-text-primary)] mb-2">No Releases Yet</h2>
        <p class="text-sm text-[var(--color-text-secondary)]">Create your first release to start tracking schema changes over time.</p>
      </div>

      <div v-else class="space-y-8">
        <div v-for="group in groupedReleases" :key="group.name" class="space-y-4">
          <h2 class="text-lg font-semibold text-[var(--color-text-primary)] flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[var(--color-primary)]"></span>
            {{ group.name }}
          </h2>

          <div class="space-y-3 pl-4 border-l-2 border-[var(--color-border)]">
            <div
              v-for="release in group.releases"
              :key="release.id"
              class="bg-[var(--color-surface)] border border-[var(--color-border)] rounded-lg p-4 hover:border-[var(--color-primary)] hover:shadow-lg transition-all group relative -ml-4"
            >
              <div class="absolute -left-3 top-6 w-4 h-4 rounded-full bg-[var(--color-primary)] border-4 border-[var(--color-background)] group-hover:scale-125 transition-transform"></div>

              <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                  <div class="flex items-center gap-3 mb-2">
                    <h3 class="text-lg font-semibold text-[var(--color-text-primary)]">{{ release.name }}</h3>
                    <span class="px-2.5 py-0.5 bg-[var(--color-primary-light)] text-[var(--color-primary)] text-xs font-semibold rounded-full">
                      v{{ release.version }}
                    </span>
                  </div>

                  <p v-if="release.description" class="text-sm text-[var(--color-text-secondary)] mb-3">
                    {{ release.description }}
                  </p>

                  <div class="flex flex-wrap items-center gap-4 text-xs text-[var(--color-text-tertiary)]">
                    <div class="flex items-center gap-1.5">
                      <Icon name="clock" class="w-4 h-4" />
                      <span>{{ formatDate(release.created_at) }}</span>
                    </div>
                    <div v-if="release.created_by" class="flex items-center gap-1.5">
                      <Icon name="user-group" class="w-4 h-4" />
                      <span>{{ release.created_by }}</span>
                    </div>
                    <div v-if="release.snapshot_data" class="flex items-center gap-3">
                      <div class="flex items-center gap-1">
                        <span class="font-semibold text-[var(--color-text-primary)]">{{ Object.keys(release.snapshot_data.entities || {}).length }}</span>
                        <span>tables</span>
                      </div>
                    </div>
                  </div>
                </div>

                <button
                  @click="exportMarkdown(release.id)"
                  class="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-[var(--color-primary)] hover:bg-[var(--color-primary-light)] rounded-lg transition-all"
                >
                  <Icon name="arrow-down-tray" class="w-4 h-4" />
                  Export
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
