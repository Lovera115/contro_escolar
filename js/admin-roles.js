const roleLabels = {
    admin: 'Administrador',
    control_escolar: 'Control Escolar',
    finanzas: 'Finanzas',
    docente: 'Docente',
    alumno: 'Alumno'
};

const moduleDefinitions = [
    { id: 'control_escolar', name: 'Control Escolar', details: 'Inscripción, actas, asignación académica y expedientes' },
    { id: 'finanzas', name: 'Finanzas', details: 'Cobros, historial generacional, auditoría y bajas financieras' },
    { id: 'docente', name: 'Docente', details: 'Pase de asistencia, calificaciones y cierre de actas' },
    { id: 'alumno', name: 'Alumno', details: 'Horarios, calificaciones y carga de requisitos' },
    { id: 'administrador', name: 'Administrador', details: 'RBAC, reportes globales y configuración del sistema' }
];
const roleIds = ['admin', 'control_escolar', 'finanzas', 'docente', 'alumno'];
const permissionLevels = ['none', 'read', 'write', 'full'];
const permissionLabels = { none: 'Sin acceso', read: 'Lectura', write: 'Escritura', full: 'Control total' };
const defaultPermissions = {
    control_escolar: { admin: 'full', control_escolar: 'full', finanzas: 'read', docente: 'none', alumno: 'none' },
    finanzas: { admin: 'full', control_escolar: 'read', finanzas: 'full', docente: 'none', alumno: 'none' },
    docente: { admin: 'full', control_escolar: 'read', finanzas: 'none', docente: 'write', alumno: 'none' },
    alumno: { admin: 'full', control_escolar: 'none', finanzas: 'none', docente: 'none', alumno: 'read' },
    administrador: { admin: 'full', control_escolar: 'none', finanzas: 'none', docente: 'none', alumno: 'none' }
};

let usersDB = [
    { id: 1, name: 'Dr. Roberto Sánchez', type: 'docente', role: 'docente', username: 'roberto.sanchez', registration: 'SANR-7802', email: 'r.sanchez@univ.edu.mx', phone: '722 100 2001', career: 'Área Docente', period: '2025-3', paymentStatus: 'al_corriente', balance: 0, status: 'activo', lastAccess: '07 Oct 2026 · 10:15 AM' },
    { id: 2, name: 'Lic. María Jiménez', type: 'docente', role: 'docente', username: 'maria.jimenez', registration: 'JIMM-5513', email: 'm.jimenez@univ.edu.mx', phone: '722 100 2002', career: 'Área Docente', period: '2025-3', paymentStatus: 'al_corriente', balance: 0, status: 'activo', lastAccess: '06 Oct 2026 · 04:42 PM' },
    { id: 3, name: 'Ana García López', type: 'alumno', role: 'alumno', username: 'ana.garcia', registration: '2024-0312', email: 'ana.garcia@alumnos.edu.mx', phone: '722 123 4567', career: 'Especialidad en Derecho Administrativo (EDA)', period: '2026-1', paymentStatus: 'adeudo', balance: 2500, status: 'activo', lastAccess: '05 Oct 2026 · 08:30 AM' },
    { id: 4, name: 'Luis Pérez Mora', type: 'alumno', role: 'alumno', username: 'luis.perez', registration: '2023-1145', email: 'luis.perez@alumnos.edu.mx', phone: '722 123 4568', career: 'Especialidad en Justicia Administrativa (EJA)', period: '2025-3', paymentStatus: 'al_corriente', balance: 0, status: 'activo', lastAccess: '04 Oct 2026 · 12:10 PM' },
    { id: 5, name: 'Claudia Ríos Vega', type: 'alumno', role: 'alumno', username: 'claudia.rios', registration: '2024-0518', email: 'claudia.rios@alumnos.edu.mx', phone: '722 123 4569', career: 'Especialidad en Responsabilidades Administrativas (ERA)', period: '2026-1', paymentStatus: 'beca', balance: 0, status: 'inactivo', lastAccess: '—' },
    { id: 6, name: 'Administrador CEPTRI', type: 'admin', role: 'admin', username: 'admin.cepc', registration: 'ADMIN-001', email: 'admin@ceptri.edu.mx', phone: '', career: '', period: '2026-1', paymentStatus: 'al_corriente', balance: 0, status: 'activo', lastAccess: '07 Oct 2026 · 09:02 AM' },
    { id: 7, name: 'Control Escolar', type: 'control_escolar', role: 'control_escolar', username: 'control.escolar', registration: 'CE-001', email: 'control@ceptri.edu.mx', phone: '', career: '', period: '2026-1', paymentStatus: 'al_corriente', balance: 0, status: 'activo', lastAccess: '07 Oct 2026 · 08:54 AM' },
    { id: 8, name: 'Finanzas CEPTRI', type: 'finanzas', role: 'finanzas', username: 'finanzas', registration: 'FIN-001', email: 'finanzas@ceptri.edu.mx', phone: '', career: '', period: '2026-1', paymentStatus: 'al_corriente', balance: 0, status: 'activo', lastAccess: '06 Oct 2026 · 03:20 PM' }
];

