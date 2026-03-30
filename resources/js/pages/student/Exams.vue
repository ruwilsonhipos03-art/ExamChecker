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
                <p v-if="isScreeningExam(sheet)" class="small fw-semibold text-success mb-0 mt-1">
                  {{ screeningProgramName(sheet) }}
                </p>
              </div>
            </div>

            <div v-if="screeningMeta(sheet)" class="meta-row">
              <span class="meta-label">Program</span>
              <span class="meta-value">{{ screeningProgramName(sheet) || '-' }}</span>
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
import { onMounted, onUnmounted, ref } from 'vue';
import axios from 'axios';
import { useNotifications } from '../../composables/useNotifications';

const exams = ref([]);
const selectedPrograms = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');
const latestExamUpdate = ref(null);
const { markSeen } = useNotifications({ poll: false });

const loadExams = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const [{ data: sheetsData }, { data: recommendationData }] = await Promise.all([
      axios.get('/api/answer-sheets'),
      axios.get('/api/student/program-recommendations').catch(() => ({ data: null })),
    ]);

    exams.value = Array.isArray(sheetsData) ? sheetsData : [];
    latestExamUpdate.value = exams.value
      .map((sheet) => sheet.updated_at)
      .filter(Boolean)
      .sort()
      .slice(-1)[0] || null;

    const payload = recommendationData?.data || {};
    const programs = Array.isArray(payload.programs) ? payload.programs : [];
    const selectedIds = Array.isArray(payload.selected_program_ids) ? payload.selected_program_ids.map((id) => Number(id)) : [];
    const programById = new Map(programs.map((item) => [Number(item.program_id), item]));
    selectedPrograms.value = selectedIds
      .map((id, index) => {
        const row = programById.get(id);
        if (!row) return null;
        return {
          rank: index + 1,
          programName: String(row.program_name || ''),
        };
      })
      .filter(Boolean);
  } catch (error) {
    exams.value = [];
    selectedPrograms.value = [];
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

const isScreeningExam = (sheet) => {
  const type = String(sheet?.exam?.Exam_Type || '').trim().toLowerCase();
  return type === 'screening' || type === 'screening exam';
};

const screeningMeta = (sheet) => {
  if (!isScreeningExam(sheet)) return null;

  const fromExamProgram = String(sheet?.exam?.program?.Program_Name || '').trim();
  if (fromExamProgram) {
    return {
      rank: null,
      programName: fromExamProgram,
    };
  }

  const examTitle = String(sheet?.exam?.Exam_Title || '').trim().toLowerCase();
  if (!examTitle) return null;

  const matched = selectedPrograms.value.find((item) => {
    const programName = String(item.programName || '').trim().toLowerCase();
    return programName && examTitle.includes(programName);
  });

  return matched || null;
};

const screeningProgramName = (sheet) => {
  if (!isScreeningExam(sheet)) return '';

  const meta = screeningMeta(sheet);
  const fromSelection = String(meta?.programName || '').trim();
  if (fromSelection) return fromSelection;

  return 'Screening Exam';
};

onMounted(loadExams);

onUnmounted(() => {
  if (latestExamUpdate.value) {
    markSeen('exams', latestExamUpdate.value);
  }
});
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
