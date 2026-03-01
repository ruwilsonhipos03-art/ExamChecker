<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, {{ firstName }}!</h1>
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
import { ref, onMounted, onBeforeUnmount } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const currentTime = ref('');
const firstName = ref('Admin');
let statsRefreshTimer = null;

const stats = ref([
    { label: 'Total Employees', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Total Students', value: '0', icon: 'bi-person-badge-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Colleges', value: '0', icon: 'bi-building-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Programs', value: '0', icon: 'bi-journal-bookmark-fill', colorClass: 'bg-emerald-light text-emerald' }
]);

const normalizeCount = (payload, ...keys) => {
    for (const key of keys) {
        const value = payload?.[key];
        const parsed = Number(value);

        if (Number.isFinite(parsed)) {
            return parsed.toLocaleString();
        }
    }

    return '0';
};

const loadStats = async () => {
    try {
        const { data } = await axios.get('/api/admin/dashboard/stats');
        stats.value = [
            { label: 'Total Employees', value: normalizeCount(data, 'total_employees', 'employees'), icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald' },
            { label: 'Total Students', value: normalizeCount(data, 'total_students', 'students'), icon: 'bi-person-badge-fill', colorClass: 'bg-emerald-light text-emerald' },
            { label: 'Colleges', value: normalizeCount(data, 'colleges', 'total_colleges'), icon: 'bi-building-fill', colorClass: 'bg-emerald-light text-emerald' },
            { label: 'Programs', value: normalizeCount(data, 'programs', 'total_programs'), icon: 'bi-journal-bookmark-fill', colorClass: 'bg-emerald-light text-emerald' }
        ];
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Failed to load dashboard stats',
            text: 'Please try refreshing the page.',
            confirmButtonColor: '#ef4444'
        });
    }
};

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);

    const savedUser = localStorage.getItem('user_data');
    if (savedUser) {
        const parsedUser = JSON.parse(savedUser);
        firstName.value = parsedUser?.first_name || 'Admin';
    }

    loadStats();
    statsRefreshTimer = setInterval(loadStats, 30000);
});

onBeforeUnmount(() => {
    if (statsRefreshTimer) {
        clearInterval(statsRefreshTimer);
    }
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
