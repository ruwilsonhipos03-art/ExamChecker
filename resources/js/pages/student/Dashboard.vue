<template>
    <div class="dashboard-container">
        <div class="content-header mb-4 d-flex justify-content-between align-items-end">
            <div>
                <h1 class="fw-bold text-dark">Welcome back, {{ firstName }}!</h1>
                <p class="text-muted mb-0">Exam Management & Scanning System</p>
            </div>
            <div class="text-end d-none d-md-block">
                <div class="fw-bold text-dark">{{ currentTime }}</div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-6">
                <div
                    class="card border-0 shadow-sm p-5 rounded-4 h-100 bg-white d-flex flex-column align-items-center justify-content-center text-center">
                    <div class="mb-4">
                        <i class="bi bi-qr-code-scan display-1 text-emerald"></i>
                    </div>
                    <h3 class="fw-bold">Ready to Take Exam?</h3>
                    <p class="text-muted mb-4">Click the button below to open the scanner and process an answer sheet.
                    </p>

                    <button @click="openScannerModal" class="btn btn-emerald btn-lg px-5 py-3 rounded-pill shadow-sm">
                        <i class="bi bi-camera me-2"></i>Launch Scanner
                    </button>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="row g-3">
                    <div class="col-sm-6" v-for="(stat, index) in stats" :key="index">
                        <button
                            type="button"
                            class="card border-0 shadow-sm p-4 rounded-4 stat-card h-100 bg-white text-start"
                            :class="{ 'stat-clickable': Boolean(stat.route) }"
                            :disabled="!stat.route"
                            @click="goToStat(stat)"
                        >
                            <div :class="['stat-icon mb-3', stat.colorClass]">
                                <i :class="stat.icon"></i>
                            </div>
                            <div class="stat-value h3 fw-bold mb-1">
                                {{ isLoading ? '...' : stat.value }}
                            </div>
                            <div class="stat-label text-muted small fw-medium text-uppercase">{{ stat.label }}</div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import axios from 'axios';
import Swal from 'sweetalert2';
import { Html5Qrcode } from "html5-qrcode";
import { useRouter } from 'vue-router';

const currentTime = ref('');
const firstName = ref('Admin');
const isLoading = ref(true);
const user = ref({ email_verified_at: null });
let html5QrCode = null;
let statsRefreshTimer = null;
const router = useRouter();
const isEmailVerified = computed(() => Boolean(user.value?.email_verified_at));

const stats = ref([
    { label: 'Exams Taken', value: '0', icon: 'bi-file-earmark-text', colorClass: 'bg-emerald-light text-emerald', key: 'exams_taken', route: '/student/exams' },
    { label: 'Exams Completed', value: '0', icon: 'bi-check-circle', colorClass: 'bg-emerald-light text-emerald', key: 'exams_completed', route: '/student/reports' },
    { label: 'Subjects', value: '0', icon: 'bi-journal-bookmark', colorClass: 'bg-emerald-light text-emerald', key: 'total_subjects', route: '/student/subjects' },
    { label: 'Passing Rate', value: '0%', icon: 'bi-graph-up', colorClass: 'bg-emerald-light text-emerald', key: 'passing_rate', route: '/student/reports' }
]);

/**
 * SweetAlert2 Modal with Integrated Camera
 */