const usersPageSize = 7;
let currentUserPage = 1;
let userAdminTab = 'directory';
let matrixPermissions = JSON.parse(JSON.stringify(defaultPermissions));

function escapeUserHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

function normalizeUserRole(user) {
    if (user.role && roleLabels[user.role]) return user.role;
    if (roleLabels[user.type]) return user.type;
    return user.type === 'docente' ? 'docente' : 'alumno';
}

function getRoleModuleAccess(role) {
    return moduleDefinitions
        .filter(module => (matrixPermissions[module.id]?.[role] || 'none') !== 'none')
        .map(module => module.id);
}

function getUserModules(user) {
    const role = normalizeUserRole(user);
    const moduleIds = new Set(getRoleModuleAccess(role));
    if (user.accessFinance) moduleIds.add('finanzas');
    if (user.accessSchool) moduleIds.add('control_escolar');
    return [...moduleIds].map(id => moduleDefinitions.find(module => module.id === id)?.name).filter(Boolean);
}

function getFilteredUsers() {
    const search = document.getElementById('user-search')?.value.trim().toLocaleLowerCase('es-MX') || '';
    const role = document.getElementById('user-filter-role')?.value || 'todos';
    const status = document.getElementById('user-filter-status')?.value || 'todos';
    const period = document.getElementById('user-filter-period')?.value || 'todos';
    return usersDB.filter(user => {
        const matchesSearch = `${user.name} ${user.email} ${user.username || ''} ${user.registration || ''}`
            .toLocaleLowerCase('es-MX').includes(search);
        return matchesSearch &&
            (role === 'todos' || normalizeUserRole(user) === role) &&
            (status === 'todos' || user.status === status) &&
            (period === 'todos' || (user.period || '2026-1') === period);
    });
}

function updateUserMetrics() {
    const staffRoles = new Set(['admin', 'control_escolar', 'finanzas']);
    const financeAccess = usersDB.filter(user => user.status === 'activo' && getUserModules(user).includes('Finanzas')).length;
    const schoolAccess = usersDB.filter(user => user.status === 'activo' && getUserModules(user).includes('Control Escolar')).length;
    const metrics = {
        'metric-total-accounts': usersDB.length,
        'metric-admin-staff': usersDB.filter(user => staffRoles.has(normalizeUserRole(user))).length,
        'metric-finance-access': financeAccess,
        'metric-school-access': schoolAccess
    };
    Object.entries(metrics).forEach(([id, value]) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value;
    });
}

