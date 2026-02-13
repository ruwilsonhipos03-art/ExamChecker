<template>
    <div class="auth-wrapper d-flex align-items-center justify-content-center bg-light p-3">
        <div class="card shadow-sm border-0 auth-card-wide">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h4 class="fw-bold text-emerald">Student Registration</h4>
                    <p class="text-muted small">Fill out the form to secure your entrance exam slot.</p>
                </div>

                <form @submit.prevent="handleRegister">
                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">FIRST NAME</label>
                            <input v-model="form.first_name" type="text" class="form-control" required
                                :disabled="loading">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-secondary text-nowrap">M.I.</label>
                            <input v-model="form.middle_name" type="text" class="form-control" maxlength="2"
                                :disabled="loading">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">LAST NAME</label>
                            <input v-model="form.last_name" type="text" class="form-control" required
                                :disabled="loading">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-bold text-secondary">SUFFIX</label>
                            <input v-model="form.extension_name" type="text" class="form-control" placeholder="Jr."
                                :disabled="loading">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">EMAIL ADDRESS</label>
                        <input v-model="form.email" type="email" class="form-control" required :disabled="loading">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">USERNAME</label>
                        <input v-model="form.username" type="text" class="form-control" required :disabled="loading">
                    </div>

                    <div class="row g-2 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">PASSWORD</label>
                            <input v-model="form.password" type="password" class="form-control" required
                                :disabled="loading">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">CONFIRM PASSWORD</label>
                            <input v-model="form.password_confirmation" type="password" class="form-control" required
                                :disabled="loading">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-emerald w-100 py-2 fw-bold shadow-sm" :disabled="loading">
                        <span v-if="loading" class="spinner-border spinner-border-sm me-2"></span>
                        {{ loading ? 'CREATING ACCOUNT...' : 'CREATE ACCOUNT' }}
                    </button>
                </form>

                <div class="text-center mt-3">
                    <p class="small text-muted">Already registered?
                        <router-link to="/login" class="text-emerald fw-bold text-decoration-none">Back to
                            Login</router-link>
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import Swal from 'sweetalert2';

const router = useRouter();
const loading = ref(false);

const form = reactive({
    first_name: '',
    middle_name: '',
    last_name: '',
    extension_name: '',
    email: '',
    username: '',
    password: '',
    password_confirmation: ''
});

const handleRegister = async () => {
    // Basic match check before sending to server
    if (form.password !== form.password_confirmation) {
        return Swal.fire({
            icon: 'error',
            title: 'Mismatch',
            text: 'Passwords do not match!',
            confirmButtonColor: '#10b981'
        });
    }

    loading.value = true;
    try {
        const res = await axios.post('/api/register', form);

        // 1. Store the session (Token and User)
        localStorage.setItem('auth_token', res.data.token);
        localStorage.setItem('user_data', JSON.stringify(res.data.user));
        axios.defaults.headers.common['Authorization'] = `Bearer ${res.data.token}`;

        // 2. Send verification code and prompt for it
        await axios.post('/api/email-verification/send');
        const { value: code } = await Swal.fire({
            title: 'Verify Your Email',
            input: 'text',
            inputLabel: 'Enter the 6-digit code sent to your email',
            inputPlaceholder: 'e.g. 123456',
            inputAttributes: { maxlength: 6, autocapitalize: 'off', autocorrect: 'off' },
            confirmButtonText: 'Verify',
            confirmButtonColor: '#10b981',
            showCancelButton: false
        });
        if (code) {
            await axios.post('/api/email-verification/verify', { code });
        }

        // 3. Show the Exam Schedule Reveal
        const sched = res.data.schedule;
        await Swal.fire({
            title: '<strong>Account Created!</strong>',
            icon: 'success',
            html: `
                <div class="text-start mt-3 p-3 border rounded bg-light">
                    <p class="mb-2">Welcome, <b>${res.data.user.first_name}</b>!</p>
                    <hr>
                    <p class="mb-1 text-success fw-bold"><i class="bi bi-calendar-check me-2"></i>YOUR EXAM SCHEDULE:</p>
                    <div class="ms-3">
                        <div><b>Date:</b> ${sched.date}</div>
                        <div><b>Time:</b> ${sched.time}</div>
                        <div><b>Venue:</b> ${sched.location}</div>
                    </div>
                    <p class="small text-muted mt-3 mb-0">* Please save this information.</p>
                </div>
            `,
            confirmButtonText: 'Proceed to Dashboard',
            confirmButtonColor: '#10b981',
        });

        // 4. Automated Login (Redirect)
        router.push('/student/dashboard');

    } catch (error) {
        const msg = error.response?.data?.message || 'Registration failed. Slots might be full.';
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: msg,
            confirmButtonColor: '#10b981'
        });
    } finally {
        loading.value = false;
    }
};
</script>

<style scoped>
.auth-wrapper {
    min-height: 100vh;
    background-color: #f8f9fa;
}

.auth-card-wide {
    width: 100%;
    max-width: 750px;
    border-radius: 1rem;
}

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

.text-emerald {
    color: #10b981;
}

.form-control:focus {
    border-color: #10b981;
    box-shadow: 0 0 0 0.25rem rgba(16, 185, 129, 0.25);
}

.text-nowrap {
    white-space: nowrap;
}
</style>
