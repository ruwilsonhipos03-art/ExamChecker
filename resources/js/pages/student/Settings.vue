<template>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h4 class="fw-bold mb-1">Settings</h4>
                                <div class="text-muted small">Manage basic preferences</div>
                            </div>
                            <i class="bi bi-gear fs-3 text-emerald"></i>
                        </div>

                        <div class="border-top pt-4">
                            <h6 class="fw-bold mb-3">Notifications</h6>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <div class="fw-semibold">Email updates</div>
                                    <div class="text-muted small">Receive announcements and reminders.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="emailUpdates">
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <div class="fw-semibold">Exam alerts</div>
                                    <div class="text-muted small">Be notified when schedules change.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="examAlerts">
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-4">
                            <h6 class="fw-bold mb-3">Appearance</h6>
                            <div class="d-flex align-items-center justify-content-between py-2">
                                <div>
                                    <div class="fw-semibold">Compact layout</div>
                                    <div class="text-muted small">Reduce spacing on lists and cards.</div>
                                </div>
                                <div class="form-check form-switch m-0">
                                    <input class="form-check-input" type="checkbox" v-model="compactLayout">
                                </div>
                            </div>
                        </div>

                        <div class="border-top pt-4 d-flex justify-content-end">
                            <button class="btn btn-emerald px-4" @click="saveSettings">
                                Save Changes
                            </button>
                        </div>
                    </div>
                </div>
                <div v-if="saved" class="alert alert-success mt-3">Settings saved.</div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';

const emailUpdates = ref(true);
const examAlerts = ref(true);
const compactLayout = ref(false);
const saved = ref(false);

const saveSettings = () => {
    saved.value = true;
    window.setTimeout(() => {
        saved.value = false;
    }, 2000);
};
</script>

<style scoped>
.text-emerald {
    color: #10b981;
}

.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
}

.btn-emerald:hover {
    background-color: #059669;
    color: white;
}
</style>
