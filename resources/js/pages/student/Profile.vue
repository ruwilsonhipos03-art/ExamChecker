<template>
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <div>
                                <h4 class="fw-bold mb-1">Profile</h4>
                                <div class="text-muted small">Manage your account details</div>
                            </div>
                            <span class="badge" :class="isVerified ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning'">
                                {{ isVerified ? 'Email Verified' : 'Email Not Verified' }}
                            </span>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">FULL NAME</div>
                                <div class="fw-semibold">{{ fullName }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">EMAIL</div>
                                <div class="d-flex flex-column gap-2">
                                    <input v-model.trim="emailInput" type="email" class="form-control"
                                        placeholder="Enter your email" :disabled="emailSaving">
                                    <button class="btn btn-outline-emerald align-self-start" :disabled="emailSaving || !emailChanged"
                                        @click="updateEmail">
                                        <span v-if="emailSaving" class="spinner-border spinner-border-sm me-2"></span>
                                        Update Email
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">USERNAME</div>
                                <div class="fw-semibold">{{ user.username || '—' }}</div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">ROLE</div>
                                <div class="fw-semibold text-capitalize">{{ user.role || 'student' }}</div>
                            </div>
                        </div>

                        <div class="border-top pt-4">
                            <h5 class="fw-bold mb-2">Email Verification</h5>
                            <p class="text-muted small mb-3">
                                Verify your email to secure your account and receive updates.
                            </p>

                            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                                <button class="btn btn-emerald" :disabled="sending || isVerified" @click="sendCode">
                                    <span v-if="sending" class="spinner-border spinner-border-sm me-2"></span>
                                    {{ isVerified ? 'Verified' : 'Send Verification Code' }}
                                </button>
                                <span v-if="codeSent" class="text-success small fw-semibold">Code sent to your email.</span>
                            </div>

                            <form class="row g-2 align-items-center" @submit.prevent="verifyCode">
                                <div class="col-sm-6 col-md-4">
                                    <input v-model.trim="code" type="text" maxlength="6" class="form-control"
                                        placeholder="Enter 6-digit code" :disabled="verifying || isVerified">
                                </div>
                                <div class="col-sm-4 col-md-3">
                                    <button class="btn btn-outline-emerald w-100" type="submit"
                                        :disabled="verifying || isVerified || code.length !== 6">
                                        <span v-if="verifying" class="spinner-border spinner-border-sm me-2"></span>
                                        Verify
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import axios from 'axios';

const sending = ref(false);
const verifying = ref(false);
const codeSent = ref(false);
const code = ref('');
const emailSaving = ref(false);

const user = ref({
    first_name: '',
    middle_initial: '',
    last_name: '',
    extension_name: '',
    email: '',
    username: '',
    role: 'student',
    email_verified_at: null
});

const getUserStorage = () => {
    if (localStorage.getItem('auth_token')) {
        return localStorage;
    }
    return sessionStorage;
};

const loadUser = () => {
    const storage = getUserStorage();
    const data = storage.getItem('user_data');
    if (!data) return;
    try {
        user.value = { ...user.value, ...JSON.parse(data) };
    } catch (e) {
        console.warn('Failed to parse user data');
    }
};

loadUser();

const emailInput = ref(user.value.email || '');

const emailChanged = computed(() => {
    return (emailInput.value || '') !== (user.value.email || '');
});

const fullName = computed(() => {
    const parts = [
        user.value.first_name,
        user.value.middle_initial ? `${user.value.middle_initial}.` : '',
        user.value.last_name,
        user.value.extension_name
    ].filter(Boolean);
    return parts.join(' ');
});

const isVerified = computed(() => Boolean(user.value.email_verified_at));

const updateEmail = async () => {
    if (!emailChanged.value) return;
    emailSaving.value = true;
    try {
        const res = await axios.put('/api/profile/email', { email: emailInput.value });
        user.value = { ...user.value, ...res.data.user };
        emailInput.value = user.value.email || '';
        getUserStorage().setItem('user_data', JSON.stringify(user.value));
        code.value = '';
        codeSent.value = false;
        if (window.Toast) {
            window.Toast.fire({ icon: 'success', title: 'Email updated' });
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Failed to update email.';
        if (window.Swal) {
            window.Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#10b981' });
        }
    } finally {
        emailSaving.value = false;
    }
};

const sendCode = async () => {
    sending.value = true;
    try {
        await axios.post('/api/email-verification/send');
        codeSent.value = true;
        if (window.Toast) {
            window.Toast.fire({ icon: 'success', title: 'Verification code sent' });
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Failed to send verification code.';
        if (window.Swal) {
            window.Swal.fire({ icon: 'error', title: 'Error', text: msg, confirmButtonColor: '#10b981' });
        }
    } finally {
        sending.value = false;
    }
};

const verifyCode = async () => {
    if (code.value.length !== 6) return;
    verifying.value = true;
    try {
        await axios.post('/api/email-verification/verify', { code: code.value });
        const verifiedAt = new Date().toISOString();
        user.value.email_verified_at = verifiedAt;
        getUserStorage().setItem('user_data', JSON.stringify({ ...user.value, email_verified_at: verifiedAt }));
        if (window.Toast) {
            window.Toast.fire({ icon: 'success', title: 'Email verified' });
        }
    } catch (error) {
        const msg = error.response?.data?.message || 'Invalid or expired code.';
        if (window.Swal) {
            window.Swal.fire({ icon: 'error', title: 'Verification failed', text: msg, confirmButtonColor: '#10b981' });
        }
    } finally {
        verifying.value = false;
    }
};
</script>

<style scoped>
.btn-emerald {
    background-color: #10b981;
    color: white;
    border: none;
    transition: all 0.2s ease;
}

.btn-emerald:hover {
    background-color: #059669;
    color: white;
    transform: translateY(-1px);
}

.btn-outline-emerald {
    border: 1px solid #10b981;
    color: #10b981;
}

.btn-outline-emerald:hover {
    background-color: #10b981;
    color: white;
}
</style>
