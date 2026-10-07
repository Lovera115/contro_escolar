const menusByRole = {
    admin: [
        { icon: 'fa-solid fa-users-gear', text: 'Gestión de Roles', module: 'modules/admin-roles.html', title: 'Administración de Roles y Usuarios' },
        { icon: 'fa-solid fa-link', text: 'Asignación Académica', module: 'modules/asignacion-academica.html', title: 'Asignación Académica' },
        { icon: 'fa-solid fa-folder-open', text: 'Revisión de Expedientes', module: 'modules/revision-expedientes.html', title: 'Revisión de Expedientes' },
        { icon: 'fa-solid fa-money-bill-trend-up', text: 'Finanzas y Cobranza', module: 'modules/finanzas.html', title: 'Control Financiero y Pagos', section: 'payments' },
        { icon: 'fa-solid fa-chart-line', text: 'Auditoría Financiera', module: 'modules/finanzas.html', title: 'Historial Financiero y Auditoría Generacional', section: 'audit' }
    ],
    finanzas: [
        { icon: 'fa-solid fa-money-bill-trend-up', text: 'Finanzas y Cobranza', module: 'modules/finanzas.html', title: 'Control Financiero y Pagos', section: 'payments' },
        { icon: 'fa-solid fa-chart-line', text: 'Auditoría Financiera', module: 'modules/finanzas.html', title: 'Historial Financiero y Auditoría Generacional', section: 'audit' }
    ],
    control_escolar: [
        { icon: 'fa-solid fa-users-gear', text: 'Gestión de Usuarios', module: 'modules/admin-roles.html', title: 'Gestión de Usuarios' },
        { icon: 'fa-solid fa-link', text: 'Asignación Académica', module: 'modules/asignacion-academica.html', title: 'Asignación Académica' },
        { icon: 'fa-solid fa-folder-open', text: 'Revisión de Expedientes', module: 'modules/revision-expedientes.html', title: 'Revisión de Expedientes' }
    ],
    docente: [
        { icon: 'fa-regular fa-calendar-days', text: 'Mis Grupos y Horario', module: 'modules/portal-docente.html', title: 'Mis Grupos y Horario', section: 'groups' },
        { icon: 'fa-solid fa-clipboard-check', text: 'Pase de Asistencia', module: 'modules/portal-docente.html', title: 'Pase de Asistencia', section: 'attendance' },
        { icon: 'fa-solid fa-file-signature', text: 'Cierre de Actas & Parciales', module: 'modules/portal-docente.html', title: 'Cierre de Actas & Parciales', section: 'grades' }
    ],
    alumno: [
        { icon: 'fa-solid fa-house-user', text: 'Mi Portal', module: 'modules/portal-alumno.html', title: 'Mi Portal', section: 'dashboard' },
        { icon: 'fa-solid fa-address-card', text: 'Mis Datos', module: 'modules/portal-alumno.html', title: 'Mis Datos', section: 'profile' },
        { icon: 'fa-solid fa-folder-open', text: 'Expediente Digital', module: 'modules/portal-alumno.html', title: 'Expediente Digital', section: 'documents' },
        { icon: 'fa-solid fa-graduation-cap', text: 'Trayectoria Académica', module: 'modules/portal-alumno.html', title: 'Trayectoria Académica', section: 'grades' }
    ]
};

let currentRole = 'admin';
let requestedStudentPortalSection = 'dashboard';
let requestedTeacherPortalSection = 'groups';

