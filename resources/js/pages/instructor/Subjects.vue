<template>
  <div>
    <h2 class="fw-bold mb-1">Subjects Management</h2>
    <p class="text-muted">Manage Subjects records and information.</p>

    <div class="row g-4 mt-2">
      <div class="col-md-3" v-for="n in 4" :key="n">
        <div class="card border-0 shadow-sm p-4 rounded-4">
          <h6 class="text-muted">Total Data</h6>
          <h3 class="fw-bold mb-0">1,234</h3>
        </div>
      </div>
    </div>
  </div>
</template>