<script setup>
import { ref, onMounted, computed } from 'vue'
import { useReleasesStore } from '@/stores/releases'
import Icon from '@/components/Icon.vue'
import EntityDiff from '@/components/EntityDiff.vue'

const releasesStore = useReleasesStore()

const view = ref('timeline') // 'timeline', 'detail', 'compare', 'entity-history'
const selectedRelease1 = ref(null)
const selectedRelease2 = ref(null)
const isCompareMode = ref(false)
const timelineSearchQuery = ref('')

onMounted(() => {
  releasesStore.fetchReleases()
})

function viewReleaseDetail(release) {
  releasesStore.fetchReleaseDetail(release.id)
  view.value = 'detail'
}

function compareReleases() {
  if (selectedRelease1.value && selectedRelease2.value) {
    releasesStore.compareReleasesAction(selectedRelease1.value.id, selectedRelease2.value.id)
    view.value = 'compare'
  }
}

function backToTimeline() {
  view.value = 'timeline'
  releasesStore.clearReleaseDetail()
  releasesStore.clearCompare()
  selectedRelease1.value = null
  selectedRelease2.value = null
  isCompareMode.value = false
}

function enableCompareMode() {
  isCompareMode.value = true
  selectedRelease1.value = null
  selectedRelease2.value = null
}

function cancelCompareMode() {
  isCompareMode.value = false
  selectedRelease1.value = null
  selectedRelease2.value = null
}

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

