<template>
    <aside class="sidebar" :class="{ 'collapsed': isCollapsed }">
        <div class="nav-links">
            <div class="section-label" v-if="!isCollapsed">MAIN</div>

            <router-link to="/instructor/dashboard" class="nav-item">
                <i class="bi bi-grid-fill"></i>
                <span v-if="!isCollapsed">Dashboard</span>
            </router-link>

            <div class="section-label" v-if="!isCollapsed">TABLES</div>

            <router-link to="/instructor/students" class="nav-item">
                <i class="bi bi-people-fill"></i>
                <span v-if="!isCollapsed">Students</span>
            </router-link>

            <router-link to="/instructor/subjects" class="nav-item">
                <i class="bi bi-book-half"></i>
                <span v-if="!isCollapsed">Subjects</span>
            </router-link>

            <div class="section-label" v-if="!isCollapsed">EXAMS</div>

            <div class="nav-group">
                <button @click="toggleNormal" class="nav-item btn-dropdown"
                    :class="{ 'active': isNormalOpen && !isCollapsed }">
                    <div class="d-flex align-items-center gap-3">
                        <i class="bi bi-journal-text"></i>
                        <span v-if="!isCollapsed">Term Exams</span>
                    </div>
                    <i v-if="!isCollapsed" class="bi bi-chevron-down ms-auto arrow"
                        :class="{ 'rotate': isNormalOpen }"></i>
                </button>

                <div class="sub-nav" v-if="isNormalOpen && !isCollapsed">
                    <router-link to="/instructor/exams" class="sub-item">
                        <i class="bi bi-file-earmark-text"></i> Exam
                    </router-link>

                    <router-link to="/instructor/keys" class="sub-item">
                        <i class="bi bi-key-fill"></i> Answer Key
                    </router-link>
                    <router-link to="/instructor/generate" class="sub-item">
                        <i class="bi bi-file-earmark-plus-fill"></i> Generate Sheet
                    </router-link>
                    <router-link to="/instructor/reports" class="sub-item">
                        <i class="bi bi-file-bar-graph-fill"></i> Reports
                    </router-link>
                    <router-link to="/instructor/analysis" class="sub-item">
                        <i class="bi bi-bar-chart-line-fill"></i> Analysis
                    </router-link>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { ref } from 'vue';
defineProps(['isCollapsed']);

const isNormalOpen = ref(false);


const toggleNormal = () => {
    isNormalOpen.value = !isNormalOpen.value;
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

.sidebar.collapsed {
    width: 80px;
}

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

.router-link-active,
.active {
    background: var(--primary-color) !important;
    color: white !important;
}

.btn-dropdown {
    cursor: pointer;
}

.arrow {
    transition: transform 0.3s ease;
    font-size: 0.8rem;
}

.arrow.rotate {
    transform: rotate(180deg);
}

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

.sub-item i {
    font-size: 1rem;
}

.sub-item:hover {
    color: white;
}

.sub-item.router-link-active {
    background: transparent !important;
    color: #34d399 !important;
    box-shadow: none;
    font-weight: 600;
}

.section-label {
    color: var(--primary-color);
    font-size: 0.75rem;
    font-weight: 600;
    margin: 20px 0 10px 10px;
    letter-spacing: 1px;
}
</style>
