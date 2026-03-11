import { createRouter, createWebHistory } from "vue-router";

const routes = [
    {
        path: "/login",
        name: "Login",
        component: () => import("../pages/Auth/Login.vue")
    },
    {
        path: "/register",
        component: () => import("../pages/Auth/Register.vue")
    },
    {
        path: "/forgot-password",
        component: () => import("../pages/Auth/ForgotPassword.vue")
    },
    {
        path: "/",
        component: () => import("../layouts/master.vue"),
        redirect: "/login",
        children: [
            // --- ADMIN ROUTES ---
            {
                path: "admin",
                component: () => import("../layouts/admin.vue"),
                redirect: "/admin/dashboard",
                meta: { requiresAuth: true, role: "admin" },
                children: [
                    { path: "dashboard", component: () => import("../pages/admin/Dashboard.vue") },
                    { path: "employees", component: () => import("../pages/admin/Employees.vue") },
                    { path: "offices", component: () => import("../pages/admin/Offices.vue") },
                    { path: "colleges", component: () => import("../pages/admin/colleges.vue") },
                    { path: "programs", component: () => import("../pages/admin/Programs.vue") },
                    { path: "subjects", component: () => import("../pages/admin/Subjects.vue") },
                    { path: "schedules", component: () => import("../pages/admin/Schedules.vue") },
                    { path: "students", component: () => import("../pages/admin/Students.vue") },
                    { path: "reports", component: () => import("../pages/admin/Reports.vue") },
                ],
            },

            // --- College Dean ROUTES ---
            {
                path: "college-dean",
                component: () => import("../layouts/college-dean.vue"),
                redirect: "/college-dean/dashboard",
                meta: { requiresAuth: true, role: "college_dean" },
                children: [
                    { path: "dashboard", name: "ph-dashboard", component: () => import("../pages/college-dean/Dashboard.vue") },
                    { path: "entrance/exams", component: () => import("../pages/college-dean/entrance/Exams.vue") },
                    { path: "entrance/keys", component: () => import("../pages/college-dean/entrance/AnswerKeys.vue") },
                    { path: "entrance/generate", component: () => import("../pages/college-dean/entrance/GenerateSheets.vue") },
                    { path: "entrance/reports", component: () => import("../pages/college-dean/entrance/Reports.vue") },
                    { path: "entrance/analysis", component: () => import("../pages/college-dean/entrance/Analysis.vue") },
                    { path: "normal/exams", component: () => import("../pages/college-dean/normal/Exams.vue") },
                    { path: "normal/keys", component: () => import("../pages/college-dean/normal/AnswerKeys.vue") },
                    { path: "normal/generate", component: () => import("../pages/college-dean/normal/GenerateSheets.vue") },
                    { path: "normal/reports", component: () => import("../pages/college-dean/normal/Reports.vue") },
                    { path: "normal/analysis", component: () => import("../pages/college-dean/normal/Analysis.vue") },
                    { path: "students", component: () => import("../pages/college-dean/Students.vue") },
                    { path: "subjects", component: () => import("../pages/college-dean/Subjects.vue") },
                    { path: "instructor-subjects", component: () => import("../pages/college-dean/InstructorSubjects.vue") },
                    { path: "reports", component: () => import("../pages/college-dean/Reports.vue") },
                ],
            },

            // --- ENTRANCE EXAMINER ROUTES ---
            {
                path: "entrance",
                component: () => import("../layouts/entrance.vue"),
                redirect: "/entrance/dashboard",
                meta: { requiresAuth: true, role: "entrance_examiner" },
                children: [
                    { path: "dashboard", component: () => import("../pages/entrance/Dashboard.vue") },
                    { path: "students", component: () => import("../pages/entrance/Students.vue") },
                    { path: "exams", component: () => import("../pages/entrance/Exams.vue") },
                    { path: "keys", component: () => import("../pages/entrance/AnswerKeys.vue") },
                    { path: "program-requirements", component: () => import("../pages/admin/ProgramRequirements.vue") },
                    { path: "generate", component: () => import("../pages/entrance/GenerateSheets.vue") },
                    { path: "reports", component: () => import("../pages/entrance/Reports.vue") },
                    { path: "analysis", component: () => import("../pages/entrance/Analysis.vue") },
                ],
            },

            // --- INSTRUCTOR ROUTES ---
            {
                path: "instructor",
                component: () => import("../layouts/instructor.vue"),
                redirect: "/instructor/dashboard",
                meta: { requiresAuth: true, role: "instructor" },
                children: [
                    { path: "dashboard", component: () => import("../pages/instructor/Dashboard.vue") },
                    { path: "exams", component: () => import("../pages/instructor/Exams.vue") },
                    { path: "keys", component: () => import("../pages/instructor/AnswerKeys.vue") },
                    { path: "generate", component: () => import("../pages/instructor/GenerateSheets.vue") },
                    { path: "sheets", redirect: "/instructor/generate" },
                    { path: "reports", component: () => import("../pages/instructor/Reports.vue") },
                    { path: "analysis", component: () => import("../pages/instructor/Analysis.vue") },
                    { path: "subjects", component: () => import("../pages/instructor/Subjects.vue") },
                    { path: "students", component: () => import("../pages/instructor/Students.vue") },
                ],
            },

            // --- STUDENT ROUTES ---
            {
                path: "student",
                component: () => import("../layouts/student.vue"),
                redirect: "/student/dashboard",
                meta: { requiresAuth: true, role: "student" },
                children: [
                    { path: "dashboard", component: () => import("../pages/student/Dashboard.vue") },
                    { path: "exams", component: () => import("../pages/student/Exams.vue") },
                    { path: "subjects", component: () => import("../pages/student/Subjects.vue") },
                    { path: "recommendations", component: () => import("../pages/student/Recommendations.vue") },
                    { path: "reports", component: () => import("../pages/student/Reports.vue") },
                    { path: "schedules", component: () => import("../pages/student/Schedules.vue") },
                    { path: "profile", component: () => import("../pages/student/Profile.vue") },
                ],
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// --- NAVIGATION GUARD ---
router.beforeEach((to, from, next) => {
    const token = localStorage.getItem('auth_token') || sessionStorage.getItem('auth_token');
    const userData = localStorage.getItem('user_data') || sessionStorage.getItem('user_data');

    // Safety check for user data
    let user = {};
    try {
        user = userData ? JSON.parse(userData) : {};
    } catch (e) {
        console.error("Failed to parse user data", e);
    }

    // 1. Handle Authentication Requirement
    const requiresAuth = to.matched.some(record => record.meta.requiresAuth);

    if (requiresAuth) {
        if (!token) {
            // User is not logged in, redirect to login
            return next('/login');
        }

        // 2. Role-Based Access Control (RBAC)
        if (to.meta.role && user.role !== to.meta.role) {
            if (window.Toast) {
                window.Toast.fire({
                    icon: 'error',
                    title: 'Unauthorized Access'
                });
            }
            // User role doesn't match the route, redirect to their home
            return next('/login');
        }
    }

    // 3. Prevent logged-in users from accessing Auth pages (Login/Register)
    if (token && (to.path === '/login' || to.path === '/register')) {
        const roleRoutes = {
            admin: '/admin/dashboard',
            college_dean: '/college-dean/dashboard',
            instructor: '/instructor/dashboard',
            entrance_examiner: '/entrance/dashboard',
            student: '/student/dashboard'
        };
        return next(roleRoutes[user.role] || '/');
    }

    // Default: allow navigation
    next();
});

export default router;
