<template>
    <div class="auth-wrapper d-flex align-items-center justify-content-center bg-light">
        <div class="card shadow-sm border-0 auth-card">
            <div class="card-body p-5 position-relative">

                <button v-if="isCodeSent" @click="goBack"
                    class="btn btn-link text-emerald p-0 mb-3 text-decoration-none position-absolute top-0 start-0 mt-4 ms-4">
                    <i class="bi bi-arrow-left"></i> Back
                </button>

                <div class="text-center mb-4">
                    <h4 class="fw-bold mt-2">Reset Password</h4>
                    <p class="text-muted small">
                        {{ isCodeSent ? `Enter the 6-digit code sent to ${email}.` : `Enter your email and we'll send
                        you a recovery code.` }}
                    </p>
                </div>

                <form v-if="!isCodeSent" @submit.prevent="handleSendCode">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase">Email Address</label>
                        <input v-model="email" type="email" class="form-control" required
                            placeholder="yourname@example.com">
                    </div>

                    <button type="submit"
                        :class="['btn w-100 py-2 fw-bold mb-3 shadow-sm', email ? 'btn-emerald' : 'btn-secondary opacity-50']"
                        :disabled="!email">
                        SEND CODE
                    </button>

                    <router-link to="/login"
                        class="btn btn-outline-secondary w-100 py-2 fw-bold btn-sm text-decoration-none">
                        CANCEL
                    </router-link>
                </form>

                <div v-else class="text-center">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary text-uppercase d-block mb-3">Verification
                            Code</label>
                        <div class="d-flex justify-content-center gap-2">
                            <input v-for="(digit, index) in 6" :key="index" :ref="el => otpFields[index] = el"
                                type="text" class="form-control text-center fw-bold fs-4"
                                style="width: 45px; height: 55px;" maxlength="1" v-model="otp[index]"
                                @input="handleInput($event, index)" @keydown.delete="handleDelete(index)"
                                inputmode="numeric">
                        </div>
                    </div>

                    <button @click="handleVerifyCode" class="btn btn-emerald w-100 py-2 fw-bold mb-3">
                        VERIFY & RESET
                    </button>

                    <div class="mt-2">
                        <p v-if="timer > 0" class="small text-muted">
                            Resend code in <span class="fw-bold text-dark">{{ formatTime }}</span>
                        </p>
                        <button v-else @click="handleSendCode"
                            class="btn btn-link text-emerald text-decoration-none small fw-bold">
                            Resend Code
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive, computed, onUnmounted, nextTick } from 'vue';

const email = ref('');
const isCodeSent = ref(false);
const otp = reactive(['', '', '', '', '', '']);
const otpFields = ref([]); // Stores references to the input elements
const timer = ref(0);
let interval = null;

const formatTime = computed(() => {
    const minutes = Math.floor(timer.value / 60);
    const seconds = timer.value % 60;
    return `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
});

const startTimer = () => {
    timer.value = 120;
    if (interval) clearInterval(interval);
    interval = setInterval(() => {
        if (timer.value > 0) timer.value--;
        else clearInterval(interval);
    }, 1000);
};

const handleSendCode = () => {
    if (email.value) {
        isCodeSent.value = true;
        startTimer();
        // Focus first box after UI updates
        nextTick(() => otpFields.value[0]?.focus());
    }
};

const goBack = () => {
    isCodeSent.value = false;
    otp.fill(''); // Clear code if they go back
};

// AUTO-ADVANCE & NUMERIC FILTER
const handleInput = (event, index) => {
    const val = event.target.value;

    // Allow only numbers
    if (!/^\d$/.test(val)) {
        otp[index] = '';
        return;
    }

    // Move to next field
    if (val && index < 5) {
        otpFields.value[index + 1].focus();
    }
};

// BACKSPACE LOGIC
const handleDelete = (index) => {
    // If current box is empty, move to previous box
    if (!otp[index] && index > 0) {
        otpFields.value[index - 1].focus();
    }
};

const handleVerifyCode = () => {
    const code = otp.join('');
    if (code.length === 6) {
        alert('Verifying code: ' + code);
    } else {
        alert('Please complete the 6-digit code.');
    }
};

onUnmounted(() => { if (interval) clearInterval(interval); });
</script>

<style scoped>
.auth-wrapper {
    min-height: 100vh;
}

.auth-card {
    width: 100%;
    max-width: 420px;
    border-radius: 1rem;
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

.text-emerald {
    color: #10b981;
}

.form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.2rem rgba(16, 185, 129, 0.25);
}
</style>