<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, College Dean! 👋</h1>
                <p class="text-muted mb-0">Manage your exams and students here.</p>
            </div>

            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
                <div class="small text-muted">System Status: Online</div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-12">
                <div class="action-card card border-0 shadow-sm p-4 rounded-4 bg-white">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div class="action-text">
                            <h3 class="fw-bold mb-1">Ready to check exams?</h3>
                            <p class="text-muted mb-0">Start the optical scanner to process new student answer sheets.
                            </p>
                        </div>
                        <button class="btn btn-scan d-flex align-items-center gap-3 px-5 py-3 rounded-3 shadow">
                            <i class="bi bi-qr-code-scan fs-2"></i>
                            <span class="fs-4 fw-bold">START SCANNING</span>
                        </button>
                    </div>
                </div>
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

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden bg-white">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">Recent Activity</h5>
                <a href="#" class="text-emerald text-decoration-none small fw-bold">View All</a>
            </div>
            <div class="card-body p-0">
                <div v-for="a in activities" :key="a.id" class="p-4 border-bottom d-flex align-items-center gap-3">
                    <div class="p-2 rounded-3 bg-emerald-light text-emerald">
                        <i :class="a.icon"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold text-dark">{{ a.title }}</div>
                        <div class="small text-muted">{{ a.time }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';

const currentTime = ref('');

const stats = ref([
    { label: 'Exams Created', value: '0', icon: 'bi-file-earmark-plus-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Total Examinees', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald' },
    { label: 'Subjects', value: '0', icon: 'bi-book-fill', colorClass: 'bg-info-subtle text-info' },
    { label: 'Passing Rate', value: '0%', icon: 'bi-graph-up-arrow', colorClass: 'bg-warning-subtle text-warning' }
]);

const activities = [
    { id: 1, title: 'Entrance Exam - Batch 2024 generated', time: '2 hours ago', icon: 'bi-file-earmark-check' },
    { id: 2, title: '45 new students enrolled', time: '5 hours ago', icon: 'bi-person-plus' }
];

const loadStats = async () => {
    try {
        const { data } = await axios.get('/api/dept_head/dashboard/stats');
        stats.value = [
            { label: 'Exams Created', value: Number(data.exams_created || 0).toLocaleString(), icon: 'bi-file-earmark-plus-fill', colorClass: 'bg-emerald-light text-emerald' },
            { label: 'Total Examinees', value: Number(data.total_examinees || 0).toLocaleString(), icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald' },
            { label: 'Subjects', value: Number(data.subjects || 0).toLocaleString(), icon: 'bi-book-fill', colorClass: 'bg-info-subtle text-info' },
            { label: 'Passing Rate', value: `${Number(data.passing_rate || 0).toFixed(2)}%`, icon: 'bi-graph-up-arrow', colorClass: 'bg-warning-subtle text-warning' }
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
    loadStats();
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