function renderUserTable() {
    const tbody = document.getElementById('user-table-body');
    if (!tbody) return;
    const filteredUsers = getFilteredUsers();
    const pageCount = Math.max(1, Math.ceil(filteredUsers.length / usersPageSize));
    currentUserPage = Math.min(currentUserPage, pageCount);
    const startIndex = (currentUserPage - 1) * usersPageSize;
    const pageUsers = filteredUsers.slice(startIndex, startIndex + usersPageSize);
    tbody.innerHTML = '';

    if (pageUsers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="users-empty-state">No se encontraron usuarios con esos criterios.</td></tr>';
    } else {
        pageUsers.forEach(user => {
            const tr = document.createElement('tr');
            const role = normalizeUserRole(user);
            const initials = user.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toLocaleUpperCase('es-MX');
            const modules = getUserModules(user);
            const moduleChips = modules.length
                ? modules.map(name => `<span class="rbac-module-chip">${escapeUserHtml(name)}</span>`).join('')
                : '<span class="rbac-module-empty">Sin módulos</span>';
            tr.innerHTML = `
                <td><div class="rbac-user-cell"><span class="rbac-avatar">${escapeUserHtml(initials)}</span><span><strong>${escapeUserHtml(user.name)}</strong><small>${escapeUserHtml(user.email)}</small><small>@${escapeUserHtml(user.username || user.registration || 'usuario')}</small></span></div></td>
                <td><select class="rbac-role-select role-${role}" aria-label="Cambiar rol de ${escapeUserHtml(user.name)}" onchange="changeUserRole(${Number(user.id)}, this.value)">
                    ${roleIds.map(id => `<option value="${id}" ${role === id ? 'selected' : ''}>${roleLabels[id]}</option>`).join('')}
                </select></td>
                <td><div class="rbac-module-list">${moduleChips}</div></td>
                <td class="rbac-last-access">${escapeUserHtml(user.lastAccess || 'Sin registro')}</td>
                <td><label class="rbac-switch" aria-label="${user.status === 'activo' ? 'Desactivar' : 'Activar'} ${escapeUserHtml(user.name)}"><input type="checkbox" ${user.status === 'activo' ? 'checked' : ''} onchange="toggleUserStatus(${Number(user.id)}, this.checked)"><span></span></label><small class="rbac-status-text ${user.status === 'activo' ? 'status-active' : 'status-inactive'}">${user.status === 'activo' ? 'Activo' : 'Inactivo'}</small></td>
                <td><div class="rbac-actions">
                    <button type="button" class="rbac-icon-button" onclick="editUser(${Number(user.id)})" aria-label="Editar ${escapeUserHtml(user.name)}" title="Editar usuario"><i class="fa-solid fa-pen"></i></button>
                    <button type="button" class="rbac-icon-button" onclick="resetUserPassword(${Number(user.id)})" aria-label="Restablecer contraseña de ${escapeUserHtml(user.name)}" title="Restablecer contraseña"><i class="fa-solid fa-key"></i></button>
                    <button type="button" class="rbac-icon-button rbac-danger-button" onclick="setUserInactive(${Number(user.id)})" aria-label="${user.status === 'activo' ? 'Dar de baja' : 'Eliminar'} ${escapeUserHtml(user.name)}" title="${user.status === 'activo' ? 'Dar de baja' : 'Eliminar cuenta'}"><i class="fa-solid fa-${user.status === 'activo' ? 'user-slash' : 'trash-can'}"></i></button>
                </div></td>`;
            tbody.appendChild(tr);
        });
    }
    const shownCount = filteredUsers.length === 0 ? 0 : Math.min(startIndex + pageUsers.length, filteredUsers.length);
    const summary = document.getElementById('user-table-summary');
    if (summary) summary.textContent = `Mostrando ${shownCount} de ${filteredUsers.length} registros`;
    renderUserPagination(pageCount);
    updateUserMetrics();
}

function initializeAdminRolesModule() {
    document.querySelectorAll('[data-action="create-user"]').forEach(button => {
        button.addEventListener('click', openUserModal);
    });
    renderUserTable();
}

function renderUserPagination(pageCount) {
    const pagination = document.getElementById('user-pagination');
    if (!pagination) return;
    pagination.innerHTML = '';
    for (let page = 1; page <= pageCount; page += 1) {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = page;
        button.className = `user-page-button${page === currentUserPage ? ' active' : ''}`;
        button.setAttribute('aria-label', `Página ${page}`);
        button.setAttribute('aria-current', page === currentUserPage ? 'page' : 'false');
        button.addEventListener('click', () => { currentUserPage = page; renderUserTable(); });
        pagination.appendChild(button);
    }
}

function filterUserTable() {
    currentUserPage = 1;
    renderUserTable();
}

function switchUserAdminTab(tab) {
    userAdminTab = tab === 'matrix' ? 'matrix' : 'directory';
    const directoryActive = userAdminTab === 'directory';
    document.getElementById('rbac-panel-directory')?.toggleAttribute('hidden', !directoryActive);
    document.getElementById('rbac-panel-matrix')?.toggleAttribute('hidden', directoryActive);
    document.getElementById('rbac-tab-directory')?.classList.toggle('active', directoryActive);
    document.getElementById('rbac-tab-matrix')?.classList.toggle('active', !directoryActive);
    document.getElementById('rbac-tab-directory')?.setAttribute('aria-selected', String(directoryActive));
    document.getElementById('rbac-tab-matrix')?.setAttribute('aria-selected', String(!directoryActive));
    if (!directoryActive) renderPermissionMatrix();
}

