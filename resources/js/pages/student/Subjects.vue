<template>
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="fw-bold mb-1">Subjects</h3>
        <p class="text-muted mb-0 small">Subjects and their assigned instructors</p>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="loadSubjects" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
        Refresh
      </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-body">
        <input
          v-model.trim="search"
          type="text"
          class="form-control form-control-sm"
          placeholder="Search by subject or instructor..."
        >
      </div>
    </div>

    <div v-if="loading" class="card border-0 shadow-sm rounded-4">
      <div class="card-body text-center py-4 text-muted">Loading subjects...</div>
    </div>

    <div v-else-if="!filteredSubjects.length" class="card border-0 shadow-sm rounded-4">
      <div class="card-body text-center py-4 text-muted">No assigned subjects found.</div>
    </div>

    <div v-else class="row g-3">
      <div v-for="row in filteredSubjects" :key="row.id" class="col-12 col-md-6 col-xl-4">
        <div class="card border-0 shadow-sm rounded-4 h-100 subject-card">
          <div class="card-body">
            <div class="d-flex align-items-start justify-content-between mb-2">
              <h5 class="fw-bold mb-0">{{ row.subject_name || '-' }}</h5>
              <span class="badge text-bg-success-subtle text-success border">Assigned</span>
            </div>

            <div class="small text-muted mb-1">Instructor</div>
            <div class="fw-semibold mb-3">{{ row.instructor_name || '-' }}</div>

            <div class="small text-muted mb-1">Assigned At</div>
            <div class="fw-semibold">{{ formatDateTime(row.assigned_at) }}</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const subjects = ref([]);
const loading = ref(false);
const search = ref('');

const filteredSubjects = computed(() => {
  const q = search.value.toLowerCase();
  if (!q) return subjects.value;

  return subjects.value.filter((row) => {
    const text = [
      row.subject_name,
      row.instructor_name,
    ].map((item) => String(item || '').toLowerCase()).join(' ');

    return text.includes(q);
  });
});

const formatDateTime = (value) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString();
};

const loadSubjects = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/student/subjects');
    subjects.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    subjects.value = [];
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to load subjects',
      text: error?.response?.data?.message || 'Please try again.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    loading.value = false;
  }
};

onMounted(loadSubjects);
</script>

<style scoped>
.subject-card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.subject-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 0.75rem 1.5rem rgba(15, 23, 42, 0.08) !important;
}
</style>
