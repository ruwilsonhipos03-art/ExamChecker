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
import axios from 'axios';
import Swal from 'sweetalert2';
import { useRouter } from 'vue-router';

const router = useRouter();
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

const handleSendCode = async () => {
    if (!email.value) return;
    try {
        await axios.post('/api/forgot-password/send-code', { email: email.value });
        isCodeSent.value = true;
        startTimer();
        nextTick(() => otpFields.value[0]?.focus());
        Swal.fire({
            icon: 'success',
            title: 'Code Sent',
            text: 'Please check your email for the 6-digit code.',
            confirmButtonColor: '#10b981'
        });
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Failed',
            text: e.response?.data?.message || 'Unable to send code.',
            confirmButtonColor: '#10b981'
        });
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

const handleVerifyCode = async () => {
    const code = otp.join('');
    if (code.length !== 6) {
        return Swal.fire({
            icon: 'warning',
            title: 'Incomplete Code',
            text: 'Please complete the 6-digit code.',
            confirmButtonColor: '#10b981'
        });
    }

    try {
        const { value: formValues } = await Swal.fire({
            title: 'Set New Password',
            html: `
                <div class="text-start">
                    <label class="form-label small fw-bold text-secondary text-uppercase">New Password</label>
                    <input id="swal-new-password" type="password" class="form-control mb-3" placeholder="Minimum 8 characters">
                    <label class="form-label small fw-bold text-secondary text-uppercase">Confirm Password</label>
                    <input id="swal-confirm-password" type="password" class="form-control" placeholder="Re-enter password">
                </div>
            `,
            focusConfirm: false,
            confirmButtonText: 'Reset Password',
            confirmButtonColor: '#10b981',
            showCancelButton: true,
            preConfirm: () => {
                const newPassword = document.getElementById('swal-new-password').value;
                const confirmPassword = document.getElementById('swal-confirm-password').value;
                if (!newPassword || newPassword.length < 8) {
                    Swal.showValidationMessage('Password must be at least 8 characters.');
                    return false;
                }
                if (newPassword !== confirmPassword) {
                    Swal.showValidationMessage('Passwords do not match.');
                    return false;
                }
                return { newPassword, confirmPassword };
            }
        });

        if (!formValues) {
            return;
        }

        await axios.post('/api/forgot-password/reset', {
            email: email.value,
            code,
            password: formValues.newPassword,
            password_confirmation: formValues.confirmPassword
        });

        await Swal.fire({
            icon: 'success',
            title: 'Password Updated',
            text: 'You can now log in with your new password.',
            confirmButtonColor: '#10b981'
        });

        isCodeSent.value = false;
        otp.fill('');
        router.push('/login');
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Reset Failed',
            text: e.response?.data?.message || 'Invalid or expired code.',
            confirmButtonColor: '#10b981'
        });
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
