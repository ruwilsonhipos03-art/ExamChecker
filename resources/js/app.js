import { createApp } from 'vue';
import App from './App.vue';
import router from './router';
import axios from 'axios';

// Bootstrap & Icons
import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';

// SweetAlert2 Setup
import Swal from 'sweetalert2';

// 1. Create a Global Toast instance
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.addEventListener('mouseenter', Swal.stopTimer)
        toast.addEventListener('mouseleave', Swal.resumeTimer)
    }
});

// 2. Attach to window object
window.Swal = Swal;
window.Toast = Toast;

// --- 3. Axios Global Configuration ---
axios.defaults.baseURL = 'http://localhost:8000'; // Ensure this matches your Laravel URL
axios.defaults.headers.common['Accept'] = 'application/json';
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// 4. Request Interceptor: Attach token to every outgoing request
axios.interceptors.request.use(
    (config) => {
        const token = localStorage.getItem('auth_token');
        if (token) {
            config.headers.Authorization = `Bearer ${token}`;
        }
        return config;
    },
    (error) => {
        return Promise.reject(error);
    }
);

// 5. Response Interceptor: Handle session expiration (401)
axios.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response && error.response.status === 401) {
            // Token is invalid or expired
            localStorage.removeItem('auth_token');
            localStorage.removeItem('user_data');
            
            // Only redirect if we aren't already on the login page
            if (window.location.pathname !== '/login') {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

const app = createApp(App);

// Global property for axios (optional, allows this.$http in Options API)
app.config.globalProperties.$http = axios;

app.use(router);
app.mount('#app');