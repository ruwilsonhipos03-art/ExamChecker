<template>
  <div class="page-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0">My Reports</h3>
      <button class="btn btn-outline-success btn-sm" :disabled="loading" @click="loadReports">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>

    <div v-if="loading" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="d-flex align-items-center text-muted">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading reports...
      </div>
    </div>

    <div v-else-if="errorMessage" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="alert alert-danger mb-0">{{ errorMessage }}</div>
    </div>

    <div v-else-if="!reports.length" class="card border-0 shadow-sm p-4 rounded-4">
      <p class="text-muted mb-0">No checked exam results yet.</p>
    </div>

    <div v-else class="row g-3">
      <div v-for="item in reports" :key="item.answer_sheet_id" class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 report-card">
          <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <h5 class="fw-bold mb-0">{{ item.exam_name }}</h5>
              <span class="badge" :class="item.result === 'Passed' ? 'text-bg-success' : 'text-bg-danger'">
                {{ item.result }}
              </span>
            </div>

            <div class="meta-row"><span class="meta-label">Math</span><span class="meta-value">{{ item.math }}</span></div>
            <div class="meta-row"><span class="meta-label">English</span><span class="meta-value">{{ item.english }}</span></div>
            <div class="meta-row"><span class="meta-label">Science</span><span class="meta-value">{{ item.science }}</span></div>
            <div class="meta-row"><span class="meta-label">Social Science</span><span class="meta-value">{{ item.social_science }}</span></div>
            <div class="meta-row"><span class="meta-label fw-semibold">Total</span><span class="meta-value fw-bold">{{ item.total }}</span></div>
            <div class="meta-row"><span class="meta-label">Checked At</span><span class="meta-value">{{ formatDateTime(item.checked_at) }}</span></div>

            <button
              v-if="item.can_recommend_program"
              class="btn btn-success btn-sm w-100 mt-3 fw-semibold"
              @click="goToRecommendation(item)"
            >
              Program Recommendation
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';

const loading = ref(false);
const reports = ref([]);
const errorMessage = ref('');
const router = useRouter();

const loadReports = async () => {
  loading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/api/student/reports');
    reports.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    reports.value = [];
    errorMessage.value = error?.response?.data?.message || 'Failed to load reports.';
  } finally {
    loading.value = false;
  }
};

const formatDateTime = (value) => {
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

const goToRecommendation = (item) => {
  const answerSheetId = item?.answer_sheet_id;
  if (!answerSheetId) {
    router.push('/student/recommendations');
    return;
  }

  router.push({
    path: '/student/recommendations',
    query: { answer_sheet_id: String(answerSheetId) },
  });
};

onMounted(loadReports);
</script>

<style scoped>
.report-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.report-card:hover {
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
