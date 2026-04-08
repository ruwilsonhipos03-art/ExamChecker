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
                <div class="small text-muted mb-2">
                    Pick exactly {{ requiredSelectionCount }} qualified program{{ requiredSelectionCount > 1 ? 's' : '' }}. After selection, proceed to each selected program's
                    office/building to take the screening exam and scan the QR code again.
                </div>
                <div v-if="lockReason" class="alert mb-0 py-2 px-3"
                    :class="selectionLocked ? 'alert-warning' : 'alert-info'">
                    {{ lockReason }}
                </div>
            </div>

            <div v-if="workflowPassedPrograms.length" class="card border-0 shadow-sm p-4 rounded-4 mb-3">
                <div class="fw-semibold mb-2">Post-Screening Decision</div>
                <div v-for="item in workflowPassedPrograms" :key="`decision-${item.program_id}`" class="meta-row align-items-center">
                    <div>
                        <div class="fw-semibold">{{ item.program_name || `Program #${item.program_id}` }}</div>
                        <div class="small text-muted">
                            Passed
                            <span v-if="item.total_score !== null">({{ item.total_score }})</span>
                            <span v-if="item.rank">- Choice #{{ item.rank }}</span>
                        </div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap justify-content-end">
                        <span v-if="workflow.final_program_id === item.program_id" class="badge text-bg-success">
                            Final Program
                        </span>
                        <template v-else>
                            <button
                                v-if="item.can_continue && !item.continued"
                                class="btn btn-outline-primary btn-sm"
                                :disabled="decisionSaving || workflow.final_program_id"
                                @click="saveDecision(item.program_id, 'continue')">
                                Continue to Next
                            </button>
                            <span v-else-if="item.continued" class="badge text-bg-info">Continue Selected</span>
                            <button
                                class="btn btn-success btn-sm"
                                :disabled="decisionSaving || workflow.final_program_id"
                                @click="saveDecision(item.program_id, 'pick')">
                                Pick This Program
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <div v-if="selectedStatusRows.length" class="card border-0 shadow-sm p-4 rounded-4 mb-3">
                <div class="fw-semibold mb-2">Selected Program Screening Status</div>
                <div v-for="item in selectedStatusRows" :key="item.program_id" class="meta-row">
                    <span class="meta-label">{{ item.program_name || `Program #${item.program_id}` }}</span>
                    <span class="meta-value">
                        <span v-if="item.status === 'failed'" class="text-danger">Failed ({{ item.total_score }})</span>
                        <span v-else-if="item.status === 'passed'" class="text-success">Passed ({{ item.total_score
                            }})</span>
                        <span v-else-if="item.status === 'in_progress'" class="text-warning">Attempted - Waiting for
                            result</span>
                        <span v-else class="text-muted">No screening attempt yet</span>
                    </span>
                </div>
            </div>

            <div v-if="screeningAttempts.length" class="card border-0 shadow-sm p-4 rounded-4 mb-3">
                <div class="fw-semibold mb-2">Screening Exams Already Taken</div>
                <div v-for="item in screeningAttempts" :key="item.program_id" class="meta-row">
                    <span class="meta-label">{{ item.program_name || `Program #${item.program_id}` }}</span>
                    <span class="meta-value">
                        <span v-if="item.status === 'failed'" class="text-danger">Taken - Failed ({{ item.total_score ??
                            '-' }})</span>
                        <span v-else-if="item.status === 'passed'" class="text-success">Taken - Passed ({{
                            item.total_score ?? '-' }})</span>
                        <span v-else class="text-warning">Taken - In Progress</span>
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
                                <span v-if="program.screening_attempted" class="badge text-bg-secondary">Already
                                    Taken</span>
                                <span v-else class="badge text-bg-success">Qualified</span>
                            </div>
                            <div class="meta-row"><span class="meta-label">Department</span><span class="meta-value">{{
                                    program.College_Name || '-' }}</span></div>
                            <div class="meta-row"><span class="meta-label">Minimum Total</span><span
                                    class="meta-value">{{ program.minimum_total_score }}</span></div>
                            <div class="meta-row"><span class="meta-label">Your Total</span><span class="meta-value">{{
                                    program.student_total_score }}</span></div>
                            <div class="meta-row"><span class="meta-label">Match Score</span><span class="meta-value">{{
                                    program.weighted_score }}</span></div>
                            <div v-if="program.screening_attempted" class="meta-row">
                                <span class="meta-label">Screening Status</span>
                                <span class="meta-value">
                                    <span v-if="program.screening_status === 'failed'" class="text-danger">
                                        Failed ({{ program.screening_total_score ?? '-' }})
                                    </span>
                                    <span v-else-if="program.screening_status === 'passed'"
                                        class="text-success">
                                        Passed ({{ program.screening_total_score ?? '-' }})
                                    </span>
                                    <span v-else class="text-warning">In Progress</span>
                                </span>
                            </div>
                            <div v-if="program.screening_attempted && program.screening_exam_title" class="meta-row">
                                <span class="meta-label">Screening Exam</span>
                                <span class="meta-value">{{ program.screening_exam_title }}</span>
                            </div>

                            <button class="btn btn-sm w-100 mt-3 fw-semibold"
                                :class="isSelected(program.program_id) ? 'btn-success' : 'btn-outline-success'"
                                :disabled="selectionLocked || program.screening_attempted || (!isSelected(program.program_id) && selectedProgramIds.length >= 3)"
                                @click="toggleProgram(program.program_id)">
                                {{
                                    program.screening_attempted
                                        ? 'Already Took Screening'
                                        : (isSelected(program.program_id) ? `Selected #${selectedRank(program.program_id)}` :
                                'Select Program')
                                }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm p-4 rounded-4 mt-3">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="small">
                        Selected: <span class="fw-bold">{{ selectedProgramIds.length }}</span>/{{ requiredSelectionCount }}
                    </div>
                    <button class="btn btn-success"
                        :disabled="selectionLocked || saving || selectedProgramIds.length !== requiredSelectionCount" @click="saveSelection">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-2"></span>
                        {{ selectionLocked ? 'Selection Locked' : (requiredSelectionCount === 1 ? 'Save Program Choice' : 'Save Top 3 Choices') }}
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
const decisionSaving = ref(false);
const errorMessage = ref('');

const eligible = ref(false);
const eligibilityMessage = ref('');
const answerSheetId = ref(null);
const examName = ref('');
const studentScores = ref({});

const programs = ref([]);
const selectedProgramIds = ref([]);
const selectionLocked = ref(false);
const canRepick = ref(true);
const lockReason = ref('');
const screeningStatuses = ref([]);
const screeningAttempts = ref([]);
const workflow = ref({
    final_program_id: null,
    continued_program_ids: [],
    passed_programs: [],
    allow_new_single_program_pick: false,
});

const qualifiedPrograms = computed(() => {
    return programs.value.filter((item) => item.is_qualified && !item.screening_attempted);
});

const selectedStatusRows = computed(() => {
    const statusMap = new Map(
        screeningStatuses.value.map((item) => [Number(item.program_id), item])
    );
    const programMap = new Map(
        programs.value.map((item) => [Number(item.program_id), item])
    );

    return selectedProgramIds.value.map((programId) => {
        const key = Number(programId);
        const fromStatus = statusMap.get(key);
        if (fromStatus) return fromStatus;

        const program = programMap.get(key);
        return {
            program_id: key,
            program_name: String(program?.program_name || ''),
            status: 'no_attempt',
            exam_title: null,
            total_score: null,
        };
    });
});

const workflowPassedPrograms = computed(() => {
    return Array.isArray(workflow.value?.passed_programs) ? workflow.value.passed_programs : [];
});

const requiredSelectionCount = computed(() => {
    return workflow.value?.allow_new_single_program_pick ? 1 : 3;
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
        screeningAttempts.value = Array.isArray(payload.screening_attempts) ? payload.screening_attempts : [];
        workflow.value = payload.workflow || {
            final_program_id: null,
            continued_program_ids: [],
            passed_programs: [],
            allow_new_single_program_pick: false,
        };

        const savedIds = Array.isArray(payload.selected_program_ids) ? payload.selected_program_ids.map((id) => Number(id)) : [];
        selectedProgramIds.value = workflow.value?.allow_new_single_program_pick ? [] : savedIds.slice(0, 3);
    } catch (error) {
        eligible.value = false;
        programs.value = [];
        selectedProgramIds.value = [];
        selectionLocked.value = false;
        canRepick.value = true;
        lockReason.value = '';
        screeningStatuses.value = [];
        screeningAttempts.value = [];
        workflow.value = {
            final_program_id: null,
            continued_program_ids: [],
            passed_programs: [],
            allow_new_single_program_pick: false,
        };
        errorMessage.value = error?.response?.data?.message || 'Failed to load recommendations.';
    } finally {
        loading.value = false;
    }
};

const saveDecision = async (programId, action) => {
    decisionSaving.value = true;
    try {
        await axios.post('/api/student/program-recommendations/decision', {
            program_id: Number(programId),
            action,
        });
        await loadRecommendations();
        window.Toast.fire({
            icon: 'success',
            title: action === 'pick' ? 'Final program selected' : 'Proceed to next screening',
        });
    } catch (error) {
        window.Swal.fire({
            icon: 'error',
            title: 'Decision Failed',
            text: error?.response?.data?.message || 'Unable to save decision.',
            confirmButtonColor: '#10b981',
        });
    } finally {
        decisionSaving.value = false;
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

    if (selectedProgramIds.value.length !== requiredSelectionCount.value) {
        window.Swal.fire({
            icon: 'warning',
            title: `Select ${requiredSelectionCount.value} Program${requiredSelectionCount.value > 1 ? 's' : ''}`,
            text: `Please select exactly ${requiredSelectionCount.value} qualified program${requiredSelectionCount.value > 1 ? 's' : ''}.`,
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
