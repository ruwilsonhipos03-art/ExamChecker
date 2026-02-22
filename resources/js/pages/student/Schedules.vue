<template>
  <div class="page-container">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h3 class="fw-bold mb-0">My Exam Schedules</h3>
      <button class="btn btn-outline-success btn-sm" :disabled="isLoading" @click="loadSchedules">
        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
      </button>
    </div>

    <div v-if="isLoading" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="d-flex align-items-center text-muted">
        <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
        Loading schedules...
      </div>
    </div>

    <div v-else-if="errorMessage" class="card border-0 shadow-sm p-4 rounded-4">
      <div class="alert alert-danger mb-0">{{ errorMessage }}</div>
    </div>

    <div v-else-if="!schedules.length" class="card border-0 shadow-sm p-4 rounded-4">
      <p class="text-muted mb-0">No exam schedules found yet.</p>
    </div>

    <div v-else class="row g-3">
      <div v-for="item in schedules" :key="item.id" class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 schedule-card">
          <div class="card-body p-4">
            <h5 class="fw-bold mb-1">{{ item.exam_title || 'Untitled Exam' }}</h5>
            <p class="text-muted small mb-3">{{ item.exam_type || 'Exam' }}</p>

            <div class="meta-row">
              <span class="meta-label">Exam Date</span>
              <span class="meta-value">{{ formatDate(item.scheduled_date) }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Exam Time</span>
              <span class="meta-value">{{ formatTime(item.scheduled_time) }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Location</span>
              <span class="meta-value">{{ item.location || '-' }}</span>
            </div>
            <div class="meta-row">
              <span class="meta-label">Schedule Status</span>
              <span class="meta-value">{{ prettyStatus(item.schedule_status) }}</span>
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

const schedules = ref([]);
const isLoading = ref(true);
const errorMessage = ref('');

const loadSchedules = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  try {
    const { data } = await axios.get('/api/student/schedules');
    schedules.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    schedules.value = [];
    errorMessage.value = error?.response?.data?.message || 'Failed to load schedules.';
  } finally {
    isLoading.value = false;
  }
};

const prettyStatus = (value) => {
  const text = String(value || 'scheduled').trim().toLowerCase();
  return text.charAt(0).toUpperCase() + text.slice(1);
};

const formatDate = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleDateString(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
  });
};

const formatTime = (value) => {
  if (!value) return '-';
  const date = new Date(`1970-01-01T${value}`);
  if (Number.isNaN(date.getTime())) return String(value);
  return date.toLocaleTimeString(undefined, {
    hour: '2-digit',
    minute: '2-digit',
  });
};

onMounted(loadSchedules);
</script>

<style scoped>
.schedule-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.schedule-card:hover {
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
