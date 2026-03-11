<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Students</h4>
                        <p class="text-muted small mb-0">All registered students with their latest exam record</p>
                    </div>
                    <button
                        class="btn btn-success fw-bold px-4"
                        :disabled="loading || filteredRows.length === 0"
                        @click="downloadWord"
                    >
                        <i class="bi bi-download me-2"></i>Download Word
                    </button>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input
                            v-model.trim="filters.search"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Student #, name, username, email..."
                        >
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Program</label>
                        <select v-model="filters.programName" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            <option v-for="program in programOptions" :key="program" :value="program">{{ program }}</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-semibold mb-1">Exam</label>
                        <select v-model="filters.examName" class="form-select form-select-sm">
                            <option value="">All Exams</option>
                            <option v-for="exam in examOptions" :key="exam" :value="exam">{{ exam }}</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-semibold mb-1">Sort Order</label>
                        <select v-model="filters.sortOrder" class="form-select form-select-sm">
                            <option value="asc">Ascending</option>
                            <option value="desc">Descending</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No.</th>
                                <th>Student #</th>
                                <th>Full Name</th>
                                <th>Program</th>
                                <th>Exam</th>
                                <th class="text-end">Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="7" class="text-center py-4 text-muted">Loading students...</td>
                            </tr>
                            <tr v-else-if="filteredRows.length === 0">
                                <td colspan="7" class="text-center py-4 text-muted">No students found.</td>
                            </tr>
                            <tr v-else v-for="(row, index) in filteredRows" :key="row.id">
                                <td class="ps-3">{{ index + 1 }}</td>
                                <td class="fw-semibold">{{ row.student_number || '-' }}</td>
                                <td>{{ row.full_name || '-' }}</td>
                                <td>{{ row.program_name || 'N/A' }}</td>
                                <td>{{ row.exam_name || 'N/A' }}</td>
                                <td class="text-end">{{ row.exam_total_score ?? '-' }}</td>
                                <td class="text-capitalize">{{ row.exam_status || 'not_taken' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const loading = ref(false);
const rows = ref([]);

const filters = ref({
    search: '',
    programName: '',
    examName: '',
    sortOrder: 'asc',
});

const programOptions = computed(() => {
    return [...new Set(rows.value.map((row) => row.program_name).filter(Boolean))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const examOptions = computed(() => {
    return [...new Set(rows.value.map((row) => row.exam_name).filter((name) => name && name !== 'N/A'))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const filteredRows = computed(() => {
    let result = [...rows.value];

    if (filters.value.search) {
        const q = filters.value.search.toLowerCase();
        result = result.filter((row) => {
            const text = [
                row.student_number,
                row.full_name,
                row.username,
                row.email,
                row.program_name,
                row.exam_name,
            ].map((item) => String(item || '').toLowerCase()).join(' ');

            return text.includes(q);
        });
    }

    if (filters.value.programName) {
        result = result.filter((row) => row.program_name === filters.value.programName);
    }

    if (filters.value.examName) {
        result = result.filter((row) => row.exam_name === filters.value.examName);
    }

    const factor = filters.value.sortOrder === 'asc' ? 1 : -1;
    result.sort((a, b) => String(a.full_name || '').localeCompare(String(b.full_name || '')) * factor);

    return result;
});

const loadStudents = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/students');
        rows.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        rows.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load students',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const escapeHtml = (value) => {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
};

const downloadWord = () => {
    if (loading.value || filteredRows.value.length === 0) {
        return;
    }

    const tableRows = filteredRows.value.map((row, index) => `
        <tr>
            <td>${index + 1}</td>
            <td>${escapeHtml(row.student_number || '-')}</td>
            <td>${escapeHtml(row.full_name || '-')}</td>
            <td>${escapeHtml(row.program_name || 'N/A')}</td>
            <td>${escapeHtml(row.exam_name || 'N/A')}</td>
            <td>${row.exam_total_score ?? '-'}</td>
            <td>${escapeHtml(row.exam_status || 'not_taken')}</td>
        </tr>
    `).join('');

    const html = `
        <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word">
            <head>
                <meta charset="UTF-8" />
                <title>Admin Students Report</title>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    h2 { margin: 0 0 12px; text-align: center; }
                    table { border-collapse: collapse; width: 100%; font-size: 13px; }
                    th, td { border: 1px solid #cbd5e1; padding: 7px 8px; }
                    th { background: #e2e8f0; text-align: left; }
                </style>
            </head>
            <body>
                <h2>Admin Students Report</h2>
                <table>
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Student #</th>
                            <th>Full Name</th>
                            <th>Program</th>
                            <th>Exam</th>
                            <th>Score</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>${tableRows}</tbody>
                </table>
            </body>
        </html>
    `;

    const blob = new Blob(['\ufeff', html], { type: 'application/msword' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'Admin_Students_Report.doc';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
};

onMounted(loadStudents);
</script>
