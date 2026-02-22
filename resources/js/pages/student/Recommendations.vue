<template>
  <div class="page-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0">Program Recommendations</h3>
      <button class="btn btn-outline-success btn-sm" :disabled="loading" @click="loadRecommendations">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>

    <div v-if="loading" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="d-flex align-items-center text-muted">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading recommendations...
      </div>
    </div>

    <div v-else-if="errorMessage" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="alert alert-danger mb-0">{{ errorMessage }}</div>
    </div>

    <div v-else-if="!eligible" class="card border-0 shadow-sm p-4 rounded-4">
      <p class="text-muted mb-0">{{ eligibilityMessage }}</p>
    </div>

    <template v-else>
      <div class="card border-0 shadow-sm p-4 rounded-4 mb-3">
        <div class="small text-muted mb-2">Source Exam: <span class="fw-semibold text-dark">{{ examName || '-' }}</span></div>
        <div class="small text-muted mb-2">
          Scores: Math {{ studentScores.math }}, English {{ studentScores.english }}, Science {{ studentScores.science }}, Social Science {{ studentScores.social_science }}, Total {{ studentScores.total }}
        </div>
        <div class="small text-muted mb-2">
          Pick exactly 3 qualified programs. After selection, proceed to each selected program's office/building to take the screening exam and scan the QR code again.
        </div>
        <div v-if="lockReason" class="alert mb-0 py-2 px-3" :class="selectionLocked ? 'alert-warning' : 'alert-info'">
          {{ lockReason }}
        </div>
      </div>

      <div v-if="screeningStatuses.length" class="card border-0 shadow-sm p-4 rounded-4 mb-3">
        <div class="fw-semibold mb-2">Selected Program Screening Status</div>
        <div v-for="item in screeningStatuses" :key="item.program_id" class="meta-row">
          <span class="meta-label">{{ item.program_name || `Program #${item.program_id}` }}</span>
          <span class="meta-value">
            <span v-if="item.status === 'failed'" class="text-danger">Failed ({{ item.total_score }})</span>
            <span v-else-if="item.status === 'passed'" class="text-success">Passed ({{ item.total_score }})</span>
            <span v-else class="text-muted">No screening attempt yet</span>
          </span>
        </div>
      </div>

      <div v-if="qualifiedPrograms.length === 0" class="card border-0 shadow-sm p-4 rounded-4">
        <p class="text-muted mb-0">No programs currently match your score based on configured requirements.</p>
      </div>

      <div v-else class="row g-3">
        <div v-for="program in qualifiedPrograms" :key="program.program_id" class="col-12 col-md-6">
          <div class="card border-0 shadow-sm rounded-4 h-100 recommendation-card">
            <div class="card-body p-4">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold mb-0">{{ program.program_name }}</h5>
                <span class="badge text-bg-success">Qualified</span>
              </div>
              <div class="meta-row"><span class="meta-label">Department</span><span class="meta-value">{{ program.department_name || '-' }}</span></div>
              <div class="meta-row"><span class="meta-label">Minimum Total</span><span class="meta-value">{{ program.minimum_total_score }}</span></div>
              <div class="meta-row"><span class="meta-label">Your Total</span><span class="meta-value">{{ program.student_total_score }}</span></div>
              <div class="meta-row"><span class="meta-label">Match Score</span><span class="meta-value">{{ program.weighted_score }}</span></div>

              <button
                class="btn btn-sm w-100 mt-3 fw-semibold"
                :class="isSelected(program.program_id) ? 'btn-success' : 'btn-outline-success'"
                :disabled="selectionLocked || (!isSelected(program.program_id) && selectedProgramIds.length >= 3)"
                @click="toggleProgram(program.program_id)"
              >
                {{ isSelected(program.program_id) ? `Selected #${selectedRank(program.program_id)}` : 'Select Program' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm p-4 rounded-4 mt-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
          <div class="small">
            Selected: <span class="fw-bold">{{ selectedProgramIds.length }}</span>/3
          </div>
          <button class="btn btn-success" :disabled="selectionLocked || saving || selectedProgramIds.length !== 3" @click="saveSelection">
            <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
            {{ selectionLocked ? 'Selection Locked' : 'Save Top 3 Choices' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const loading = ref(false);
const saving = ref(false);
const errorMessage = ref('');

const eligible = ref(false);
const eligibilityMessage = ref('');
const answerSheetId = ref(null);
const examName = ref('');
const studentScores = ref({
  math: 0,
  english: 0,
  science: 0,
  social_science: 0,
  total: 0,
});

const programs = ref([]);
const selectedProgramIds = ref([]);
const selectionLocked = ref(false);
const canRepick = ref(true);
const lockReason = ref('');
const screeningStatuses = ref([]);

const qualifiedPrograms = computed(() => {
  return programs.value.filter((item) => item.is_qualified);
});

const isSelected = (programId) => selectedProgramIds.value.includes(programId);
const selectedRank = (programId) => selectedProgramIds.value.findIndex((id) => id === programId) + 1;

const toggleProgram = (programId) => {
  if (selectionLocked.value) return;

  if (isSelected(programId)) {
    selectedProgramIds.value = selectedProgramIds.value.filter((id) => id !== programId);
    return;
  }

  if (selectedProgramIds.value.length >= 3) return;
  selectedProgramIds.value = [...selectedProgramIds.value, programId];
};

const loadRecommendations = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const params = {};
    const queryId = route.query?.answer_sheet_id;
    if (queryId) params.answer_sheet_id = queryId;

    const { data } = await axios.get('/api/student/program-recommendations', { params });
    const payload = data?.data || {};

    eligible.value = Boolean(payload.eligible);
    eligibilityMessage.value = payload.message || '';
    answerSheetId.value = payload.answer_sheet_id || null;
    examName.value = payload.exam_name || '';
    studentScores.value = payload.student_scores || studentScores.value;
    programs.value = Array.isArray(payload.programs) ? payload.programs : [];
    selectionLocked.value = Boolean(payload.selection_locked);
    canRepick.value = Boolean(payload.can_repick);
    lockReason.value = payload.lock_reason || '';
    screeningStatuses.value = Array.isArray(payload.screening_statuses) ? payload.screening_statuses : [];

    const savedIds = Array.isArray(payload.selected_program_ids) ? payload.selected_program_ids.map((id) => Number(id)) : [];
    const qualifiedIds = new Set(qualifiedPrograms.value.map((p) => p.program_id));
    selectedProgramIds.value = savedIds.filter((id) => qualifiedIds.has(id)).slice(0, 3);
  } catch (error) {
    eligible.value = false;
    programs.value = [];
    selectedProgramIds.value = [];
    selectionLocked.value = false;
    canRepick.value = true;
    lockReason.value = '';
    screeningStatuses.value = [];
    errorMessage.value = error?.response?.data?.message || 'Failed to load recommendations.';
  } finally {
    loading.value = false;
  }
};

const saveSelection = async () => {
  if (selectionLocked.value || !canRepick.value) {
    window.Swal.fire({
      icon: 'warning',
      title: 'Selection Locked',
      text: lockReason.value || 'You cannot re-pick unless you fail all 3 selected screening exams.',
      confirmButtonColor: '#10b981',
    });
    return;
  }

  if (selectedProgramIds.value.length !== 3) {
    window.Swal.fire({
      icon: 'warning',
      title: 'Select 3 Programs',
      text: 'Please select exactly 3 qualified programs.',
      confirmButtonColor: '#10b981',
    });
    return;
  }

  saving.value = true;
  try {
    await axios.post('/api/student/program-recommendations/select', {
      answer_sheet_id: answerSheetId.value,
      program_ids: selectedProgramIds.value,
    });

    window.Toast.fire({
      icon: 'success',
      title: 'Top 3 program choices saved',
    });

    await loadRecommendations();
  } catch (error) {
    window.Swal.fire({
      icon: 'error',
      title: 'Save Failed',
      text: error?.response?.data?.message || 'Unable to save program choices.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    saving.value = false;
  }
};

onMounted(loadRecommendations);
</script>

<style scoped>
.recommendation-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.recommendation-card:hover {
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
}
</style>
