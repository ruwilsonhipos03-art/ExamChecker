<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">System Reports</h4>
                        <p class="text-muted small mb-0">View and filter comprehensive user records and statistics</p>
                    </div>
                    <div class="col-auto">
                        <button @click="exportToExcel" class="btn btn-outline-emerald fw-bold px-4 me-2">
                            <i class="bi bi-file-earmark-excel me-2"></i> EXPORT EXCEL
                        </button>
                        <button @click="printReport" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-printer me-2"></i> PRINT
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" v-model="filters.search" class="form-control border-start-0"
                                placeholder="Search name or ID..." @input="currentPage = 1">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <select v-model="filters.role" class="form-select form-select-sm" @change="currentPage = 1">
                            <option value="">All Roles</option>
                            <option value="instructor">Instructor</option>
                            <option value="student">Student</option>
                            <option value="college_dean">College Dean</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select v-model="filters.department" class="form-select form-select-sm"
                            @change="currentPage = 1">
                            <option value="">All Colleges</option>
                            <option v-for="dept in colleges" :key="dept.id" :value="dept.id">
                                {{ dept.College_Name }}
                            </option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select v-model="filters.status" class="form-select form-select-sm" @change="currentPage = 1">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <button @click="resetFilters"
                            class="btn btn-sm btn-link text-decoration-none text-muted fw-bold">
                            RESET
                        </button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">No.</th>
                                <th class="py-3 text-secondary small fw-bold">FULL NAME</th>
                                <th class="py-3 text-secondary small fw-bold">ROLE</th>
                                <th class="py-3 text-secondary small fw-bold">COLLEGE / OFFICE</th>
                                <th class="py-3 text-secondary small fw-bold">EMAIL</th>
                                <th class="pe-4 py-3 text-secondary small fw-bold text-center">STATUS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="6" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                    <div class="mt-2 text-muted small">Generating report...</div>
                                </td>
                            </tr>
                            <template v-else>
                                <tr v-for="(user, index) in paginatedData" :key="user.id">
                                    <td class="ps-4">
                                        <span class="text-dark fw-bold">{{ index + 1 }}</span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ user.full_name }}</div>
                                    </td>
                                    <td>
                                        <span class="badge rounded-pill bg-light text-dark border px-3">
                                            {{ formatRole(user.role || '') }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-dark">{{ user.College_Name || 'N/A' }}</div>
                                        <div class="small text-muted">{{ user.office_name || 'N/A' }}</div>
                                    </td>
                                    <td><span class="small">{{ user.email }}</span></td>
                                    <td class="pe-4 text-center">
                                        <span :class="user.status === 'active' ? 'text-success' : 'text-danger'">
                                            ● {{ user.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="paginatedData.length === 0">
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        No records found matching the current filters.
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card-footer bg-white border-top p-3" v-if="filteredData.length > itemsPerPage">
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        Showing {{ startIndex + 1 }} to {{ Math.min(endIndex, filteredData.length) }} of {{
                            filteredData.length }}
                        records
                    </small>
                    <nav>
                        <ul class="pagination pagination-sm mb-0">
                            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                                <button class="page-link" @click="currentPage--">Previous</button>
                            </li>
                            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                                <button class="page-link" @click="currentPage++">Next</button>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import axios from 'axios';

// State
const allUsers = ref([]); // Initialize as array to prevent .filter error
const colleges = ref([]);
const isLoading = ref(false);
const currentPage = ref(1);
const itemsPerPage = 30;

const filters = reactive({
    search: '',
    role: '',
    department: '',
    status: ''
});

// 1. Filter Logic
const filteredData = computed(() => {
    // Safety check: ensure allUsers.value is always an array
    const data = Array.isArray(allUsers.value) ? allUsers.value : [];

    return data.filter(user => {
        const fullName = user.full_name ? user.full_name.toLowerCase() : '';
        const idNum = user.id_number ? String(user.id_number) : '';

        const matchesSearch = fullName.includes(filters.search.toLowerCase()) || idNum.includes(filters.search);
        const matchesRole = !filters.role || user.role === filters.role;
        const matchesDept = !filters.department || user.college_id === filters.department;
        const matchesStatus = !filters.status || user.status === filters.status;

        return matchesSearch && matchesRole && matchesDept && matchesStatus;
    });
});

// 2. Pagination Logic
const totalPages = computed(() => Math.ceil(filteredData.value.length / itemsPerPage));
const startIndex = computed(() => (currentPage.value - 1) * itemsPerPage);
const endIndex = computed(() => startIndex.value + itemsPerPage);

const paginatedData = computed(() => {
    return filteredData.value.slice(startIndex.value, endIndex.value);
});

onMounted(() => {
    fetchReportData();
    fetchOptions();
});

const fetchReportData = async () => {
    isLoading.value = true;
    try {
        const res = await axios.get('/api/admin/reports/all-users');
        // Standardize the response structure
        const responseData = res.data.data || res.data;
        allUsers.value = Array.isArray(responseData) ? responseData : [];
    } catch (err) {
        console.error("Report fetch error:", err);
        allUsers.value = [];
    } finally {
        isLoading.value = false;
    }
};

const fetchOptions = async () => {
    try {
        const res = await axios.get('/api/admin/colleges');
        colleges.value = res.data.data || res.data;
    } catch (e) { console.error(e); }
};

const resetFilters = () => {
    filters.search = '';
    filters.role = '';
    filters.department = '';
    filters.status = '';
    currentPage.value = 1;
};

const formatRole = (role) => {
    if (!role) return 'N/A';
    return role.replace('_', ' ').toUpperCase();
};

const exportToExcel = () => {
    alert("Exporting " + filteredData.value.length + " records to Excel...");
};

const printReport = () => {
    window.print();
};
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

.btn-outline-emerald {
    color: #10b981;
    border: 1px solid #10b981;
}

.btn-outline-emerald:hover {
    background-color: #ecfdf5;
    color: #059669;
}

.page-link {
    color: #10b981;
    border-color: #dee2e6;
}

.page-link:hover {
    background-color: #ecfdf5;
    color: #059669;
}

.page-item.disabled .page-link {
    color: #6c757d;
}

@media print {

    .btn,
    .input-group,
    .form-select,
    .pagination,
    .card-footer,
    .text-muted.small {
        display: none !important;
    }

    .card {
        box-shadow: none !important;
        border: none !important;
    }
}
</style>