function renderPermissionMatrix() {
    const tbody = document.getElementById('rbac-matrix-body');
    if (!tbody) return;
    tbody.innerHTML = moduleDefinitions.map(module => `
        <tr><th scope="row"><strong>${module.name}</strong><small>${module.details}</small></th>
        ${roleIds.map(role => {
            const current = matrixPermissions[module.id]?.[role] || 'none';
            return `<td><select class="rbac-permission-select permission-${current}" aria-label="${module.name}: permiso de ${roleLabels[role]}" onchange="updateMatrixPermission('${module.id}', '${role}', this.value)">
                ${permissionLevels.map(level => `<option value="${level}" ${level === current ? 'selected' : ''}>${permissionLabels[level]}</option>`).join('')}
            </select></td>`;
        }).join('')}</tr>`).join('');
}

function updateMatrixPermission(moduleId, role, level) {
    if (!permissionLevels.includes(level) || !moduleDefinitions.some(module => module.id === moduleId) || !roleLabels[role]) return;
    matrixPermissions[moduleId][role] = level;
    renderPermissionMatrix();
    renderUserTable();
    const feedback = document.getElementById('rbac-matrix-feedback');
    if (feedback) feedback.textContent = `Permiso de ${roleLabels[role]} actualizado a “${permissionLabels[level]}” para ${moduleDefinitions.find(module => module.id === moduleId).name}. Cambios locales, sin persistencia en servidor.`;
}

function syncRoleToLegacyType() {
    const role = document.getElementById('rbac-user-role')?.value || 'alumno';
    const legacyFields = document.getElementById('user-academic-fields');
    const paymentFields = document.getElementById('user-payment-fields');
    const isStudent = role === 'alumno';
    if (legacyFields) legacyFields.hidden = !isStudent;
    if (paymentFields) paymentFields.hidden = !isStudent;
    const label = document.getElementById('user-registration-label');
    const input = document.getElementById('user-registration');
    if (label && input) {
        label.textContent = role === 'docente' ? 'RFC / Identificador' : isStudent ? 'Matrícula' : 'Identificador';
        input.placeholder = role === 'docente' ? 'RFC o identificador docente' : isStudent ? 'Ej. 2026-0001' : 'Identificador de cuenta';
        input.required = role === 'docente' || isStudent;
    }
}

function onUserRoleChange() {
    syncRoleToLegacyType();
    setRoleDefaults(document.getElementById('rbac-user-role').value);
}

function setRoleDefaults(role, preserveSelected = false) {
    const currentModules = preserveSelected ? {
        finance: document.getElementById('user-access-finance').checked,
        school: document.getElementById('user-access-school').checked,
        export: document.getElementById('user-export-reports').checked,
        modify: document.getElementById('user-modify-payments').checked
    } : null;
    document.getElementById('user-access-finance').checked = currentModules?.finance ?? getRoleModuleAccess(role).includes('finanzas');
    document.getElementById('user-access-school').checked = currentModules?.school ?? getRoleModuleAccess(role).includes('control_escolar');
    document.getElementById('user-export-reports').checked = currentModules?.export ?? ['admin', 'control_escolar', 'finanzas'].includes(role);
    document.getElementById('user-modify-payments').checked = currentModules?.modify ?? ['admin', 'finanzas'].includes(role);
}

function openUserModal() {
    document.getElementById('user-form').reset();
    document.getElementById('user-id').value = '';
    document.getElementById('user-modal-title').textContent = 'Crear Nuevo Usuario';
    document.getElementById('rbac-user-role').value = 'alumno';
    document.getElementById('user-status').value = 'activo';
    document.getElementById('user-payment-status').value = 'adeudo';
    document.getElementById('user-period').value = '2026-1';
    document.getElementById('user-balance').value = '0';
    document.getElementById('user-form-feedback').textContent = '';
    syncRoleToLegacyType();
    setRoleDefaults('alumno');
    document.getElementById('user-modal').classList.add('active');
    document.getElementById('user-full-name').focus();
}

function closeUserModal() {
    document.getElementById('user-modal')?.classList.remove('active');
}

