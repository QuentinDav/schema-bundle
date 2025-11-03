<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import Icon from './Icon.vue'

const route = useRoute()

const navItems = [
  {
    name: 'Schema',
    path: '/schema',
    icon: 'squares-2x2',
    description: 'Visual graph',
  },
  {
    name: 'Cards',
    path: '/cards',
    icon: 'squares-plus',
    description: 'Grid view',
  },
  {
    name: 'List',
    path: '/list',
    icon: 'list-bullet',
    description: 'Table view',
  },
  {
    name: 'Releases',
    path: '/releases',
    icon: 'clock',
    description: 'Version history',
  },
  {
    name: 'Query Builder',
    path: '/query-builder',
    icon: 'command-line',
    description: 'AI-powered SQL',
  },
]

const isActive = computed(() => (path) => {
  return route.path === path || (route.path === '/' && path === '/schema')
})
</script>

<template>
  <aside class="app-sidebar">
    <nav class="nav-menu">
      <router-link
        v-for="item in navItems"
        :key="item.path"
        :to="item.path"
        class="nav-item"
        :class="{ active: isActive(item.path) }"
      >
        <div class="nav-icon">
          <Icon :name="item.icon" :size="24" />
        </div>
        <div class="nav-content">
          <span class="nav-name">{{ item.name }}</span>
          <span class="nav-description">{{ item.description }}</span>
        </div>
        <div class="nav-indicator"></div>
      </router-link>
    </nav>

    <div class="sidebar-footer">
      <div class="footer-info">
        <Icon name="information-circle" :size="16" />
        <span>DB Schema Viewer v1.0</span>
      </div>
    </div>
  </aside>
</template>

<style scoped>
.app-sidebar {
  width: 280px;
  background: white;
  border-right: 1px solid #e5e7eb;
  display: flex;
  flex-direction: column;
  height: 100%;
}

.nav-menu {
  padding: 1.5rem 1rem;
  flex: 1;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem;
  margin-bottom: 0.5rem;
  border-radius: 12px;
  text-decoration: none;
  color: #6b7280;
  transition: all 0.2s ease;
  position: relative;
  cursor: pointer;
}

.nav-item:hover {
  background: #f3f4f6;
  color: #374151;
}

.nav-item.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.nav-icon {
  flex-shrink: 0;
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.nav-content {
  display: flex;
  flex-direction: column;
  flex: 1;
}

.nav-name {
  font-size: 1rem;
  font-weight: 600;
  line-height: 1.2;
}

.nav-description {
  font-size: 0.75rem;
  opacity: 0.7;
  margin-top: 0.125rem;
}

.nav-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: currentColor;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.nav-item.active .nav-indicator {
  opacity: 1;
  animation: pulse 2s infinite;
}

.sidebar-footer {
  padding: 1.5rem 1rem;
  border-top: 1px solid #e5e7eb;
}

.footer-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.875rem;
  color: #9ca3af;
}

@keyframes pulse {
  0%,
  100% {
    opacity: 1;
  }
  50% {
    opacity: 0.5;
  }
}

@media (max-width: 768px) {
  .app-sidebar {
    width: 80px;
  }

  .nav-content,
  .sidebar-footer span {
    display: none;
  }

  .nav-item {
    justify-content: center;
  }
}
</style>
