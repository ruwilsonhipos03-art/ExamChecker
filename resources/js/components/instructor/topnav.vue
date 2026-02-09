<template>
    <nav class="top-nav border-bottom bg-white d-flex align-items-center justify-content-between px-4">
        <div class="d-flex align-items-center">
            <button @click="$emit('toggle-sidebar')" class="btn btn-link text-dark p-0 me-3">
                <i class="bi bi-list fs-3"></i>
            </button>

            <div class="d-flex align-items-center gap-2">
                <div class="logo-box">
                    <i class="bi bi-qr-code-scan"></i>
                </div>
                <span class="fw-bold fs-5 mb-0 text-dark">Welcome, {{ user.first_name }}!</span>
            </div>
        </div>

        <div class="profile-section d-flex align-items-center gap-2 dropdown">
            <button class="btn d-flex align-items-center gap-2 border-0 shadow-none" data-bs-toggle="dropdown">
                <div class="avatar">{{ userInitials }}</div>

                <span class="fw-semibold d-none d-md-inline text-dark">
                    {{ user.first_name }} {{ user.last_name }}
                </span>
                <i class="bi bi-chevron-down small text-muted"></i>
            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                <li class="px-3 py-2 border-bottom mb-1">
                    <div class="text-muted" style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase;">
                        Account Role</div>
                    <div class="badge bg-light text-success border border-success-subtle fw-bold">
                        {{ formatRole(user.role) }}
                    </div>
                </li>
                <li><a class="dropdown-item py-2" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                <li><a class="dropdown-item py-2" href="#"><i class="bi bi-gear me-2"></i> Settings</a></li>
                <li>
                    <hr class="dropdown-divider">
                </li>
                <li>
                    <a class="dropdown-item py-2 text-danger" href="#" @click.prevent="handleLogout">
                        <i class="bi bi-box-arrow-right me-2"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </nav>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const user = ref({
    first_name: '',
    last_name: '',
    role: ''
});

onMounted(() => {
    const savedData = localStorage.getItem('user_data');
    if (savedData) {
        user.value = JSON.parse(savedData);
    }
});

// Generate initials from first and last name
const userInitials = computed(() => {
    const f = user.value.first_name ? user.value.first_name.charAt(0) : '';
    const l = user.value.last_name ? user.value.last_name.charAt(0) : '';
    return (f + l).toUpperCase() || '??';
});

// Format role for display (e.g., 'instructor' -> 'Instructor')
const formatRole = (role) => {
    if (!role) return 'User';
    return role.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};

const handleLogout = async () => {
    const result = await window.Swal.fire({
        title: 'Logout?',
        text: "Are you sure you want to sign out?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10b981',
        confirmButtonText: 'Yes, logout'
    });

    if (result.isConfirmed) {
        try {
            await axios.post('/api/logout');
        } catch (error) {
            console.warn("API logout failed, clearing local session.");
        } finally {
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_data');
            delete axios.defaults.headers.common['Authorization'];
            router.push('/login');
            window.Toast.fire({ icon: 'success', title: 'Logged out successfully' });
        }
    }
};
</script>

<style scoped>
.top-nav {
    height: 70px;
    z-index: 1030;
    width: 100%;
}

.logo-box {
    width: 38px;
    height: 38px;
    background: #10b981;
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 1.2rem;
}

.avatar {
    width: 35px;
    height: 35px;
    background: #10b981;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    font-weight: 600;
}

.btn:focus {
    box-shadow: none;
}
</style>
