<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4 bg-indigo-700 text-white">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1">Department Head Workspace</h4>
                        <p class="text-indigo-100 small mb-0">Manage your personal examination standards and sets</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-light fw-bold px-4 shadow-sm text-indigo-700">
                            <i class="bi bi-shield-plus me-2"></i> CREATE NEW SET
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="px-4 py-3 border-bottom bg-light">
                    <div class="input-group input-group-sm w-25">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" v-model="searchQuery" class="form-control border-start-0 ps-0"
                            placeholder="Search my sets...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">REF. NO</th>
                                <th class="py-3 text-secondary small fw-bold">EXAM TITLE</th>
                                <th class="py-3 text-secondary small fw-bold">TYPE</th>
                                <th class="py-3 text-secondary small fw-bold">OWNER</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="5" class="text-center py-5">
                                    <div class="spinner-border text-indigo" role="status"></div>
                                </td>
                            </tr>
                            <template v-else>
                                <tr v-for="(exam, index) in filteredExams" :key="exam.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="dept-icon me-3">
                                                <i class="bi bi-file-earmark-check-fill text-indigo"></i>
                                            </div>
                                            <span class="fw-bold text-dark">{{ exam.Exam_Title }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-indigo-soft text-indigo border border-indigo-200 px-3">
                                            {{ exam.Exam_Type }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <i class="bi bi-person-circle me-1"></i>
                                        {{ exam.creator?.name || 'You' }}
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(exam)" class="btn btn-icon btn-outline-indigo me-2">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteExam(exam.id)" class="btn btn-icon btn-outline-danger"
                                            :disabled="deletingId === exam.id">
                                            <span v-if="deletingId === exam.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredExams.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted">No exams recorded under your
                                        account.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="examModal" tabindex="-1" ref="modalRef">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header bg-indigo-700 text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Update Exam Set' : 'Configure New Exam' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveExam">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary text-uppercase">Title of
                                    Examination</label>
                                <input v-model="form.Exam_Title" type="text" class="form-control border-2" required>
                            </div>
                            <div class="mb-3">
                                <label
                                    class="form-label small fw-bold text-secondary text-uppercase">Classification</label>
                                <select v-model="form.Exam_Type" class="form-select border-2" required>
                                    <option value="Entrance">Entrance</option>
                                    <option value="Departmental">Departmental</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">CLOSE</button>
                            <button type="submit" class="btn btn-indigo-700 px-4 fw-bold text-white"
                                :disabled="isSaving">
                                {{ isSaving ? 'SAVING...' : 'COMMIT CHANGES' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, reactive, computed } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';

// State
const exams = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);

const form = reactive({ Exam_Title: '', Exam_Type: 'Entrance' });

// Methods
const filteredExams = computed(() => {
    return (exams.value || []).filter(e =>
        e.Exam_Title.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

const fetchExams = async () => {
    isLoading.value = true;
    try {
        // Calls the shared unified endpoint
        const res = await axios.get('/api/exams');
        exams.value = res.data;
    } catch (e) {
        console.error("Fetch failed", e);
    } finally {
        isLoading.value = false;
    }
};

const openModal = (exam = null) => {
    editMode.value = !!exam;
    currentId.value = exam?.id || null;
    form.Exam_Title = exam?.Exam_Title || '';
    form.Exam_Type = exam?.Exam_Type || 'Entrance';
    modalInstance.show();
};

const saveExam = async () => {
    isSaving.value = true;
    const url = editMode.value ? `/api/exams/${currentId.value}` : '/api/exams';
    const method = editMode.value ? 'put' : 'post';

    try {
        await axios[method](url, form);
        modalInstance.hide();
        await fetchExams();
        window.Toast.fire({ icon: 'success', title: 'Department records updated!' });
    } catch (e) {
        window.Swal.fire({ icon: 'error', title: 'Process Error', text: 'Check your inputs.' });
    } finally {
        isSaving.value = false;
    }
};

const deleteExam = async (id) => {
    const result = await window.Swal.fire({
        title: 'Confirm Deletion?',
        text: 'This exam set will be permanently removed from your records.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#4f46e5'
    });

    if (result.isConfirmed) {
        deletingId.value = id;
        try {
            await axios.delete(`/api/exams/${id}`);
            await fetchExams();
        } finally {
            deletingId.value = null;
        }
    }
};

onMounted(() => {
    fetchExams();
    modalInstance = new Modal(modalRef.value);
});
</script>

<style scoped>
/* Indigo Theme Palette */
.bg-indigo-700 {
    background-color: #4338ca;
}

.text-indigo-700 {
    color: #4338ca;
}

.text-indigo-100 {
    color: #e0e7ff;
}

.text-indigo {
    color: #4f46e5;
}

.btn-indigo-700 {
    background-color: #4338ca;
    border: none;
}

.btn-indigo-700:hover {
    background-color: #3730a3;
}

.bg-indigo-soft {
    background-color: #eef2ff;
}

.border-indigo-200 {
    border-color: #c7d2fe !important;
}

.btn-outline-indigo {
    color: #4f46e5;
    border-color: #4f46e5;
}

.btn-outline-indigo:hover {
    background-color: #4f46e5;
    color: white;
}

.dept-icon {
    width: 40px;
    height: 40px;
    background-color: #eef2ff;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

.btn-icon {
    width: 32px;
    height: 32px;
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
}
</style>
