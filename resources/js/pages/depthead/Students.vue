<template>
  <div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <h3 class="fw-bold mb-1">Students</h3>
        <p class="text-muted mb-0 small">Students under your department programs</p>
      </div>
      <button class="btn btn-outline-success btn-sm" @click="loadStudents" :disabled="loading">
        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
        Refresh
      </button>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
      <div class="card-body p-0">
        <div class="p-3 border-bottom">
          <input v-model="search" type="text" class="form-control form-control-sm"
            placeholder="Search by student name, number, program, username, or email">
        </div>

        <div class="table-responsive">
          <table class="table table-hover mb-0 align-middle">
            <thead class="table-light">
              <tr>
                <th class="ps-3">Student #</th>
                <th>Name</th>
                <th>Program</th>
                <th>Username</th>
                <th>Email</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="loading">
                <td colspan="5" class="text-center py-4 text-muted">Loading students...</td>
              </tr>
              <tr v-else-if="!filteredStudents.length">
                <td colspan="5" class="text-center py-4 text-muted">No students found.</td>
              </tr>
              <tr v-for="student in filteredStudents" :key="student.id">
                <td class="ps-3 fw-semibold">{{ student.student_number || '-' }}</td>
                <td>{{ student.full_name || '-' }}</td>
                <td>{{ student.program_name || '-' }}</td>
                <td>{{ student.username || '-' }}</td>
                <td>{{ student.email || '-' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const students = ref([]);
const loading = ref(false);
const search = ref('');

const filteredStudents = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return students.value;

  return students.value.filter((s) => {
    const haystack = [
      s.student_number,
      s.full_name,
      s.program_name,
      s.username,
      s.email,
    ]
      .map((item) => String(item || '').toLowerCase())
      .join(' ');

    return haystack.includes(q);
  });
});

const loadStudents = async () => {
  loading.value = true;
  try {
    const { data } = await axios.get('/api/dept_head/students');
    students.value = Array.isArray(data?.data) ? data.data : [];
  } catch (error) {
    students.value = [];
    window.Swal?.fire({
      icon: 'error',
      title: 'Failed to load students',
      text: error?.response?.data?.message || 'Please try again.',
      confirmButtonColor: '#10b981',
    });
  } finally {
    loading.value = false;
  }
};

onMounted(loadStudents);
</script>
