<template>
    <div class="container-fluid py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <div class="row align-items-center">
                    <div class="col">
                        <h4 class="fw-bold mb-1 text-dark">Examiner Workspace</h4>
                        <p class="text-muted small mb-0">Create and manage term examination sets</p>
                    </div>
                    <div class="col-auto">
                        <button @click="openModal()" class="btn btn-emerald fw-bold px-4 shadow-sm">
                            <i class="bi bi-plus-lg me-2"></i> NEW EXAM SET
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="px-4 py-3 border-bottom bg-light">
                    <div class="input-group input-group-sm w-50 w-md-25">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="text" v-model="searchQuery" class="form-control border-start-0 ps-0"
                            placeholder="Search by title or type...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4 py-3 text-secondary small fw-bold">NO.</th>
                                <th class="py-3 text-secondary small fw-bold">EXAM TITLE</th>
                                <th class="py-3 text-secondary small fw-bold">CATEGORY</th>
                                <th class="py-3 text-secondary small fw-bold">EXAMINER</th>
                                <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="isLoading">
                                <td colspan="6" class="text-center py-5">
                                    <div class="spinner-border text-emerald" role="status"></div>
                                </td>
                            </tr>
                            <template v-else>
                                <tr v-for="(exam, index) in filteredExams" :key="exam.id">
                                    <td class="ps-4 text-muted">#{{ index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="exam-icon me-3">
                                                <i class="bi bi-journal-text text-emerald"></i>
                                            </div>
                                            <span class="fw-semibold text-dark">{{ exam.Exam_Title }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <span
                                            class="badge rounded-pill bg-light text-emerald border border-emerald px-3">
                                            {{ exam.Exam_Type }}
                                        </span>
                                    </td>
                                    <td class="text-muted small">
                                        <template v-if="exam.creator">
                                            {{ exam.creator.name || `${exam.creator.first_name || ''}
                                            ${exam.creator.last_name || ''}` }}
                                        </template>
                                        <template v-else>N/A</template>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button @click="openModal(exam)" class="btn btn-icon btn-light-success me-2"
                                            title="Edit">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <button @click="deleteExam(exam.id)" class="btn btn-icon btn-light-danger"
                                            :disabled="deletingId === exam.id">
                                            <span v-if="deletingId === exam.id"
                                                class="spinner-border spinner-border-sm"></span>
                                            <i v-else class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="filteredExams.length === 0">
                                    <td colspan="5" class="text-center py-5 text-muted">No exams recorded yet.</td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="modal fade" id="examModal" tabindex="-1" ref="modalRef">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header bg-emerald text-white border-0">
                        <h5 class="modal-title fw-bold">{{ editMode ? 'Modify Exam Set' : 'Create New Exam Set' }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form @submit.prevent="saveExam">
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-secondary">EXAM TITLE</label>
                                <input v-model="form.Exam_Title" type="text" class="form-control border-2" required>
                            </div>

                        </div>
                        <div class="modal-footer border-0 p-4 pt-0">
                            <button type="button" class="btn btn-light fw-bold" data-bs-dismiss="modal">CANCEL</button>
                            <button type="submit" class="btn btn-emerald px-4 fw-bold" :disabled="isSaving">
                                {{ isSaving ? 'SAVING...' : 'SAVE EXAM' }}
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

const exams = ref([]);
const searchQuery = ref('');
const editMode = ref(false);
const currentId = ref(null);
const modalRef = ref(null);
let modalInstance = null;

const isLoading = ref(false);
const isSaving = ref(false);
const deletingId = ref(null);
const EXAM_TYPE_ALIASES = ['term', 'term exam', 'departmental', 'normal', 'normal exam'];

const form = reactive({ Exam_Title: '', Exam_Type: 'Term' });

const resetForm = () => {
    form.Exam_Title = '';
    form.Exam_Type = 'Term';
};

const filteredExams = computed(() => {
    const data = Array.isArray(exams.value) ? exams.value : [];
    const allowed = data.filter((exam) =>
        EXAM_TYPE_ALIASES.includes(String(exam?.Exam_Type || '').trim().toLowerCase())
    );
    return allowed.filter(e => {
        const title = (e.Exam_Title || '').toLowerCase();
        const type = (e.Exam_Type || '').toLowerCase();
        return title.includes(searchQuery.value.toLowerCase()) || type.includes(searchQuery.value.toLowerCase());
    });
});

const fetchExams = async () => {
    isLoading.value = true;
    try {
        const examsRes = await axios.get('/api/exams');
        exams.value = Array.isArray(examsRes.data) ? examsRes.data : [];
    } catch (e) {
        exams.value = [];
    } finally {
        isLoading.value = false;
    }
};

const openModal = (exam = null) => {
    resetForm();
    editMode.value = !!exam;
    currentId.value = exam?.id || null;
    form.Exam_Title = exam?.Exam_Title || '';
    form.Exam_Type = exam?.Exam_Type || 'Term';
    modalInstance.show();
};

const saveExam = async () => {
    isSaving.value = true;
    try {
        const payload = {
            Exam_Title: form.Exam_Title,
            Exam_Type: form.Exam_Type,
        };
        if (editMode.value) {
            await axios.put(`/api/exams/${currentId.value}`, payload);
        } else {
            await axios.post('/api/exams', payload);
        }
        modalInstance.hide();
        await fetchExams();
        window.Toast?.fire({ icon: 'success', title: 'Data synced!' });
    } catch (e) {
        window.Swal?.fire({ icon: 'error', title: 'Error', text: 'Failed to save.' });
    } finally {
        isSaving.value = false;
    }
};

const deleteExam = async (id) => {
    const result = await window.Swal.fire({
        title: 'Delete this set?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444'
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
.btn-emerald {
    background-color: #10b981;
    color: white;
}

.btn-emerald:hover {
    background-color: #059669;
}

.text-emerald {
    color: #10b981;
}

.bg-emerald {
    background-color: #10b981;
}

.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 8px;
    border: none;
}

.btn-light-success {
    color: #10b981;
    background-color: #ecfdf5;
}

.btn-light-danger {
    color: #ef4444;
    background-color: #fef2f2;
}

.exam-icon {
    width: 40px;
    height: 40px;
    background-color: #f0fdf4;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
}
</style>
