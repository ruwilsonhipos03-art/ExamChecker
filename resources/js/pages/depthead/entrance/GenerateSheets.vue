<template>
    <div class="container-fluid py-2 py-md-4">
        <div class="card shadow-sm border-0 mb-4 bg-white">
            <div class="card-body p-3 p-md-4">
                <div class="row align-items-center g-3">
                    <div class="col-12 col-md">
                        <h4 class="fw-bold mb-1 text-dark">Generate Answer Sheets</h4>
                        <p class="text-muted small mb-0 d-none d-sm-block">Create and print OMR bubble sheets</p>
                    </div>
                    <div class="col-12 col-md-auto">
                        <button @click="openModal" class="btn btn-emerald fw-bold w-100 px-4">
                            <i class="bi bi-printer me-2"></i> GENERATE / PRINT
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 overflow-hidden">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="bg-light">
                        <tr>

                            <th class="py-3 text-secondary small fw-bold">SHEET CODE</th>
                            <th class="py-3 text-secondary small fw-bold">EXAM</th>
                            <th class="py-3 text-secondary small fw-bold">STATUS</th>
                            <th class="pe-4 py-3 text-end text-secondary small fw-bold">ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="isLoading">
                            <td colspan="5" class="text-center py-5">
                                <div class="spinner-border text-emerald"></div>
                                <p class="text-muted mt-2 mb-0">Loading data...</p>
                            </td>
                        </tr>

                        <tr v-else-if="sheets.length === 0">
                            <td colspan="5" class="text-center py-5">
                                <i class="bi bi-file-earmark-text text-muted mb-2 d-block" style="font-size: 2rem;"></i>
                                <span class="text-muted">No answer sheets found.</span>
                            </td>
                        </tr>

                        <tr v-else v-for="sheet in sheets" :key="sheet.id">

                            <td class="fw-bold text-emerald">{{ sheet.qr_payload }}</td>
                            <td>{{ sheet.exam?.Exam_Title || 'N/A' }}</td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ sheet.status }}</span>
                            </td>
                            <td class="pe-4 text-end">
                                <div class="btn-group shadow-sm rounded-3">
                                    <button @click="printSheet(sheet.id)" class="btn btn-light-success border-0 px-3"
                                        title="Print">
                                        <i class="bi bi-printer"></i>
                                    </button>
                                    <button @click="deleteSheet(sheet.id)" class="btn btn-light-danger border-0 px-3"
                                        title="Delete">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal fade" id="generateModal" tabindex="-1" ref="modalRef" data-bs-backdrop="static">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0">
                    <div class="modal-header bg-dark text-white border-0">
                        <h5 class="modal-title fw-bold">Generate Answer Sheets</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">SELECT EXAM</label>
                            <select v-model="form.exam_id"
                                class="form-select border-2 fw-bold text-emerald focus-ring-emerald">
                                <option value="">Select Exam...</option>
                                <option v-for="ex in availableExams" :key="ex.id" :value="ex.id">
                                    {{ ex.Exam_Title }}
                                </option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-secondary">NUMBER OF SHEETS</label>
                            <input v-model.number="form.count" type="number" min="1" max="200"
                                class="form-control border-2" placeholder="e.g. 5">
                        </div>
                        <p class="small text-muted mb-0">This will generate a multi-page PDF for printing.</p>
                    </div>
                    <div class="modal-footer bg-light border-top">
                        <button type="button" class="btn btn-light px-4 fw-bold" data-bs-dismiss="modal">CANCEL</button>
                        <button @click="generateSheets" class="btn btn-emerald px-4 fw-bold"
                            :disabled="isGenerating || !form.exam_id || !form.count">
                            <span v-if="isGenerating" class="spinner-border spinner-border-sm me-2"></span>
                            {{ isGenerating ? 'GENERATING...' : 'GENERATE' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import { Modal } from 'bootstrap';
import Swal from 'sweetalert2';

// State
const sheets = ref([]);
const availableExams = ref([]);
const isLoading = ref(false);
const isGenerating = ref(false);

const form = reactive({
    exam_id: '',
    count: 1
});

const modalRef = ref(null);
let modalInstance = null;

// SweetAlert Mixin for notifications
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true
});

const fetchSheets = async () => {
    isLoading.value = true;
    try {
        const [sheetsRes, examsRes] = await Promise.all([
            axios.get('/api/answer-sheets'),
            axios.get('/api/exams'),
        ]);

        // Use logic to handle potential non-array responses
        sheets.value = Array.isArray(sheetsRes.data) ? sheetsRes.data : [];
        availableExams.value = Array.isArray(examsRes.data) ? examsRes.data : [];
    } catch (e) {
        console.error('Failed to load data', e);
        Swal.fire({
            icon: 'error',
            title: 'Oops...',
            text: 'Failed to fetch data from the server.',
            confirmButtonColor: '#10b981'
        });
    } finally {
        isLoading.value = false;
    }
};

const openModal = () => {
    form.exam_id = '';
    form.count = 1;
    modalInstance.show();
};

const generateSheets = async () => {
    isGenerating.value = true;
    try {
        const res = await axios.post(
            '/api/answer-sheets/generate',
            { exam_id: form.exam_id, count: form.count },
            { responseType: 'blob' }
        );

        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        const headerName = res.headers['content-disposition'];
        const match = headerName?.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/i);
        const fileName = match ? match[1].replace(/['"]/g, '') : `answer_sheets_${form.exam_id}.pdf`;

        const link = document.createElement('a');
        link.href = url;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);

        modalInstance.hide();
        await fetchSheets();

        Toast.fire({
            icon: 'success',
            title: 'Answer sheets generated successfully'
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Generation Failed',
            text: 'Could not generate sheets. Check your network or inputs.',
            confirmButtonColor: '#ef4444'
        });
    } finally {
        isGenerating.value = false;
    }
};

const printSheet = async (id) => {
    try {
        const res = await axios.get(`/api/answer-sheets/${id}/print`, { responseType: 'blob' });
        const blob = new Blob([res.data], { type: 'application/pdf' });
        const url = window.URL.createObjectURL(blob);
        window.open(url, '_blank');
        window.URL.revokeObjectURL(url);
    } catch (e) {
        Toast.fire({
            icon: 'error',
            title: 'Failed to print sheet'
        });
    }
};

const deleteSheet = async (id) => {
    const result = await Swal.fire({
        title: 'Delete Sheet?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it!'
    });

    if (result.isConfirmed) {
        try {
            await axios.delete(`/api/answer-sheets/${id}`);
            await fetchSheets();
            Toast.fire({
                icon: 'success',
                title: 'Sheet deleted'
            });
        } catch (e) {
            Swal.fire('Error', 'Could not delete the record.', 'error');
        }
    }
};

onMounted(() => {
    fetchSheets();
    if (modalRef.value) {
        modalInstance = new Modal(modalRef.value);
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

.btn-emerald:disabled {
    background-color: #6ee7b7;
    opacity: 0.7;
}

.text-emerald {
    color: #10b981;
}

.focus-ring-emerald:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
}

.btn-light-success {
    color: #10b981;
    background: #ecfdf5;
}

.btn-light-success:hover {
    background: #d1fae5;
}

.btn-light-danger {
    color: #ef4444;
    background: #fef2f2;
}

.btn-light-danger:hover {
    background: #fee2e2;
}
</style>