async function loadModule(modulePath, pageTitle, section = '') {
    const mainContainer = document.getElementById('main-content');
    const academicModule = modulePath === 'modules/asignacion-academica.html';
    const studentModule = modulePath === 'modules/portal-alumno.html';
    const teacherModule = modulePath === 'modules/portal-docente.html';
    const financeModule = modulePath === 'modules/finanzas.html';
    const documentsModule = modulePath === 'modules/revision-expedientes.html';
    const teacherViewAllowed = teacherModule && currentRole === 'docente';
    document.body.classList.toggle('finance-layout-active', financeModule);
    document.body.classList.toggle('teacher-layout-active', teacherViewAllowed);
    const sidebar = document.getElementById('sidebar');
    sidebar?.classList.remove('finance-sidebar-open', 'teacher-sidebar-open', 'mobile-sidebar-open');
    const sidebarToggle = document.getElementById('toggle-sidebar');
    if (sidebarToggle) {
        sidebarToggle.onclick = () => {
            const isOpen = sidebar?.classList.toggle('mobile-sidebar-open') || false;
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        };
        sidebarToggle.setAttribute('aria-expanded', 'false');
    }
    if (!teacherViewAllowed) {
        sidebar?.classList.remove('teacher-sidebar-open');
    }
    if (academicModule && !['admin', 'control_escolar'].includes(currentRole)) {
        mainContainer.innerHTML = '<div class="access-denied">No tienes permisos para acceder a esta vista.</div>';
        document.getElementById('page-title').textContent = 'Acceso restringido';
        return;
    }
    if (studentModule && currentRole !== 'alumno') {
        mainContainer.innerHTML = '<div class="access-denied">El portal está disponible únicamente para el alumno autenticado.</div>';
        document.getElementById('page-title').textContent = 'Acceso restringido';
        return;
    }
    if (teacherModule && currentRole !== 'docente') {
        mainContainer.innerHTML = '<div class="access-denied">El portal docente está disponible únicamente para el docente autenticado.</div>';
        document.getElementById('page-title').textContent = 'Acceso restringido';
        return;
    }
    if (documentsModule && !['admin', 'control_escolar'].includes(currentRole)) {
        mainContainer.innerHTML = '<div class="access-denied">No tienes permisos para revisar expedientes.</div>';
        document.getElementById('page-title').textContent = 'Acceso restringido';
        return;
    }

    document.getElementById('page-title').textContent = pageTitle;

    try {
        const separator = modulePath.includes('?') ? '&' : '?';
        const response = await fetch(`${modulePath}${separator}v=20261007-modal-refresh-2`, { cache: 'no-store' });
        if (!response.ok) throw new Error('Error al cargar la vista');
        
        const html = await response.text();
        mainContainer.innerHTML = html;

        // Inicializar lógica según la interfaz cargada
        if (modulePath.includes('admin-roles')) {
            if (typeof initializeAdminRolesModule === 'function') initializeAdminRolesModule();
        } else if (modulePath.includes('finanzas')) {
            if (typeof updateKPIs === 'function' && typeof renderStudentsTable === 'function') {
                updateKPIs();
                filterStudentsTable();
                renderStudentsTable();
                renderFinancialAudit();
                if (typeof initializeFinanceAudit === 'function') initializeFinanceAudit(section);
            }
        } else if (academicModule && typeof initializeAcademicAssignments === 'function') {
            initializeAcademicAssignments();
        } else if (studentModule && typeof initializeStudentPortal === 'function') {
            initializeStudentPortal();
        } else if (documentsModule && typeof initializeDocumentReview === 'function') {
            initializeDocumentReview();
        } else if (teacherModule && typeof initializeTeacherPortal === 'function') {
            initializeTeacherPortal();
            const section = ['groups', 'attendance', 'grades'].includes(requestedTeacherPortalSection)
                ? requestedTeacherPortalSection : 'groups';
            const selectedTab = document.querySelector(`.teacher-tab[aria-controls="teacher-panel-${section}"]`);
            switchTeacherTab(section, selectedTab || document.querySelector('.teacher-tab'));
        }
    } catch (error) {
        console.error(`Error al cargar el módulo ${modulePath}:`, error);
        mainContainer.innerHTML = `<div style="padding:20px; color:red;">Error al cargar el módulo (${modulePath}).</div>`;
    }
}

function renderMenu(role) {
    const menuContainer = document.getElementById('dynamic-menu');
    menuContainer.innerHTML = '';

    currentRole = Object.prototype.hasOwnProperty.call(menusByRole, role) ? role : 'finanzas';
    const items = menusByRole[currentRole];

    items.forEach((item, index) => {
        const li = document.createElement('li');
        const activeClass = index === 0 ? 'active' : '';
        
        li.innerHTML = `
            <a href="#" class="${activeClass}" onclick="event.preventDefault(); setActiveMenu(this, '${item.module}', '${item.title}', '${item.section || ''}')">
                <i class="${item.icon}"></i>
                <span>${item.text}</span>
            </a>
        `;
        menuContainer.appendChild(li);
    });

    loadModule(items[0].module, items[0].title, items[0].section || '');
}

function setActiveMenu(element, modulePath, title, section = '') {
    document.querySelectorAll('.sidebar-nav a').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    document.getElementById('sidebar')?.classList.remove('teacher-sidebar-open', 'finance-sidebar-open', 'mobile-sidebar-open');
    document.getElementById('toggle-sidebar')?.setAttribute('aria-expanded', 'false');
    if (modulePath === 'modules/portal-alumno.html') {
        requestedStudentPortalSection = section || 'dashboard';
    } else if (modulePath === 'modules/portal-docente.html') {
        requestedTeacherPortalSection = section || 'groups';
    }
    loadModule(modulePath, title, section);
}

function changeRole(role) {
    const profileName = document.getElementById('user-name');
    const profileRole = document.getElementById('user-role');
    if (role === 'alumno') {
        if (profileName) profileName.textContent = 'Ana García López';
        if (profileRole) profileRole.textContent = 'Alumno';
    } else if (role === 'docente') {
        if (profileName) profileName.textContent = 'Dr. Roberto Sánchez';
        if (profileRole) profileRole.textContent = 'Docente';
    } else if (profileName && profileRole) {
        profileName.textContent = role === 'control_escolar' ? 'Lic. Carmen Flores' :
            role === 'finanzas' ? 'Roberto Sánchez' : 'Administrador';
        profileRole.textContent = role === 'control_escolar' ? 'Control Escolar' :
            role === 'finanzas' ? 'Finanzas' : 'SuperUsuario';
    }
    renderMenu(role);
}

document.addEventListener('DOMContentLoaded', () => {
    renderMenu('admin');
});