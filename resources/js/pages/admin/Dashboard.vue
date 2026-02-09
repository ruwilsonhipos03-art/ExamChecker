<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, John! 👋</h1>
                <p class="text-muted mb-0">Here's what's happening today.</p>
            </div>

            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
                <div class="small text-muted">System Status: Online</div>
            </div>
        </div>

        <div class="row g-4">
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

const stats = [
    { label: 'Total Employees', value: '248', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Departments', value: '12', icon: 'bi-building-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Active Programs', value: '36', icon: 'bi-journal-bookmark-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Scheduled Classes', value: '156', icon: 'bi-calendar-check-fill', colorClass: 'bg-emerald-light text-emerald' }
];

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);
});
</script>

<style scoped>
/* Colors shifted to match Emerald/Green theme */
.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
}

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
</style>
