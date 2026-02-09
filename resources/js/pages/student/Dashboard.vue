<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, Student! 👋</h1>
                <p class="text-muted mb-0">Manage your exams and students here.</p>
            </div>

            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
                <div class="small text-muted">System Status: Online</div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-md-3" v-for="(stat, index) in stats" :key="index">
                <div class="card border-0 shadow-sm p-4 rounded-4 stat-card h-100 bg-white">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div :class="['stat-icon', stat.colorClass]">
                            <i :class="stat.icon"></i>
                        </div>
                    </div>
                    <div class="stat-value h2 fw-bold mb-1">{{ stat.value }}</div>
                    <div class="stat-label text-muted fw-medium">{{ stat.label }}</div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';

const currentTime = ref('');

// Updated to reflect Program Head metrics
const stats = [
    { label: 'Total Students', value: '1,247', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Exams Completed', value: '156', icon: 'bi-check-circle-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Pending Reviews', value: '23', icon: 'bi-clock-fill', colorClass: 'bg-warning-subtle text-warning' },
    { label: 'Active Subjects', value: '12', icon: 'bi-book-fill', colorClass: 'bg-info-subtle text-info' }
];

const activities = [
    { id: 1, title: 'Entrance Exam - Batch 2024 generated', time: '2 hours ago', icon: 'bi-file-earmark-check' },
    { id: 2, title: '45 new students enrolled', time: '5 hours ago', icon: 'bi-person-plus' }
];

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);
});
</script>

<style scoped>
/* Emerald UI Colors */
.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
}

/* Dashboard Card Animations */
.stat-card {
    transition: transform 0.2s ease-in-out;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.action-card {
    border-left: 6px solid #10b981 !important;
}

.btn-scan {
    background-color: #10b981;
    color: white;
    border: none;
    transition: all 0.3s ease;
}

.btn-scan:hover {
    background-color: #059669;
    transform: scale(1.02);
    color: white;
}

.btn-scan:active {
    transform: scale(0.98);
}

/* Custom Warning/Info states for PH dashboard */
.bg-warning-subtle {
    background-color: #fffbeb;
}

.text-warning {
    color: #f59e0b;
}

.bg-info-subtle {
    background-color: #eff6ff;
}

.text-info {
    color: #3b82f6;
}
</style>
