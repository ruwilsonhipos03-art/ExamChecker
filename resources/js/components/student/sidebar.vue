<template>
  <aside class="sidebar" :class="{ 'collapsed': isCollapsed }">
    <div class="nav-links">
      <div class="section-label" v-if="!isCollapsed">MAIN</div>
      
      <router-link to="/student/dashboard" class="nav-item">
        <i class="bi bi-grid-fill"></i>
        <span v-if="!isCollapsed">Dashboard</span>
      </router-link>

      <!-- <div class="section-label" v-if="!isCollapsed">TABLES</div> -->

      <router-link
        to="/student/exams"
        class="nav-item"
        :class="{ 'has-dot': hasDot('exams') && !activeTabs.exams.value }"
        @click="handleNavClick('exams')"
      >
        <i class="bi bi-file-earmark-text"></i>
        <span v-if="!isCollapsed">Exams</span>
      </router-link>

      <router-link
        to="/student/schedules"
        class="nav-item"
        :class="{ 'has-dot': hasDot('schedules') && !activeTabs.schedules.value }"
        @click="handleNavClick('schedules')"
      >
        <i class="bi bi-calendar3"></i>
        <span v-if="!isCollapsed">Schedules</span>
      </router-link>

      <router-link
        to="/student/subjects"
        class="nav-item"
        :class="{ 'has-dot': hasDot('subjects') && !activeTabs.subjects.value }"
        @click="handleNavClick('subjects')"
      >
        <i class="bi bi-book-half"></i>
        <span v-if="!isCollapsed">Subjects</span>
      </router-link>

      <router-link
        to="/student/recommendations"
        class="nav-item"
        :class="{ 'has-dot': hasDot('recommendations') && !activeTabs.recommendations.value }"
        @click="handleNavClick('recommendations')"
      >
        <i class="bi bi-mortarboard-fill"></i>
        <span v-if="!isCollapsed">Recommendations</span>
      </router-link>

      <router-link
        to="/student/reports"
        class="nav-item"
        :class="{ 'has-dot': hasDot('reports') && !activeTabs.reports.value }"
        @click="handleNavClick('reports')"
      >
        <i class="bi bi-file-bar-graph-fill"></i>
        <span v-if="!isCollapsed">Reports</span>
      </router-link>

      <!-- <div class="section-label" v-if="!isCollapsed">EXAMS</div> -->
    </div>
  </aside>
</template>

<script setup>
import { computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useNotifications } from '../../composables/useNotifications';
defineProps(['isCollapsed']);

const route = useRoute();
const { hasDot, markSeen, summary } = useNotifications();
const activeTabs = {
  exams: computed(() => route.path === '/student/exams'),
  schedules: computed(() => route.path === '/student/schedules'),
  subjects: computed(() => route.path === '/student/subjects'),
  recommendations: computed(() => route.path === '/student/recommendations'),
  reports: computed(() => route.path === '/student/reports'),
};

const handleNavClick = (tabKey) => {
  const latest = summary.value?.tabs?.[tabKey]?.latest_at || null;
  markSeen(tabKey, latest || new Date().toISOString());
};

Object.entries(activeTabs).forEach(([tabKey, isActive]) => {
  watch(
    [isActive, () => summary.value?.tabs?.[tabKey]?.latest_at || null],
    ([active, latest]) => {
      if (!active) return;
      markSeen(tabKey, latest || new Date().toISOString());
    },
    { immediate: true }
  );
});
</script>

<style scoped>
/* Scoped styles exactly matching Admin sidebar */
.sidebar {
  width: 260px;
  background-color: var(--sidebar-bg);
  height: 100%;
  transition: width 0.3s ease;
  padding: 20px 12px;
  overflow-x: hidden;
  white-space: nowrap;
}
.sidebar.collapsed { width: 80px; }

.nav-item {
  display: flex;
  align-items: center;
  gap: 15px;
  padding: 12px 18px;
  color: var(--text-muted);
  text-decoration: none;
  border-radius: 10px;
  margin-bottom: 5px;
  transition: all 0.2s;
  width: 100%;
  border: none;
  background: transparent;
  text-align: left;
  position: relative;
}

.nav-item:hover { 
  background: var(--sidebar-hover);
  color: white; 
}

.router-link-active, .active {
  background: var(--primary-color) !important;
  color: white !important;
}

.btn-dropdown { cursor: pointer; }
.arrow { transition: transform 0.3s ease; font-size: 0.8rem; }
.arrow.rotate { transform: rotate(180deg); }

.sub-nav {
  display: flex;
  flex-direction: column;
  padding-left: 20px;
  margin-bottom: 10px;
}

.sub-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 18px;
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.9rem;
  border-radius: 8px;
  transition: 0.2s;
}

.sub-item i { font-size: 1rem; }
.sub-item:hover { color: white; }
.sub-item.router-link-active { 
  background: transparent !important; 
  color: #34d399 !important;
  box-shadow: none; font-weight: 600; 
}

.section-label {
  color: var(--primary-color);
  font-size: 0.75rem;
  font-weight: 600;
  margin: 20px 0 10px 10px;
  letter-spacing: 1px;
}

.nav-item.has-dot::after {
  content: '';
  position: absolute;
  top: 8px;
  right: 12px;
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #ef4444;
  box-shadow: 0 0 0 2px var(--sidebar-bg);
}
</style>