function editUser(id) {
    const user = usersDB.find(candidate => candidate.id === id);
    if (!user) { alert('No se encontró el usuario que deseas editar.'); return; }
    document.getElementById('user-form').reset();
    document.getElementById('user-modal-title').textContent = 'Editar Usuario';
    document.getElementById('user-id').value = user.id;
    document.getElementById('user-full-name').value = user.name;
    document.getElementById('rbac-user-role').value = normalizeUserRole(user);
    document.getElementById('user-registration').value = user.registration || '';
    document.getElementById('user-email').value = user.email;
    document.getElementById('user-username').value = user.username || '';
    document.getElementById('user-phone').value = user.phone || '';
    document.getElementById('user-period').value = user.period || '2026-1';
    document.getElementById('user-payment-status').value = user.paymentStatus || 'adeudo';
    document.getElementById('user-balance').value = Number(user.balance || 0).toFixed(2);
    const careerSelect = document.getElementById('user-career');
    if (user.career && ![...careerSelect.options].some(option => option.value === user.career)) careerSelect.add(new Option(user.career, user.career));
    if (user.career) careerSelect.value = user.career;
    document.getElementById('user-status').value = user.status;
    syncRoleToLegacyType();
    document.getElementById('user-access-finance').checked = Boolean(user.accessFinance);
    document.getElementById('user-access-school').checked = Boolean(user.accessSchool);
    document.getElementById('user-export-reports').checked = Boolean(user.exportReports);
    document.getElementById('user-modify-payments').checked = Boolean(user.modifyPayments);
    document.getElementById('user-form-feedback').textContent = '';
    document.getElementById('user-modal').classList.add('active');
    document.getElementById('user-full-name').focus();
}

function changeUserRole(id, role) {
    if (!roleLabels[role]) return;
    const user = usersDB.find(candidate => candidate.id === id);
    if (!user) return;
    user.role = role;
    user.type = role;
    renderUserTable();
}

function toggleUserStatus(id, active) {
    const user = usersDB.find(candidate => candidate.id === id);
    if (!user) return;
    user.status = active ? 'activo' : 'inactivo';
    renderUserTable();
}

function setUserInactive(id) {
    const user = usersDB.find(candidate => candidate.id === id);
    if (!user) return;
    if (user.status === 'activo') {
        if (confirm(`¿Dar de baja a ${user.name}? El registro se conservará como inactivo.`)) {
            user.status = 'inactivo';
            renderUserTable();
        }
    } else if (confirm(`¿Eliminar definitivamente la cuenta inactiva de ${user.name}?`)) {
        usersDB = usersDB.filter(candidate => candidate.id !== id);
        renderUserTable();
    }
}

function generateTemporaryPassword() {
    const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
    const values = new Uint32Array(14);
    if (window.crypto?.getRandomValues) window.crypto.getRandomValues(values);
    else for (let index = 0; index < values.length; index += 1) values[index] = Math.floor(Math.random() * 100000);
    return [...values].map(value => alphabet[value % alphabet.length]).join('');
}

function resetUserPassword(id) {
    const user = usersDB.find(candidate => candidate.id === id);
    if (!user) { alert('No se encontró el usuario.'); return; }
    if (!confirm(`¿Generar una contraseña temporal nueva para ${user.name}?`)) return;
    const password = generateTemporaryPassword();
    alert(`Contraseña temporal generada para ${user.name}:\n\n${password}\n\nEn producción debe entregarse de forma segura y reemplazarse con autenticación del servidor.`);
}

