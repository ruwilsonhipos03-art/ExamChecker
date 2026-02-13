<template>
    <div class="page-container">
        <h3 class="fw-bold mb-4">Entrance Examiner Reports</h3>

        <div class="card border-0 shadow-sm p-4 rounded-4 mb-3 no-print">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Filter by Exam Title</label>
                    <select v-model="filters.examTitle" class="form-select">
                        <option value="">All Exams</option>
                        <option v-for="title in examTitles" :key="title" :value="title">
                            {{ title }}
                        </option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-semibold">Search Student Name</label>
                    <input v-model.trim="filters.name" type="text" class="form-control"
                        placeholder="Lastname, Firstname..." />
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Sort By</label>
                    <select v-model="filters.sortBy" class="form-select">
                        <option value="student_full_name">Name</option>
                        <option value="Exam_Title">Exam Title</option>
                        <option value="math">Math</option>
                        <option value="english">English</option>
                        <option value="science">Science</option>
                        <option value="social_science">Social Science</option>
                        <option value="total">Total</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-semibold">Order</label>
                    <select v-model="filters.sortOrder" class="form-select">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <button class="btn btn-success w-100" :disabled="loading || filteredRows.length === 0"
                        @click="printActiveTable">
                        <i class="bi bi-printer me-2"></i>Print to PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
            <div class="print-meta print-only">
                <div class="fw-bold fs-5">Entrance Examiner Reports</div>
                <div class="mt-2">
                    <span><strong>Exam Filter:</strong> {{ filters.examTitle || 'All' }}</span> |
                    <span><strong>Sorted by:</strong> {{ readableSortBy }} ({{ filters.sortOrder.toUpperCase()
                        }})</span>
                </div>
                <div><strong>Generated on:</strong> {{ generatedAt }}</div>
                <hr />
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 72px;">No.</th>
                            <th>Student Fullname</th>
                            <th>Exam Title</th>
                            <th class="text-end">Math</th>
                            <th class="text-end">English</th>
                            <th class="text-end">Science</th>
                            <th class="text-end">Social Science</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="loading">
                            <td colspan="8" class="text-center py-5">
                                <div class="spinner-border spinner-border-sm text-primary me-2"></div>
                                Loading reports...
                            </td>
                        </tr>
                        <tr v-else-if="filteredRows.length === 0">
                            <td colspan="8" class="text-center py-4 text-muted">No records found.</td>
                        </tr>
                        <tr v-else v-for="(row, index) in filteredRows" :key="row.id || index">
                            <td>{{ index + 1 }}</td>
                            <td class="fw-semibold">{{ row.student_full_name || 'N/A' }}</td>
                            <td>{{ row.Exam_Title }}</td>
                            <td class="text-end">{{ row.math ?? 0 }}</td>
                            <td class="text-end">{{ row.english ?? 0 }}</td>
                            <td class="text-end">{{ row.science ?? 0 }}</td>
                            <td class="text-end">{{ row.social_science ?? 0 }}</td>
                            <td class="text-end fw-bold">{{ row.total ?? 0 }}</td>
                        </tr>
                    </tbody>
                </table>
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
    examTitle: '',
    name: '',
    sortBy: 'student_full_name',
    sortOrder: 'asc',
});

// Extract unique Exam Titles for the dropdown
const examTitles = computed(() => {
    if (!rows.value.length) return [];
    const titles = rows.value.map((row) => row.Exam_Title).filter(Boolean);
    return [...new Set(titles)].sort((a, b) => a.localeCompare(b));
});

const filteredRows = computed(() => {
    let result = [...rows.value];

    // Filter by Exam Title
    if (filters.value.examTitle) {
        result = result.filter((row) => row.Exam_Title === filters.value.examTitle);
    }

    // Filter by Student Name
    if (filters.value.name) {
        const search = filters.value.name.toLowerCase();
        result = result.filter((row) =>
            row.student_full_name?.toLowerCase().includes(search)
        );
    }

    // Sorting Logic
    const key = filters.value.sortBy;
    const factor = filters.value.sortOrder === 'asc' ? 1 : -1;

    result.sort((a, b) => {
        const first = a[key] ?? '';
        const second = b[key] ?? '';

        if (typeof first === 'number' && typeof second === 'number') {
            return (first - second) * factor;
        }

        return String(first).localeCompare(String(second)) * factor;
    });

    return result;
});

const readableSortBy = computed(() => {
    const labels = {
        student_full_name: 'Name',
        Exam_Title: 'Exam Title',
        math: 'Math',
        english: 'English',
        science: 'Science',
        social_science: 'Social Science',
        total: 'Total',
    };
    return labels[filters.value.sortBy] || 'Name';
});

const generatedAt = computed(() => new Date().toLocaleString());

const printActiveTable = () => {
    window.print();
};

const loadReports = async () => {
    loading.value = true;
    try {
        // Adjust the URL if needed based on your API environment
        const { data } = await axios.get('/api/entrance/reports/examinee-results');

        // Based on your JSON, data is the array, or inside data.data
        rows.value = Array.isArray(data) ? data : (data?.data || []);
    } catch (error) {
        console.error("Error loading reports:", error);
        rows.value = [];
    } finally {
        loading.value = false;
    }
};

onMounted(loadReports);
</script>

<style scoped>
.print-only {
    display: none;
}

@media print {
    .no-print {
        display: none !important;
    }

    .print-only {
        display: block !important;
    }

    .page-container {
        padding: 0 !important;
        margin: 0 !important;
    }

    .card {
        border: none !important;
    }

    .table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 11px;
        /* Smaller font for PDF fit */
    }

    .table th {
        background-color: #f8f9fa !important;
        -webkit-print-color-adjust: exact;
    }

    .table td,
    .table th {
        border: 1px solid #dee2e6 !important;
        padding: 4px 8px !important;
    }
}
</style>
