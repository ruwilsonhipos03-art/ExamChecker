<template>
  <aside class="sidebar" :class="{ 'collapsed': isCollapsed }">
    <div class="nav-links">
      <div class="section-label" v-if="!isCollapsed">MAIN</div>
      
      <router-link to="/student/dashboard" class="nav-item">
        <i class="bi bi-grid-fill"></i>
        <span v-if="!isCollapsed">Dashboard</span>
      </router-link>

      <!-- <div class="section-label" v-if="!isCollapsed">TABLES</div> -->

      <router-link to="/student/exams" class="nav-item">
        <i class="bi bi-file-earmark-text"></i>
        <span v-if="!isCollapsed">Exams</span>
      </router-link>

      <router-link to="/student/schedules" class="nav-item">
        <i class="bi bi-calendar3"></i>
        <span v-if="!isCollapsed">Schedules</span>
      </router-link>

      <router-link to="/student/recommendations" class="nav-item">
        <i class="bi bi-mortarboard-fill"></i>
        <span v-if="!isCollapsed">Recommendations</span>
      </router-link>

      <router-link to="/student/reports" class="nav-item">
        <i class="bi bi-file-bar-graph-fill"></i>
        <span v-if="!isCollapsed">Reports</span>
      </router-link>

      <router-link to="/student/profile" class="nav-item">
        <i class="bi bi-person-circle"></i>
        <span v-if="!isCollapsed">Profile</span>
      </router-link>

      <!-- <div class="section-label" v-if="!isCollapsed">EXAMS</div> -->
    </div>
  </aside>
</template>

<script setup>
import { ref } from 'vue';
defineProps(['isCollapsed']);

const isNormalOpen = ref(false);


const toggleNormal = () => {
  isNormalOpen.value = !isNormalOpen.value;
  if(isNormalOpen.value) isEntranceOpen.value = false; // Close other dropdown
};
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
</style>