// Filtered and grouped releases for timeline
const filteredReleases = computed(() => {
  let releases = releasesStore.sortedReleases

  if (timelineSearchQuery.value) {
    const query = timelineSearchQuery.value.toLowerCase()
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
    if (diffDays === 0) {
      groupKey = 'Today'
    } else if (diffDays === 1) {
      groupKey = 'Yesterday'
    } else if (diffDays < 7) {
      groupKey = 'This Week'
    } else if (diffDays < 30) {
      groupKey = 'This Month'
    } else if (diffDays < 90) {
      groupKey = 'Last 3 Months'
    } else {
      groupKey = 'Older'
    }

    if (!groups[groupKey]) {
      groups[groupKey] = []
    }
    groups[groupKey].push(release)
  })

  // Return in specific order
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
  <div class="releases-view">
    <!-- Header -->
    <div class="view-header">
      <div>
        <h1>Schema Releases</h1>
        <p class="subtitle">Track and compare schema changes over time</p>
      </div>
      <div v-if="view !== 'timeline'" class="back-btn-container">
        <button @click="backToTimeline" class="back-btn hover-lift">
          <Icon name="arrow-left" />
          Back to Timeline
        </button>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="releasesStore.loading && releasesStore.releases.length === 0" class="loading-state">
      <div class="spinner"></div>
      <p>Loading releases...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="!releasesStore.loading && releasesStore.releases.length === 0" class="empty-state">
      <Icon name="clock" :size="80" class="empty-icon" />
      <h2>No Releases Yet</h2>
      <p>Create your first release to start tracking schema changes over time.</p>
      <button @click="showCreateModal = true" class="btn-create-first">
        <Icon name="plus" :size="20" />
        Create First Release
      </button>
    </div>

    <!-- Timeline View -->
    <div v-else-if="view === 'timeline'" class="timeline-view">
      <!-- Search Bar -->
      <div class="timeline-search">
        <Icon name="magnifying-glass" :size="20" class="search-icon" />
        <input
          v-model="timelineSearchQuery"
          type="text"
          placeholder="Search releases by name, description, or author..."
          class="search-input"
        />
        <button v-if="timelineSearchQuery" @click="timelineSearchQuery = ''" class="clear-btn">
          <Icon name="x-mark" :size="16" />
        </button>
      </div>

      <!-- Compare Mode Toggle -->
      <div class="compare-toggle">
        <div v-if="isCompareMode" class="compare-mode-active">
          <span>Select two releases to compare</span>
          <button
            @click="compareReleases"
            class="btn-compare"
            :disabled="!selectedRelease1 || !selectedRelease2"
          >
            Compare
          </button>
          <button @click="cancelCompareMode" class="btn-cancel">
            Cancel
          </button>
        </div>
        <button v-else @click="enableCompareMode" class="btn-compare-toggle">
          Compare Releases
        </button>
      </div>

      <!-- No Results -->
      <div v-if="filteredReleases.length === 0" class="no-results">
        <Icon name="magnifying-glass" :size="64" class="no-results-icon" />
        <h3>No releases found</h3>
        <p>Try adjusting your search query</p>
        <button v-if="timelineSearchQuery" @click="timelineSearchQuery = ''" class="btn-clear-search">
          Clear search
        </button>
      </div>

      <!-- Releases Timeline (Grouped) -->
      <div v-else class="timeline">
        <div v-for="group in groupedReleases" :key="group.name" class="timeline-group">
          <div class="group-header">
            <h3>{{ group.name }}</h3>
            <span class="group-count">{{ group.releases.length }} release{{ group.releases.length > 1 ? 's' : '' }}</span>
          </div>

          <div
            v-for="(release, index) in group.releases"
            :key="release.id"
            class="timeline-item"
            :class="{
              selected: selectedRelease1?.id === release.id || selectedRelease2?.id === release.id
            }"
          >
            <div class="timeline-marker">
              <div class="marker-dot"></div>
              <div v-if="index < group.releases.length - 1" class="marker-line"></div>
            </div>

            <div class="timeline-content">
              <div class="release-card">
                <div class="release-header">
                  <div>
                    <h3>{{ release.name }}</h3>
                    <p class="release-date">{{ formatDate(release.created_at) }}</p>
                    <p v-if="release.created_by" class="release-author">by {{ release.created_by }}</p>
                  </div>
                  <div class="release-actions">
                    <button
                      v-if="isCompareMode"
                      @click="selectedRelease1 ? (selectedRelease2 = release) : (selectedRelease1 = release)"
                      class="btn-select"
                      :class="{ active: selectedRelease1?.id === release.id || selectedRelease2?.id === release.id }"
                    >
                      {{ selectedRelease1?.id === release.id ? '1' : selectedRelease2?.id === release.id ? '2' : 'Select' }}
                    </button>
                    <button @click="viewReleaseDetail(release)" class="btn-view">
                      View Details
                    </button>
                  </div>
                </div>

                <p v-if="release.description" class="release-description">{{ release.description }}</p>

                <div class="release-stats">
                  <div class="stat">
                    <span class="stat-value">{{ release.total_entities }}</span>
                    <span class="stat-label">Total Entities</span>
                  </div>
                  <div v-if="release.changed_entities > 0" class="stat stat-changed">
                    <span class="stat-value">{{ release.changed_entities }}</span>
                    <span class="stat-label">Changed</span>
                  </div>
                  <div v-if="release.added_entities > 0" class="stat stat-added">
                    <span class="stat-value">{{ release.added_entities }}</span>
                    <span class="stat-label">Added</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Release Detail View -->
    <div v-else-if="view === 'detail' && releasesStore.selectedReleaseDetail" class="detail-view">
      <div class="detail-header">
        <div class="detail-header-content">
          <div>
            <h2>{{ releasesStore.selectedReleaseDetail.name }}</h2>
            <p class="detail-date">{{ formatDate(releasesStore.selectedReleaseDetail.created_at) }}</p>
            <p v-if="releasesStore.selectedReleaseDetail.description" class="detail-description">
              {{ releasesStore.selectedReleaseDetail.description }}
            </p>
          </div>
          <button @click="exportMarkdown(releasesStore.selectedReleaseDetail.id)" class="btn-export">
            <Icon name="arrow-down-tray" :size="18" />
            Export Markdown
          </button>
        </div>
      </div>

      <div class="detail-summary">
        <div class="summary-card">
          <span class="summary-value">{{ releasesStore.selectedReleaseDetail.summary.total_entities }}</span>
          <span class="summary-label">Total Entities</span>
        </div>
        <div class="summary-card changed">
          <span class="summary-value">{{ releasesStore.selectedReleaseDetail.summary.changed_entities }}</span>
          <span class="summary-label">Changed</span>
        </div>
        <div class="summary-card added">
          <span class="summary-value">{{ releasesStore.selectedReleaseDetail.summary.added_entities }}</span>
          <span class="summary-label">Added</span>
        </div>
      </div>

      <!-- Changed Entities -->
      <div v-if="releasesStore.selectedReleaseDetail.changed_entities.length > 0" class="entities-section">
        <h3>Changed Entities</h3>
        <div class="entities-list">
          <EntityDiff
            v-for="entity in releasesStore.selectedReleaseDetail.changed_entities"
            :key="entity.entity_fqcn"
            :entity-name="entity.entity_name"
            :diff="entity.diff"
            :old-schema="entity.old_schema"
            :new-schema="entity.new_schema"
          />
        </div>
      </div>

      <!-- Added Entities -->
      <div v-if="releasesStore.selectedReleaseDetail.added_entities.length > 0" class="entities-section">
        <h3>Added Entities</h3>
        <div class="entities-list">
          <div
            v-for="entity in releasesStore.selectedReleaseDetail.added_entities"
            :key="entity.entity_fqcn"
            class="entity-card added-entity"
          >
            <h4>{{ entity.entity_name }}</h4>
          </div>
        </div>
      </div>
    </div>

    <!-- Compare View -->
    <div v-else-if="view === 'compare' && releasesStore.compareReleases" class="compare-view">
      <div class="compare-header">
        <div class="compare-release">
          <h3>{{ releasesStore.compareReleases.release1.name }}</h3>
          <p>{{ formatDate(releasesStore.compareReleases.release1.created_at) }}</p>
        </div>
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor">
          <path d="M5 12h14M12 5l7 7-7 7" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <div class="compare-release">
          <h3>{{ releasesStore.compareReleases.release2.name }}</h3>
          <p>{{ formatDate(releasesStore.compareReleases.release2.created_at) }}</p>
        </div>
      </div>

      <div class="compare-summary">
        <div class="summary-card">
          <span class="summary-value">{{ releasesStore.compareReleases.summary.total_changes }}</span>
          <span class="summary-label">Total Changes</span>
        </div>
        <div class="summary-card added">
          <span class="summary-value">{{ releasesStore.compareReleases.summary.added }}</span>
          <span class="summary-label">Added</span>
        </div>
        <div class="summary-card removed">
          <span class="summary-value">{{ releasesStore.compareReleases.summary.removed }}</span>
          <span class="summary-label">Removed</span>
        </div>
        <div class="summary-card changed">
          <span class="summary-value">{{ releasesStore.compareReleases.summary.modified }}</span>
          <span class="summary-label">Modified</span>
        </div>
      </div>

      <!-- Modified Entities -->
      <div v-if="releasesStore.compareReleases.modified_entities.length > 0" class="entities-section">
        <h3>Modified Entities</h3>
        <div class="entities-list">
          <EntityDiff
            v-for="entity in releasesStore.compareReleases.modified_entities"
            :key="entity.entity_fqcn"
            :entity-name="entity.entity_name"
            :diff="entity.diff"
            :old-schema="entity.old_schema"
            :new-schema="entity.new_schema"
          />
        </div>
      </div>

      <!-- Added Entities -->
      <div v-if="releasesStore.compareReleases.added_entities.length > 0" class="entities-section">
        <h3>Added Entities</h3>
        <div class="entities-grid">
          <div
            v-for="entity in releasesStore.compareReleases.added_entities"
            :key="entity.entity_fqcn"
            class="entity-tag added"
          >
            {{ entity.entity_name }}
          </div>
        </div>
      </div>

      <!-- Removed Entities -->
      <div v-if="releasesStore.compareReleases.removed_entities.length > 0" class="entities-section">
        <h3>Removed Entities</h3>
        <div class="entities-grid">
          <div
            v-for="entity in releasesStore.compareReleases.removed_entities"
            :key="entity.entity_fqcn"
            class="entity-tag removed"
          >
            {{ entity.entity_name }}
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.releases-view {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.view-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.view-header h1 {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.subtitle {
  color: #6b7280;
  margin: 0;
}

.back-btn {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.25rem;
  background: #f3f4f6;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.back-btn:hover {
  background: #e5e7eb;
}

/* Loading & Empty States */
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.spinner-large {
  width: 48px;
  height: 48px;
  border: 4px solid #f3f4f6;
  border-top-color: #667eea;
  border-radius: 50%;
  animation: spin 1s linear infinite;
  margin-bottom: 1rem;
}

.empty-state svg {
  color: #d1d5db;
  margin-bottom: 1rem;
}

.empty-state h2 {
  font-size: 1.5rem;
  color: #374151;
  margin: 0 0 0.5rem 0;
}

.empty-state p {
  color: #6b7280;
  margin: 0 0 1.5rem 0;
}

.btn-create-first {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1.75rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.9375rem;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
}

.btn-create-first:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
}

/* Timeline Search */
.timeline-search {
  position: relative;
  display: flex;
  align-items: center;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 0.75rem 1rem;
  margin-bottom: 1.5rem;
  transition: all 0.2s ease;
}

.timeline-search:focus-within {
  border-color: #667eea;
  box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
}

.timeline-search .search-icon {
  color: #9ca3af;
  margin-right: 0.75rem;
}

.timeline-search .search-input {
  flex: 1;
  border: none;
  background: transparent;
  font-size: 0.875rem;
  outline: none;
  color: #111827;
}

.timeline-search .search-input::placeholder {
  color: #9ca3af;
}

.timeline-search .clear-btn {
  background: none;
  border: none;
  padding: 0.25rem;
  cursor: pointer;
  color: #9ca3af;
  display: flex;
  align-items: center;
  border-radius: 4px;
  transition: all 0.2s ease;
}

.timeline-search .clear-btn:hover {
  background: #f3f4f6;
  color: #6b7280;
}

/* No Results */
.no-results {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
}

.no-results-icon {
  color: #d1d5db;
  margin-bottom: 1rem;
}

.no-results h3 {
  font-size: 1.5rem;
  color: #374151;
  margin: 0 0 0.5rem 0;
}

.no-results p {
  color: #6b7280;
  margin: 0 0 1.5rem 0;
}

.btn-clear-search {
  padding: 0.75rem 1.5rem;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-clear-search:hover {
  background: #5568d3;
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Timeline */
.compare-toggle {
  margin-bottom: 2rem;
}

.timeline-group {
  margin-bottom: 2rem;
}

.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 1rem 1.5rem;
  background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
  border-radius: 12px;
  margin-bottom: 1rem;
  border-left: 4px solid #667eea;
}

.group-header h3 {
  font-size: 1.125rem;
  font-weight: 700;
  color: #667eea;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.group-count {
  font-size: 0.875rem;
  color: #6b7280;
  font-weight: 600;
}

.compare-mode-active {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.5rem;
  background: #fef3c7;
  border: 1px solid #fde68a;
  border-radius: 8px;
  color: #92400e;
  font-weight: 600;
}

.btn-compare-toggle,
.btn-compare,
.btn-cancel {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-compare-toggle {
  background: #667eea;
  color: white;
}

.btn-compare {
  background: #10b981;
  color: white;
}

.btn-compare:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-cancel {
  background: #f3f4f6;
  color: #374151;
}

.timeline {
  position: relative;
}

.timeline-item {
  display: flex;
  gap: 2rem;
  margin-bottom: 2rem;
  transition: all 0.3s ease;
}

.timeline-item.selected {
  transform: scale(1.02);
}

.timeline-marker {
  position: relative;
  display: flex;
  flex-direction: column;
  align-items: center;
  flex-shrink: 0;
}

.marker-dot {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #667eea;
  border: 4px solid white;
  box-shadow: 0 0 0 2px #667eea;
  z-index: 1;
}

.marker-line {
  width: 2px;
  flex: 1;
  background: #e5e7eb;
  margin-top: 0.5rem;
}

.timeline-content {
  flex: 1;
}

.release-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  transition: all 0.3s ease;
}

.release-card:hover {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.release-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 1rem;
}

.release-header h3 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0;
}

.release-date {
  font-size: 0.875rem;
  color: #6b7280;
  margin: 0.25rem 0 0 0;
}

.release-author {
  font-size: 0.75rem;
  color: #9ca3af;
  margin: 0.25rem 0 0 0;
}

.release-actions {
  display: flex;
  gap: 0.5rem;
}

.btn-select,
.btn-view {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
}

.btn-select {
  background: #f3f4f6;
  color: #374151;
}

.btn-select.active {
  background: #667eea;
  color: white;
}

.btn-view {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-view:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.release-description {
  color: #6b7280;
  margin: 0 0 1rem 0;
  line-height: 1.6;
}

.release-stats {
  display: flex;
  gap: 2rem;
}

.stat {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #374151;
}

.stat-label {
  font-size: 0.75rem;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.stat-changed .stat-value {
  color: #f59e0b;
}

.stat-added .stat-value {
  color: #10b981;
}

/* Detail View */
.detail-header {
  background: white;
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  border: 1px solid #e5e7eb;
}

.detail-header-content {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 2rem;
}

.detail-header h2 {
  font-size: 2rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.detail-date {
  color: #6b7280;
  margin: 0 0 1rem 0;
}

.detail-description {
  color: #374151;
  line-height: 1.6;
  margin: 0;
}

.btn-export {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1.5rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  cursor: pointer;
  transition: all 0.2s ease;
  white-space: nowrap;
}

.btn-export:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 16px rgba(102, 126, 234, 0.4);
}

.detail-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.summary-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  flex-direction: column;
  align-items: center;
}

.summary-value {
  font-size: 2.5rem;
  font-weight: 700;
  color: #374151;
}

.summary-label {
  font-size: 0.875rem;
  color: #9ca3af;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  margin-top: 0.5rem;
}

.summary-card.changed {
  border-color: #fbbf24;
  background: #fffbeb;
}

.summary-card.changed .summary-value {
  color: #f59e0b;
}

.summary-card.added {
  border-color: #34d399;
  background: #ecfdf5;
}

.summary-card.added .summary-value {
  color: #10b981;
}

.summary-card.removed {
  border-color: #fca5a5;
  background: #fef2f2;
}

.summary-card.removed .summary-value {
  color: #ef4444;
}

/* Entities Sections */
.entities-section {
  margin-bottom: 2rem;
}

.entities-section h3 {
  font-size: 1.25rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 1rem 0;
}

.entities-list {
  display: grid;
  gap: 1rem;
}

.entity-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 1rem 1.5rem;
}

.entity-card h4 {
  font-size: 1rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.75rem 0;
}

.changed-entity {
  border-left: 4px solid #f59e0b;
}

.added-entity {
  border-left: 4px solid #10b981;
}

.diff-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.diff-badge {
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  font-size: 0.75rem;
  font-weight: 600;
}

.diff-badge.added {
  background: #ecfdf5;
  color: #047857;
}

.diff-badge.removed {
  background: #fef2f2;
  color: #dc2626;
}

.diff-badge.modified {
  background: #fffbeb;
  color: #d97706;
}

.entities-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
}

.entity-tag {
  padding: 0.5rem 1rem;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
}

.entity-tag.added {
  background: #ecfdf5;
  color: #047857;
  border: 1px solid #34d399;
}

.entity-tag.removed {
  background: #fef2f2;
  color: #dc2626;
  border: 1px solid #fca5a5;
}

/* Compare View */
.compare-header {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 2rem;
  background: white;
  border-radius: 12px;
  padding: 2rem;
  margin-bottom: 2rem;
  border: 1px solid #e5e7eb;
}

.compare-release {
  text-align: center;
}

.compare-release h3 {
  font-size: 1.5rem;
  font-weight: 700;
  color: #111827;
  margin: 0 0 0.5rem 0;
}

.compare-release p {
  color: #6b7280;
  font-size: 0.875rem;
  margin: 0;
}

.compare-header svg {
  color: #667eea;
}

.compare-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

@keyframes spin {
  to {
    transform: rotate(360deg);
  }
}
</style>
