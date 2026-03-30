<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">Users</h4>
                        <p class="text-muted small mb-0">All people who use the system, including staff, students, and applicants.</p>
                    </div>
                    <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                        <i class="bi bi-person-plus me-2"></i>Add Employee
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3" v-for="item in summaryCards" :key="item.label">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <div class="small text-uppercase text-muted fw-semibold mb-2">{{ item.label }}</div>
                        <div class="h3 fw-bold mb-0 text-dark">{{ item.value }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body bg-light border-bottom p-3">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Search</label>
                        <input
                            v-model.trim="filters.search"
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="Name, username, ID no., role, program..."
                        >
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Category</label>
                        <select v-model="filters.category" class="form-select form-select-sm">
                            <option v-for="option in categoryOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Program</label>
                        <select v-model="filters.programName" class="form-select form-select-sm">
                            <option value="">All Programs</option>
                            <option v-for="program in programOptions" :key="program" :value="program">{{ program }}</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">College / Office</label>
                        <select v-model="filters.orgUnit" class="form-select form-select-sm">
                            <option value="">All Units</option>
                            <option v-for="unit in orgUnitOptions" :key="unit" :value="unit">{{ unit }}</option>
                        </select>
                    </div>

                    <div class="col-lg-2 col-md-6">
                        <label class="form-label small fw-semibold mb-1">Sort</label>
                        <select v-model="filters.sortBy" class="form-select form-select-sm">
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                            <option value="newest">Newest First</option>
                            <option value="oldest">Oldest First</option>
                        </select>
                    </div>

                    <div class="col-lg-1 col-md-6">
                        <button class="btn btn-sm btn-outline-secondary w-100" @click="resetFilters">Reset</button>
                    </div>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">No.</th>
                                <th>User</th>
                                <th>Category</th>
                                <th>Roles</th>
                                <th>ID No.</th>
                                <th>Program / Unit</th>
                                <th>Status</th>
                                <th class="text-end pe-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="loading">
                                <td colspan="8" class="text-center py-5 text-muted">Loading users...</td>
                            </tr>
                            <tr v-else-if="filteredRows.length === 0">
                                <td colspan="8" class="text-center py-5 text-muted">No users found for the selected filters.</td>
                            </tr>
                            <tr v-else v-for="(row, index) in filteredRows" :key="row.id">
                                <td class="ps-3">{{ index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ row.full_name || '-' }}</div>
                                    <div class="small text-muted">{{ row.username || '-' }}</div>
                                    <div class="small text-muted" v-if="row.email">{{ row.email }}</div>
                                </td>
                                <td>
                                    <span :class="['badge rounded-pill px-3 py-2', categoryBadgeClass(row)]">
                                        {{ categoryLabel(row) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span
                                            v-for="role in row.roles"
                                            :key="`${row.id}-${role}`"
                                            class="badge rounded-pill bg-light text-dark border px-2 py-1"
                                        >
                                            {{ prettyRole(role) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="small text-dark">
                                    {{ displayIdNumber(row) }}
                                </td>
                                <td>
                                    <div class="small fw-semibold text-dark">{{ row.program_name || 'N/A' }}</div>
                                    <div class="small text-muted">{{ row.college_name || 'N/A' }}</div>
                                    <div class="small text-muted">{{ row.office_name || 'N/A' }}</div>
                                </td>
                                <td>
                                    <span v-if="studentStatus(row)" :class="['badge border', studentStatus(row).className]">
                                        {{ studentStatus(row).label }}
                                    </span>
                                    <span v-else class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                        Active User
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <template v-if="canManageEmployee(row)">
                                        <button @click="openModal(row)" class="btn btn-icon btn-light-success me-2" title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button
                                            @click="deleteEmployee(row)"
                                            class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === row.employee_id"
                                            title="Delete"
                                        >
                                            <span v-if="deletingId === row.employee_id" class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </template>
                                    <span v-else class="text-muted small">View only</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="employeeModal" tabindex="-1" aria-hidden="true" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Edit Employee' : 'New Employee Account' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveEmployee">
                        <div class="modal-body p-4">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-uppercase label-required">First Name</label>
                                    <input v-model="form.first_name" type="text" class="form-control" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-uppercase">M.I.</label>
                                    <input v-model="form.middle_initial" type="text" class="form-control" maxlength="2">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold text-uppercase label-required">Last Name</label>
                                    <input v-model="form.last_name" type="text" class="form-control" required>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-uppercase">Extension Name</label>
                                    <input v-model="form.extension_name" type="text" class="form-control" maxlength="10">
                                </div>

                                <div class="col-md-12 py-2">
                                    <label class="form-label small fw-bold text-uppercase label-required">System Role Assignment</label>
                                    <div class="p-3 border rounded bg-white shadow-sm">
                                        <div class="d-flex flex-wrap align-items-center gap-4">
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input custom-check"
                                                    type="radio"
                                                    value="college_dean"
                                                    id="roleDept"
                                                    v-model="roleSelectionType"
                                                    @change="handleRoleTypeChange"
                                                >
                                                <label class="form-check-label fw-bold text-dark" for="roleDept">College Dean</label>
                                            </div>
                                            <div class="vr mx-1 d-none d-md-block" style="height: 20px;"></div>
                                            <div class="form-check">
                                                <input
                                                    class="form-check-input custom-check"
                                                    type="radio"
                                                    value="staff"
                                                    id="roleStaff"
                                                    v-model="roleSelectionType"
                                                    @change="handleRoleTypeChange"
                                                >
                                                <label class="form-check-label fw-bold text-dark" for="roleStaff">Academic / Staff</label>
                                            </div>
                                            <div class="d-flex gap-3 ms-3 ms-md-0" v-if="roleSelectionType === 'staff'">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="instructor" id="chkInst" v-model="form.roles">
                                                    <label class="form-check-label small" for="chkInst">Instructor</label>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" value="entrance_examiner" id="chkExam" v-model="form.roles">
                                                    <label class="form-check-label small" for="chkExam">Entrance Examiner</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div v-if="form.roles.length === 0" class="text-danger small mt-1">Please select at least one role.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">College</label>
                                    <select v-model="form.college_id" class="form-select">
                                        <option value="">None / Unassigned</option>
                                        <option v-for="dept in colleges" :key="dept.id" :value="dept.id">{{ dept.College_Name }}</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase">Office</label>
                                    <select v-model="form.office_id" class="form-select">
                                        <option value="">None / Unassigned</option>
                                        <option v-for="off in offices" :key="off.id" :value="off.id">{{ off.Office_Name }}</option>
                                    </select>
                                </div>

                                <div class="col-md-12" v-if="editMode">
                                    <label class="form-label small fw-bold text-uppercase">Employee Number</label>
                                    <input v-model="form.employee_number" type="text" class="form-control" readonly disabled>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-uppercase label-required">Username</label>
                                    <input v-model="form.username" type="text" class="form-control" required>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small fw-bold text-uppercase" :class="{ 'label-required': !editMode }">Password</label>
                                    <input v-model="form.password" type="password" class="form-control" :required="!editMode">
                                </div>

                                <div class="col-md-6">
                                    <label
                                        class="form-label small fw-bold text-uppercase"
                                        :class="{ 'label-required': !editMode || form.password.length > 0 }"
                                    >
                                        Confirm Password
                                    </label>
                                    <input
                                        v-model="form.password_confirmation"
                                        type="password"
                                        class="form-control"
                                        :class="{ 'is-invalid': showPassError }"
                                        @focus="isConfirmFocused = true"
                                    >
                                    <div class="invalid-feedback">Passwords do not match.</div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal" :disabled="isSaving">Cancel</button>
                            <button
                                type="submit"
                                class="btn btn-emerald px-4 fw-bold"
                                :disabled="isSaving || form.roles.length === 0 || form.password !== form.password_confirmation"
                            >
                                <span v-if="isSaving" class="spinner-border spinner-border-sm me-2"></span>
                                {{ isSaving ? 'Saving...' : 'Save Data' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import axios from 'axios';
import { computed, onMounted, reactive, ref } from 'vue';

const loading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);
const users = ref([]);
const colleges = ref([]);
const offices = ref([]);
const editMode = ref(false);
const currentEmployeeId = ref(null);
const modalRef = ref(null);
const isConfirmFocused = ref(false);
const roleSelectionType = ref('staff');
let modalInstance = null;

const filters = reactive({
    search: '',
    category: 'users',
    programName: '',
    orgUnit: '',
    sortBy: 'name_asc',
});

const form = reactive({
    first_name: '',
    middle_initial: '',
    last_name: '',
    extension_name: '',
    employee_number: '',
    college_id: '',
    office_id: '',
    username: '',
    password: '',
    password_confirmation: '',
    roles: [],
});

const passwordsDoNotMatch = computed(() => form.password !== form.password_confirmation);
const showPassError = computed(() => isConfirmFocused.value && passwordsDoNotMatch.value && form.password_confirmation.length > 0);

const summaryCards = computed(() => ([
    { label: 'All Users', value: users.value.length.toLocaleString() },
    { label: 'Staff', value: users.value.filter((row) => row.user_kind === 'employee').length.toLocaleString() },
    { label: 'Students', value: users.value.filter((row) => row.roles.includes('student')).length.toLocaleString() },
    { label: 'Applicants', value: users.value.filter((row) => row.is_applicant).length.toLocaleString() },
]));

const categoryOptions = computed(() => ([
    { value: 'users', label: 'All Users' },
    { value: 'staff', label: 'Staff' },
    { value: 'students', label: 'Students' },
    { value: 'applicants', label: 'Applicants' },
    { value: 'instructors', label: 'Instructors' },
    { value: 'college_deans', label: 'College Deans' },
    { value: 'entrance_examiners', label: 'Entrance Examiners' },
    { value: 'admins', label: 'Admins' },
]));

const programOptions = computed(() => {
    return [...new Set(users.value.map((row) => row.program_name).filter((item) => item && item !== 'N/A'))]
        .sort((a, b) => String(a).localeCompare(String(b)));
});

const orgUnitOptions = computed(() => {
    return [...new Set(
        users.value
            .flatMap((row) => [row.college_name, row.office_name])
            .filter((item) => item && item !== 'N/A')
    )].sort((a, b) => String(a).localeCompare(String(b)));
});

const filteredRows = computed(() => {
    let result = [...users.value];

    if (filters.search) {
        const query = filters.search.toLowerCase();
        result = result.filter((row) => {
            const text = [
                row.full_name,
                row.username,
                row.email,
                row.student_number,
                row.employee_number,
                row.program_name,
                row.college_name,
                row.office_name,
                row.role,
            ].map((item) => String(item || '').toLowerCase()).join(' ');

            return text.includes(query);
        });
    }

    if (filters.category) {
        result = result.filter((row) => matchesCategory(row, filters.category));
    }

    if (filters.programName) {
        result = result.filter((row) => row.program_name === filters.programName);
    }

    if (filters.orgUnit) {
        result = result.filter((row) => row.college_name === filters.orgUnit || row.office_name === filters.orgUnit);
    }

    result.sort((a, b) => {
        if (filters.sortBy === 'name_desc') {
            return String(b.full_name || '').localeCompare(String(a.full_name || ''));
        }

        if (filters.sortBy === 'newest') {
            return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        }

        if (filters.sortBy === 'oldest') {
            return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        }

        return String(a.full_name || '').localeCompare(String(b.full_name || ''));
    });

    return result;
});

const matchesCategory = (row, category) => {
    if (category === 'users') return true;
    if (category === 'applicants') return row.is_applicant;
    if (category === 'students') return row.roles.includes('student');
    if (category === 'instructors') return row.roles.includes('instructor');
    if (category === 'college_deans') return row.roles.includes('college_dean');
    if (category === 'entrance_examiners') return row.roles.includes('entrance_examiner');
    if (category === 'admins') return row.roles.includes('admin');
    if (category === 'staff') return row.user_kind === 'employee';
    return true;
};

const prettyRole = (role) => String(role || '').replaceAll('_', ' ').replace(/\b\w/g, (char) => char.toUpperCase());

const categoryLabel = (row) => {
    if (row.is_applicant) return 'Applicant';
    if (row.roles.includes('admin')) return 'Admin';
    if (row.user_kind === 'employee') return 'Staff';
    if (row.roles.includes('student')) return 'Student';
    return 'User';
};

const categoryBadgeClass = (row) => {
    if (row.is_applicant) return 'bg-warning-subtle text-warning border border-warning-subtle';
    if (row.roles.includes('admin')) return 'bg-danger-subtle text-danger border border-danger-subtle';
    if (row.user_kind === 'employee') return 'bg-info-subtle text-info border border-info-subtle';
    if (row.roles.includes('student')) return 'bg-success-subtle text-success border border-success-subtle';
    return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
};

const studentStatus = (row) => {
    if (!row?.roles?.includes('student')) {
        return null;
    }

    if (!row.has_verified_email) {
        return {
            label: 'Email Not Verified',
            className: 'bg-danger-subtle text-danger border-danger-subtle',
        };
    }

    if (!row.has_entrance_exam_taken) {
        return {
            label: 'Entrance Exam Not Taken',
            className: 'bg-warning-subtle text-warning border-warning-subtle',
        };
    }

    if (!row.has_screening_attempt) {
        return {
            label: 'No Screening Attempt Yet',
            className: 'bg-warning-subtle text-warning border-warning-subtle',
        };
    }

    if (!row.has_screening_pass) {
        return {
            label: 'Screening Not Passed',
            className: 'bg-warning-subtle text-warning border-warning-subtle',
        };
    }

    return {
        label: 'Screening Passed',
        className: 'bg-success-subtle text-success border-success-subtle',
    };
};

const displayIdNumber = (row) => row.employee_number || row.student_number || `USER-${row.id}`;

const canManageEmployee = (row) => Boolean(row.employee_id);

const resetForm = () => {
    Object.assign(form, {
        first_name: '',
        middle_initial: '',
        last_name: '',
        extension_name: '',
        employee_number: '',
        college_id: '',
        office_id: '',
        username: '',
        password: '',
        password_confirmation: '',
        roles: [],
    });
};

const handleRoleTypeChange = () => {
    form.roles = roleSelectionType.value === 'college_dean' ? ['college_dean'] : [];
};

const resetFilters = () => {
    Object.assign(filters, {
        search: '',
        category: 'users',
        programName: '',
        orgUnit: '',
        sortBy: 'name_asc',
    });
};

const loadUsers = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get('/api/admin/users');
        users.value = Array.isArray(data?.data) ? data.data : [];
    } catch (error) {
        users.value = [];
        window.Swal?.fire({
            icon: 'error',
            title: 'Failed to load users',
            text: error?.response?.data?.message || 'Please refresh and try again.',
        });
    } finally {
        loading.value = false;
    }
};

const fetchOptions = async () => {
    try {
        const [deptRes, offRes] = await Promise.all([
            axios.get('/api/admin/colleges'),
            axios.get('/api/admin/offices'),
        ]);
        colleges.value = deptRes.data.data || deptRes.data || [];
        offices.value = offRes.data.data || offRes.data || [];
    } catch (error) {
        colleges.value = [];
        offices.value = [];
    }
};

const openModal = (row = null) => {
    editMode.value = Boolean(row?.employee_id);
    currentEmployeeId.value = row?.employee_id || null;
    isConfirmFocused.value = false;

    if (row?.employee_id) {
        roleSelectionType.value = row.roles.includes('college_dean') ? 'college_dean' : 'staff';
        Object.assign(form, {
            first_name: row.first_name || '',
            middle_initial: row.middle_initial || '',
            last_name: row.last_name || '',
            extension_name: row.extension_name || '',
            employee_number: row.employee_number || '',
            college_id: row.college_id || '',
            office_id: row.office_id || '',
            username: row.username || '',
            password: '',
            password_confirmation: '',
            roles: [...row.roles].filter((role) => ['college_dean', 'instructor', 'entrance_examiner'].includes(role)),
        });
    } else {
        resetForm();
        roleSelectionType.value = 'staff';
    }

    modalInstance?.show();
};

const saveEmployee = async () => {
    isSaving.value = true;
    try {
        const method = editMode.value ? 'put' : 'post';
        const url = editMode.value ? `/api/admin/employees/${currentEmployeeId.value}` : '/api/admin/employees';

        await axios[method](url, {
            first_name: form.first_name,
            middle_initial: form.middle_initial,
            last_name: form.last_name,
            extension_name: form.extension_name,
            college_id: form.college_id || null,
            office_id: form.office_id || null,
            username: form.username,
            password: form.password,
            password_confirmation: form.password_confirmation,
            roles: form.roles,
        });

        modalInstance?.hide();
        await loadUsers();
        window.Toast?.fire({ icon: 'success', title: 'Action successful' });
    } catch (error) {
        window.Swal?.fire({
            icon: 'error',
            title: 'Action failed',
            text: error?.response?.data?.message || 'Check your inputs.',
        });
    } finally {
        isSaving.value = false;
    }
};

const deleteEmployee = async (row) => {
    if (!row?.employee_id) return;

    const result = await window.Swal.fire({
        title: 'Are you sure?',
        text: `Deleting ${row.full_name} will remove system access.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete',
    });

    if (!result.isConfirmed) return;

    deletingId.value = row.employee_id;
    try {
        await axios.delete(`/api/admin/employees/${row.employee_id}`);
        await loadUsers();
        window.Toast?.fire({ icon: 'success', title: 'Deleted' });
    } catch (error) {
        window.Swal?.fire('Error', 'Delete failed.', 'error');
    } finally {
        deletingId.value = null;
    }
};

onMounted(async () => {
    modalInstance = modalRef.value ? new Modal(modalRef.value) : null;
    await Promise.all([loadUsers(), fetchOptions()]);
});
</script>

<style scoped>
.label-required::after {
    content: " *";
    color: #ef4444;
}

.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
}

.btn-emerald:hover {
    background-color: #059669;
    color: white;
}

.btn-icon {
    width: 32px;
    height: 32px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: none;
    transition: 0.2s;
}

.btn-light-success {
    color: #10b981;
    background-color: #ecfdf5;
}

.btn-light-danger {
    color: #ef4444;
    background-color: #fef2f2;
}

.bg-emerald {
    background-color: #10b981;
}

.custom-check {
    transform: scale(1.1);
}
</style>