const openScannerModal = () => {
    if (!isEmailVerified.value) {
        Swal.fire({
            icon: 'warning',
            title: 'Email verification required',
            text: 'Verify your email first before scanning an answer sheet.',
            showCancelButton: true,
            confirmButtonText: 'Go to Profile',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#10b981'
        }).then((result) => {
            if (result.isConfirmed) {
                router.push('/student/profile');
            }
        });
        return;
    }

    Swal.fire({
        title: 'Scan QR Code',
        html: `
            <div id="reader-popup" style="width: 100%; min-height: 300px; border-radius: 15px; overflow: hidden; background: #f8f9fa;"></div>
            <p class="mt-3 text-muted small">Center the QR code within the frame to auto-scan.</p>
        `,
        showCancelButton: false,
        confirmButtonText: '<i class="bi bi-x-circle me-1"></i> Close Camera',
        confirmButtonColor: '#ef4444',
        cancelButtonVisible: false, // Hide default cancel to use confirm as "Close"
        showConfirmButton: true,
        allowOutsideClick: false,
        didOpen: () => {
            // Initialize scanner only after the SWAL DOM is ready
            html5QrCode = new Html5Qrcode("reader-popup");
            const config = { fps: 10, qrbox: { width: 250, height: 250 } };

            html5QrCode.start(
                { facingMode: "environment" },
                config,
                (decodedText) => {
                    // ON SUCCESS:
                    stopAndCloseScanner(decodedText);
                },
                (errorMessage) => {
                    // Ignore constant scanning noise in console
                }
            ).catch(err => {
                Swal.showValidationMessage(`Camera error: ${err}`);
            });
        },
        willClose: () => {
            // Ensure camera turns off if user clicks "Close" or hits Escape
            if (html5QrCode && html5QrCode.isScanning) {
                html5QrCode.stop();
            }
        }
    });
};

const stopAndCloseScanner = async (result) => {
    try {
        if (html5QrCode) {
            await html5QrCode.stop();
        }
        Swal.close(); // Closes the camera popup automatically

        const qrPayload = String(result || '').trim();
        const { data } = await axios.post('/api/student/answer-sheets/scan', { qr_payload: qrPayload });

        Swal.fire({
            icon: 'success',
            title: 'QR Detected!',
            text: data?.message || 'Answer sheet linked successfully.',
            timer: 1800,
            showConfirmButton: false
        });

        await loadStats();
    } catch (err) {
        Swal.fire({
            icon: 'error',
            title: 'Scan failed',
            text: err?.response?.data?.message || 'Unable to link this answer sheet.',
            confirmButtonColor: '#ef4444'
        });
    }
};

const goToStat = (stat) => {
    if (!stat?.route) return;
    router.push(stat.route);
};

const loadStats = async () => {
    isLoading.value = true;
    try {
        const { data } = await axios.get('/api/student/dashboard/stats');
        stats.value.forEach(stat => {
            const raw = data?.[stat.key];
            if (stat.key === 'passing_rate') {
                const rate = Number(raw || 0);
                stat.value = `${rate.toFixed(2)}%`;
            } else {
                stat.value = Number(raw || 0).toLocaleString();
            }
        });
    } catch (error) {
        console.error("Stats load failed", error);
    } finally {
        isLoading.value = false;
    }
};

onMounted(() => {
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    currentTime.value = new Date().toLocaleDateString(undefined, options);

    const savedUser = localStorage.getItem('user_data');
    if (savedUser) {
        const parsedUser = JSON.parse(savedUser);
        user.value = parsedUser;
        firstName.value = parsedUser?.first_name || 'Student';
    } else {
        const sessionUser = sessionStorage.getItem('user_data');
        if (sessionUser) {
            const parsedUser = JSON.parse(sessionUser);
            user.value = parsedUser;
            firstName.value = parsedUser?.first_name || 'Student';
        }
    }

    loadStats();
    statsRefreshTimer = setInterval(loadStats, 30000);
});

onBeforeUnmount(async () => {
    if (statsRefreshTimer) {
        clearInterval(statsRefreshTimer);
    }

    if (html5QrCode && html5QrCode.isScanning) {
        await html5QrCode.stop();
    }
});
</script>

<style scoped>
.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
}

.btn-emerald:hover {
    background-color: #059669;
    color: white;
}

.text-emerald {
    color: #10b981;
}

.bg-emerald-light {
    background-color: #ecfdf5;
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

.stat-card {
    transition: transform 0.2s;
    border: none;
    box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.08) !important;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-clickable {
    cursor: pointer;
    border: none;
    background: transparent;
    display: block;
    width: 100%;
    margin: 0;
    font: inherit;
    line-height: inherit;
}

.stat-clickable::-moz-focus-inner {
    border: 0;
    padding: 0;
}

.stat-clickable:disabled {
    cursor: default;
}

/* Ensure the video inside SWAL looks good */
:deep(#reader-popup video) {
    width: 100% !important;
    height: auto !important;
    object-fit: cover !important;
}
</style>
