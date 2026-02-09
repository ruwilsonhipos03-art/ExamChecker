import { createRouter, createWebHistory } from "vue-router";

// Layouts
import MasterLayout from "../layouts/master.vue";
// import { p } from "vue-router/dist/router-CWoNjPRp.mjs";
const AdminLayout = () => import("../layouts/admin.vue");
const DHLayout = () => import("../layouts/depthead.vue");
const InstructorLayout = () => import("../layouts/instructor.vue");
const EntranceLayout = () => import("../layouts/entrance.vue");
const StudentLayout = () => import("../layouts/student.vue");
import ForgotPassword from "../pages/Auth/ForgotPassword.vue";
import Login from "../pages/Auth/Login.vue";
import Register from "../pages/Auth/Register.vue";

const routes = [
    { path: "/login", component: Login },
    { path: "/register", component: Register },
    { path: "/forgot-password", component: ForgotPassword },
    {
    path: "/",
    component: MasterLayout,
    redirect: "/login",
    children: [
      // --- ADMIN ROUTES ---
      {
        path: "admin",
            component: AdminLayout,
            redirect: "/admin/dashboard",
            meta: { requiresAuth: true, role: "admin" },
        children: [
            { path: "dashboard", component: () => import("../pages/admin/Dashboard.vue") },
            { path: "employees", component: () => import("../pages/admin/Employees.vue") },
            { path: "offices", component: () => import("../pages/admin/Offices.vue") },
            { path: "departments", component: () => import("../pages/admin/Departments.vue") },
            { path: "programs", component: () => import("../pages/admin/Programs.vue") },
            { path: "subjects", component: () => import("../pages/admin/Subjects.vue") },
            { path: "schedules", component: () => import("../pages/admin/Schedules.vue") },
            { path: "reports", component: () => import("../pages/admin/Reports.vue") },
        ],
      },

      // --- DEPARTMENT HEAD ROUTES ---
      {
        path: "department-head",
        component: DHLayout,
        redirect: "/department-head/dashboard",
        meta: { requiresAuth: true, role: "dept_head" },
        children: [
            { path: "dashboard", name: "ph-dashboard", component: () => import("../pages/depthead/Dashboard.vue") },
            // Entrance Exams
            { path: "entrance/exams", component: () => import("../pages/depthead/entrance/Exams.vue") },
            { path: "entrance/keys", component: () => import("../pages/depthead/entrance/AnswerKeys.vue") },
            { path: "entrance/generate", component: () => import("../pages/depthead/entrance/GenerateSheets.vue") },
            { path: "entrance/reports", component: () => import("../pages/depthead/entrance/Reports.vue") },
            // Normal Exams
            { path: "normal/exams", component: () => import("../pages/depthead/normal/Exams.vue") },
            { path: "normal/keys", component: () => import("../pages/depthead/normal/AnswerKeys.vue") },
            { path: "normal/generate", component: () => import("../pages/depthead/normal/GenerateSheets.vue") },
            { path: "normal/reports", component: () => import("../pages/depthead/normal/Reports.vue") },
            // Management
            { path: "students", component: () => import("../pages/depthead/Students.vue") },
            { path: "subjects", component: () => import("../pages/depthead/Subjects.vue") },
        ],
      },

      // --- ENTRANCE EXAMINER ROUTES ---
      {
        path: "entrance",
        component: EntranceLayout,
          redirect: "/entrance/dashboard",
        meta: { requiresAuth: true, role: "entrance_examiner" },
        children: [
            { path: "dashboard", component: () => import("../pages/entrance/Dashboard.vue") },
            { path: "students", component: () => import("../pages/entrance/Students.vue") },
            { path: "exams", component: () => import("../pages/entrance/Exams.vue") },
            { path: "keys", component: () => import("../pages/entrance/AnswerKeys.vue") },
            { path: "generate", component: () => import("../pages/entrance/GenerateSheets.vue") },
            { path: "reports", component: () => import("../pages/entrance/Reports.vue") },
        ],
      },

      // --- INSTRUCTOR ROUTES ---
      {
        path: "instructor",
        component: InstructorLayout,
        redirect: "/instructor/dashboard",
        meta: { requiresAuth: true, role: "instructor" },
        children: [
            { path: "dashboard", component: () => import("../pages/instructor/Dashboard.vue") },
            { path: "exams", component: () => import("../pages/instructor/Exams.vue") },
            { path: "keys", component: () => import("../pages/instructor/AnswerKeys.vue") },
            { path: "sheets", component: () => import("../pages/instructor/GenerateSheets.vue") },
            { path: "reports", component: () => import("../pages/instructor/Reports.vue") },
            { path: "subjects", component: () => import("../pages/instructor/Subjects.vue") },
            { path: "students", component: () => import("../pages/instructor/Students.vue") },
        ],
      },

      // --- STUDENT ROUTES ---
      {
        path: "student",
        component: StudentLayout,
          redirect: "/student/dashboard",
        meta: { requiresAuth: true, role: "student" },
        children: [
            { path: "dashboard", component: () => import("../pages/student/Dashboard.vue")   },
            { path: "exams", component: () => import("../pages/student/Exams.vue") },
            { path: "recommendations", component: () => import("../pages/student/Recommendations.vue") },
            { path: "reports", component: () => import("../pages/student/Reports.vue") },
            { path: "schedules", component: () => import("../pages/student/Schedules.vue") },
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
  const token = localStorage.getItem('auth_token');
  const user = JSON.parse(localStorage.getItem('user_data') || '{}');

  // 1. Check if the route requires authentication
  if (to.matched.some(record => record.meta.requiresAuth)) {

    // If no token exists, send them to login
    if (!token) {
      return next('/login');
    }

    // 2. Role-Based Access Control (RBAC)
    // Check if the route has a specific role requirement
    if (to.meta.role && user.role !== to.meta.role) {
      // If user role doesn't match, send them back to their proper dashboard or login
      window.Toast.fire({
        icon: 'error',
        title: 'Unauthorized Access'
      });
      return next('/login');
    }
  }

  // 3. Prevent logged-in users from accessing Login/Register pages
  if (token && (to.path === '/login' || to.path === '/register')) {
    // Redirect them to their specific dashboard based on role
    const roleRoutes = {
      admin: '/admin/dashboard',
      department_head: '/department-head/dashboard',
      instructor: '/instructor/dashboard',
      entrance_examiner: '/entrance/dashboard',
      student: '/student/dashboard'
    };
    return next(roleRoutes[user.role] || '/');
  }

  next(); // Proceed to the route
});

export default router;