function handleUserSubmit(event) {
    event.preventDefault();
    const id = document.getElementById('user-id').value;
    const role = document.getElementById('rbac-user-role').value;
    const registration = document.getElementById('user-registration').value.trim();
    const email = document.getElementById('user-email').value.trim();
    const username = document.getElementById('user-username').value.trim();
    const feedback = document.getElementById('user-form-feedback');
    const duplicate = usersDB.find(user => user.id !== Number(id) && (
        user.email.toLocaleLowerCase('es-MX') === email.toLocaleLowerCase('es-MX') ||
        (user.username || '').toLocaleLowerCase('es-MX') === username.toLocaleLowerCase('es-MX') ||
        (registration && (user.registration || '').toLocaleLowerCase('es-MX') === registration.toLocaleLowerCase('es-MX'))
    ));
    if (duplicate) {
        feedback.textContent = 'El correo, nombre de usuario o matrícula/RFC ya está registrado.';
        feedback.classList.add('rbac-feedback-error');
        return;
    }
    const password = document.getElementById('user-password').value;
    if (!id && password && password.length < 8) {
        feedback.textContent = 'La contraseña debe tener al menos 8 caracteres.';
        feedback.classList.add('rbac-feedback-error');
        return;
    }
    const userData = {
        name: document.getElementById('user-full-name').value.trim(),
        type: role,
        role,
        registration,
        username,
        email,
        phone: document.getElementById('user-phone').value.trim(),
        career: role === 'alumno' ? document.getElementById('user-career').value : role === 'docente' ? 'Área Docente' : '',
        period: document.getElementById('user-period').value || '2026-1',
        paymentStatus: role === 'alumno' ? document.getElementById('user-payment-status').value : 'al_corriente',
        balance: role === 'alumno' ? Number(document.getElementById('user-balance').value || 0) : 0,
        status: document.getElementById('user-status').value,
        accessFinance: document.getElementById('user-access-finance').checked && !getRoleModuleAccess(role).includes('finanzas'),
        accessSchool: document.getElementById('user-access-school').checked && !getRoleModuleAccess(role).includes('control_escolar'),
        exportReports: document.getElementById('user-export-reports').checked,
        modifyPayments: document.getElementById('user-modify-payments').checked
    };
    let temporaryPassword = password;
    if (id) {
        const index = usersDB.findIndex(user => user.id === Number(id));
        if (index < 0) { feedback.textContent = 'No se encontró la cuenta que intentas actualizar.'; return; }
        usersDB[index] = { ...usersDB[index], ...userData };
        if (password) temporaryPassword = password;
    } else {
        const nextId = usersDB.reduce((maxId, user) => Math.max(maxId, user.id), 0) + 1;
        usersDB.push({ id: nextId, ...userData, lastAccess: 'Sin acceso' });
        if (!temporaryPassword) temporaryPassword = generateTemporaryPassword();
    }
    closeUserModal();
    currentUserPage = 1;
    renderUserTable();
    if (temporaryPassword) {
        alert(`Contraseña temporal:\n\n${temporaryPassword}\n\nLa interfaz es una demostración local; no hay autenticación ni almacenamiento seguro en servidor.`);
    }
}

function formatPaymentStatus(status) {
    return ({ al_corriente: 'Al corriente', adeudo: 'Adeudo', beca: 'Beca' })[status] || 'Adeudo';
}

function exportUsersPdf(kind) {
    if (!window.jspdf?.jsPDF) { alert('No se pudo exportar el reporte porque la librería PDF no está disponible.'); return; }
    const records = kind === 'actual' ? getFilteredUsers() : usersDB.filter(user => user.type === kind);
    const labels = { actual: 'Vista actual', docente: 'Lista de docentes', alumno: 'Lista de alumnos' };
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });
    doc.setFontSize(16);
    doc.text(`CEPTRI · ${labels[kind] || 'Usuarios'}`, 14, 16);
    doc.setFontSize(9);
    doc.text(`Registros: ${records.length} · Generado: ${new Date().toLocaleDateString('es-MX')}`, 14, 23);
    const columns = ['Nombre', 'Rol', 'Usuario', 'Matrícula / RFC', 'Correo', 'Estado'];
    const rows = records.map(user => [user.name, roleLabels[normalizeUserRole(user)], user.username || '—', user.registration || '—', user.email, user.status === 'activo' ? 'Activo' : 'Inactivo']);
    if (typeof doc.autoTable === 'function') doc.autoTable({ head: [columns], body: rows, startY: 30, styles: { fontSize: 8 }, headStyles: { fillColor: [30, 58, 138] } });
    else {
        let y = 30;
        rows.forEach(row => { const text = doc.splitTextToSize(row.join(' · '), 265); if (y + text.length * 4 > 195) { doc.addPage(); y = 16; } doc.text(text, 14, y); y += text.length * 4 + 3; });
    }
    doc.save(`usuarios-${kind}-${new Date().toISOString().slice(0, 10)}.pdf`);
}

document.addEventListener('click', event => {
    if (event.target?.id === 'user-modal') closeUserModal();
});
document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.getElementById('user-modal')?.classList.contains('active')) closeUserModal();
});

renderUserTable();
