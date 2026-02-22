<template>
  <div class="page-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0">My Exams</h3>
      <button class="btn btn-outline-success btn-sm" :disabled="isLoading" @click="loadExams">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>

    <div v-if="isLoading" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="d-flex align-items-center text-muted">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading exams...
      </div>
    </div>

    <div v-else-if="errorMessage" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="alert alert-danger mb-0">{{ errorMessage }}</div>
    </div>

    <div v-else-if="!exams.length" class="card border-0 shadow-sm p-4 rounded-4">
      <p class="text-muted mb-0">No scanned exams yet. Scan a QR code from your dashboard to link an exam sheet.</p>
    </div>

    <div v-else class="row g-3">
      <div v-for="sheet in exams" :key="sheet.id" class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 exam-card">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <h5 class="fw-bold mb-1">{{ sheet.exam?.Exam_Title || 'Untitled Exam' }}</h5>
                <p class="text-muted small mb-0">{{ sheet.exam?.Exam_Type || 'Exam' }}</p>
              </div>
            </div>

            <div class="meta-row">
              <span class="meta-label">Exam Status</span>
              <span class="meta-value">{{ studentStatus(sheet.status) }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Date Scanned</span>
              <span class="meta-value">{{ formatDate(scannedDate(sheet)) }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';

const exams = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');

const loadExams = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/api/answer-sheets');
    exams.value = Array.isArray(data) ? data : [];
  } catch (error) {
    exams.value = [];
    errorMessage.value = error?.response?.data?.message || 'Failed to load exams.';
  } finally {
    isLoading.value = false;
  }
};

const studentStatus = (status) => {
  const value = String(status || '').toLowerCase();
  if (value === 'checked' || value === 'graded') return 'Graded';
  return 'Pending';
};

const scannedDate = (sheet) => {
  if (sheet?.scanned_at) return sheet.scanned_at;

  const value = String(sheet?.status || '').toLowerCase();
  if (value === 'scanned' || value === 'checked' || value === 'graded') {
    return sheet?.updated_at || null;
  }

  return null;
};

const formatDate = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';

  return date.toLocaleString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(loadExams);
</script>

<style scoped>
.exam-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.exam-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 0.7rem 1.4rem rgba(16, 185, 129, 0.15) !important;
}

.meta-row {
  display: flex;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 0.45rem 0;
  border-bottom: 1px solid #f1f5f9;
}

.meta-label {
  color: #64748b;
  font-size: 0.85rem;
}

.meta-value {
  color: #0f172a;
  font-weight: 600;
  font-size: 0.9rem;
  text-align: right;
  word-break: break-word;
}
</style>
