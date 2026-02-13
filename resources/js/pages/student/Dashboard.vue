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
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <div :class="['stat-icon', stat.colorClass]">
                            <i :class="stat.icon"></i>
                        </div>
                        <div v-if="isRefreshing" class="spinner-grow spinner-grow-sm text-emerald" role="status"></div>
                    </div>

                    <div class="stat-value h2 fw-bold mb-1">
                        <template v-if="isLoading && stat.value === '0'">
                            <span class="placeholder col-6"></span>
                        </template>
                        <template v-else>
                            {{ stat.value }}
                        </template>
                    </div>
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

// State Management
const currentTime = ref('');
const firstName = ref('Admin');
const isLoading = ref(true);
const isRefreshing = ref(false);
let statsRefreshTimer = null;

// Initial Stats Structure
const stats = ref([
    { label: 'Total Employees', value: '0', icon: 'bi-people-fill', colorClass: 'bg-emerald-light text-emerald', key: 'total_employees' },
    { label: 'Total Students', value: '0', icon: 'bi-person-badge-fill', colorClass: 'bg-emerald-light text-emerald', key: 'total_students' },
    { label: 'Departments', value: '0', icon: 'bi-building-fill', colorClass: 'bg-emerald-light text-emerald', key: 'departments' },
    { label: 'Programs', value: '0', icon: 'bi-journal-bookmark-fill', colorClass: 'bg-emerald-light text-emerald', key: 'programs' }
]);

/**
 * Formats raw numbers from backend into readable strings (e.g. 1200 -> 1,200)
 */
const normalizeCount = (payload, key) => {
    const value = payload?.[key];
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed.toLocaleString() : '0';
};

/**
 * Communicates with Backend: DashboardStatsController@admin
 */
const loadStats = async (silent = false) => {
    if (!silent) isLoading.value = true;
    isRefreshing.value = true;

    try {
        // Hits Route: Route::get('dashboard/stats', [DashboardStatsController::class, 'admin'])
        const { data } = await axios.get('/api/admin/dashboard/stats');

        // Map backend response to our local stats array
        stats.value.forEach(stat => {
            stat.value = normalizeCount(data, stat.key);
        });

    } catch (error) {
        console.error("Communication failed:", error);
        if (!silent) {
            Swal.fire({
                icon: 'error',
                title: 'Sync Error',
                text: 'Could not fetch dashboard statistics from the server.',
                confirmButtonColor: '#10b981'
            });
        }
    } finally {
        isLoading.value = false;
        isRefreshing.value = false;
    }
};

onMounted(() => {
    // 1. Set Clock
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);

    // 2. Load User Profile
    const savedUser = localStorage.getItem('user_data');
    if (savedUser) {
        const parsedUser = JSON.parse(savedUser);
        firstName.value = parsedUser?.first_name || 'Admin';
    }

    // 3. Initial Backend Communication
    loadStats();

    // 4. Auto-Refresh every 30 seconds (Communication Polling)
    statsRefreshTimer = setInterval(() => loadStats(true), 30000);
});

onBeforeUnmount(() => {
    if (statsRefreshTimer) clearInterval(statsRefreshTimer);
});
</script>

<style scoped>
.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
}

.stat-card {
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 15px -3px rgba(16, 185, 129, 0.1), 0 4px 6px -2px rgba(16, 185, 129, 0.05) !important;
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

/* Placeholder animation for loading */
.placeholder {
    display: inline-block;
    min-height: 1em;
    vertical-align: middle;
    cursor: wait;
    background-color: #e9ecef;
    opacity: .5;
    border-radius: 4px;
    animation: placeholder-glow 2s ease-in-out infinite;
}

@keyframes placeholder-glow {
    50% {
        opacity: .2;
    }
}
</style>
