const academicCareers = [
    'Especialidad en Responsabilidades Administrativas (ERA)',
    'Especialidad en Justicia Administrativa (EJA)',
    'Especialidad en Derecho Administrativo (EDA)'
];
const academicGroupRecords = [
    { id: 'ERA-1A', label: '1º A', career: academicCareers[0], period: '2026-1' },
    { id: 'ERA-2A', label: '2º A', career: academicCareers[0], period: '2026-1' },
    { id: 'EJA-2B', label: '2º B', career: academicCareers[1], period: '2026-1' },
    { id: 'EJA-1A', label: '1º A', career: academicCareers[1], period: '2026-1' },
    { id: 'EDA-3A', label: '3º A', career: academicCareers[2], period: '2026-1' },
    { id: 'EDA-6C', label: '6º C', career: academicCareers[2], period: '2026-2' }
];
const academicSubjectsByCareer = {
    [academicCareers[0]]: ['Responsabilidades Administrativas', 'Procedimiento de Responsabilidad Administrativa', 'Ética Pública', 'Control Gubernamental'],
    [academicCareers[1]]: ['Justicia Administrativa', 'Procedimiento Contencioso Administrativo', 'Derecho Procesal Administrativo', 'Medios de Impugnación'],
    [academicCareers[2]]: ['Derecho Administrativo', 'Derecho Constitucional', 'Argumentación Jurídica', 'Teoría del Estado']
};

let teacherAssignments = [];
let studentEnrollments = [];
let activeAcademicTab = 'docente';

function escapeAcademicHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);
}

function populateAcademicSelect(id, placeholder, values) {
    const select = document.getElementById(id);
    if (!select) return;
    const currentValue = select.value;
    select.innerHTML = `<option value="">${placeholder}</option>`;
    values.forEach(value => {
        const option = document.createElement('option');
        option.value = value.value;
        option.textContent = value.label;
        select.appendChild(option);
    });
    if (values.some(value => value.value === currentValue)) select.value = currentValue;
}

function initializeAcademicAssignments() {
    if (!document.getElementById('teacher-assignment-form')) return;

    const teachers = (typeof usersDB !== 'undefined' ? usersDB : [])
        .filter(user => user.type === 'docente' && user.status === 'activo')
        .map(user => ({ value: String(user.id), label: user.name }));

    const groupOptions = academicGroupRecords.map(group => ({ value: group.id, label: `${group.label} · ${group.id.split('-')[0]}` }));
    populateAcademicSelect('assignment-teacher', 'Selecciona docente...', teachers);
    populateAcademicSelect('assignment-group', 'Selecciona grupo...', groupOptions);
    populateAcademicSelect('assignment-subject', 'Selecciona grupo primero...', []);
    populateAcademicSelect('enrollment-career', 'Selecciona especialidad...', academicCareers.map(career => ({ value: career, label: career })));
    initializeSampleEnrollments();
    updateEnrollmentGroups();
    populateExistingGroupSelect();
    switchAcademicTab(activeAcademicTab);
    renderTeacherAssignments();
    renderStudentEnrollments();
    renderSelectedGroup();
}

function renderTeacherSubjectOptions() {
    const groupId = document.getElementById('assignment-group')?.value || '';
    const group = academicGroupRecords.find(item => item.id === groupId);
    const subjects = group ? academicSubjectsByCareer[group.career] || [] : [];
    populateAcademicSelect('assignment-subject', subjects.length ? 'Selecciona materia...' : 'Selecciona grupo primero...', subjects.map(subject => ({ value: subject, label: subject })));
}

function initializeSampleEnrollments() {
    if (studentEnrollments.length) return;
    (typeof studentsDB !== 'undefined' ? studentsDB : []).forEach(studentRecord => {
        const group = academicGroupRecords.find(item => item.career === studentRecord.carrera && item.label === studentRecord.grupo);
        if (!group) return;
        studentEnrollments.push({
            id: Date.now() + Math.floor(Math.random() * 100000),
            name: studentRecord.nombre,
            registration: studentRecord.matricula,
            group: group.id,
            subject: (academicSubjectsByCareer[group.career] || [])[0]
        });
    });
}

function updateEnrollmentGroups() {
    const career = document.getElementById('enrollment-career')?.value || '';
    const groupSelect = document.getElementById('enrollment-group');
    if (!groupSelect) return;
    const groups = academicGroupRecords.filter(group => group.career === career);
    populateAcademicSelect('enrollment-group', groups.length ? 'Selecciona grupo...' : 'Selecciona primero especialidad...', groups.map(group => ({
        value: group.id,
        label: `${group.label} · ${group.period}`
    })));
    groupSelect.disabled = groups.length === 0;
    renderEnrollmentSubjects();
    renderStudentEnrollments();
}

function renderEnrollmentSubjects() {
    const container = document.getElementById('enrollment-subject-options');
    const groupId = document.getElementById('enrollment-group')?.value || '';
    const group = academicGroupRecords.find(item => item.id === groupId);
    if (!container) return;
    if (!group) {
        container.textContent = 'Selecciona primero una especialidad y grupo.';
        return;
    }
    const subjects = academicSubjectsByCareer[group.career] || [];
    container.innerHTML = subjects.map((subject, index) => `
        <label class="academic-subject-chip">
            <input type="checkbox" name="enrollment-subject" value="${escapeAcademicHtml(subject)}" ${index === 0 ? 'checked' : ''}>
            <span>${escapeAcademicHtml(subject)}</span>
        </label>`).join('');
}

function switchAcademicTab(tab) {
    activeAcademicTab = ['alumno', 'grupos'].includes(tab) ? tab : 'docente';
    const teacherTab = document.getElementById('tab-docente');
    const studentTab = document.getElementById('tab-alumno');
    const groupsTab = document.getElementById('tab-grupos');
    const teacherPanel = document.getElementById('panel-docente');
    const studentPanel = document.getElementById('panel-alumno');
    const groupsPanel = document.getElementById('panel-grupos');
    if (!teacherTab || !studentTab || !groupsTab || !teacherPanel || !studentPanel || !groupsPanel) return;

    [[teacherTab, teacherPanel, 'docente'], [studentTab, studentPanel, 'alumno'], [groupsTab, groupsPanel, 'grupos']]
        .forEach(([button, panel, name]) => {
            const active = activeAcademicTab === name;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', String(active));
            panel.hidden = !active;
        });
}

function setAcademicFeedback(message, isError = false) {
    const feedback = document.getElementById('academic-feedback');
    if (!feedback) return;
    feedback.textContent = message;
    feedback.classList.toggle('error', isError);
}

function createTeacherAssignment(event) {
    event.preventDefault();
    const teacherId = Number(document.getElementById('assignment-teacher').value);
    const group = document.getElementById('assignment-group').value;
    const subject = document.getElementById('assignment-subject').value;
    const teacher = (typeof usersDB !== 'undefined' ? usersDB : []).find(user => user.id === teacherId);
    if (!teacher || !group || !subject) {
        setAcademicFeedback('Selecciona docente, grupo y materia.', true);
        return;
    }

    const duplicate = teacherAssignments.some(item =>
        item.teacherId === teacherId && item.group === group && item.subject === subject
    );
    if (duplicate) {
        setAcademicFeedback('Esta asignación ya está registrada.', true);
        return;
    }

    teacherAssignments.unshift({
        id: Date.now(),
        teacherId,
        teacherName: teacher.name,
        group,
        subject
    });
    document.getElementById('teacher-assignment-form').reset();
    renderTeacherSubjectOptions();
    renderTeacherAssignments();
    renderSelectedGroup();
    setAcademicFeedback(`Se asignó a ${teacher.name} al grupo ${group}.`);
}

function renderTeacherAssignments() {
    const container = document.getElementById('teacher-assignments-list');
    if (!container) return;
    if (teacherAssignments.length === 0) {
        container.innerHTML = '<div class="academic-empty-state">Sin asignaciones aún</div>';
        return;
    }

    container.innerHTML = `
        <div class="academic-table-wrap">
            <table class="academic-table">
                <thead><tr><th>Docente</th><th>Grupo</th><th>Materia</th><th>Acciones</th></tr></thead>
                <tbody>${teacherAssignments.map(item => `
                    <tr>
                        <td>${escapeAcademicHtml(item.teacherName)}</td>
                        <td>${escapeAcademicHtml(item.group)}</td>
                        <td>${escapeAcademicHtml(item.subject)}</td>
                        <td><button class="academic-remove-button" type="button" onclick="removeTeacherAssignment(${item.id})" aria-label="Eliminar asignación"><i class="fa-solid fa-link-slash"></i></button></td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

function removeTeacherAssignment(id) {
    teacherAssignments = teacherAssignments.filter(item => item.id !== id);
    renderTeacherAssignments();
    renderSelectedGroup();
    setAcademicFeedback('La asignación fue eliminada.');
}

function findAcademicStudent(registration) {
    const normalized = registration.trim().toLocaleLowerCase('es-MX');
    const user = (typeof usersDB !== 'undefined' ? usersDB : [])
        .find(candidate => candidate.type === 'alumno' &&
            candidate.registration.toLocaleLowerCase('es-MX') === normalized);
    if (user) return { name: user.name, registration: user.registration, career: user.career };

    const student = (typeof studentsDB !== 'undefined' ? studentsDB : [])
        .find(candidate => candidate.matricula.toLocaleLowerCase('es-MX') === normalized);
    return student ? { name: student.nombre, registration: student.matricula, career: student.carrera } : null;
}

function addStudentEnrollment(registration, group, subject) {
    const student = findAcademicStudent(registration);
    if (!student) return `No se encontró la matrícula ${registration}.`;
    const groupRecord = academicGroupRecords.find(item => item.id === group);
    if (!groupRecord) return `El grupo ${group} no existe.`;
    if (student.career !== groupRecord.career) return `${student.name} no pertenece a la especialidad seleccionada para este grupo.`;
    if (!(academicSubjectsByCareer[groupRecord.career] || []).includes(subject)) return `La materia ${subject} no corresponde al grupo seleccionado.`;
    if (studentEnrollments.some(item =>
        item.registration.toLocaleLowerCase('es-MX') === student.registration.toLocaleLowerCase('es-MX') &&
        item.group === group && item.subject === subject
    )) {
        return `${student.name} ya está inscrito en ${group} - ${subject}.`;
    }

    studentEnrollments.unshift({
        id: Date.now() + Math.floor(Math.random() * 1000),
        name: student.name,
        registration: student.registration,
        group,
        subject
    });
    return '';
}

function createStudentEnrollment(event) {
    event.preventDefault();
    const registration = document.getElementById('enrollment-registration').value;
    const group = document.getElementById('enrollment-group').value;
    const subjects = [...document.querySelectorAll('input[name="enrollment-subject"]:checked')].map(input => input.value);
    if (!subjects.length) {
        setAcademicFeedback('Selecciona al menos una materia.', true);
        return;
    }
    if (!group) {
        setAcademicFeedback('Selecciona el grupo antes de elegir materias.', true);
        return;
    }
    const student = findAcademicStudent(registration);
    const groupRecord = academicGroupRecords.find(item => item.id === group);
    const errors = [];
    if (!student) errors.push(`No se encontró la matrícula ${registration}.`);
    else if (student.career !== groupRecord?.career) errors.push(`${student.name} no pertenece a la especialidad seleccionada para este grupo.`);
    const duplicateSubjects = subjects.filter(subject =>
        studentEnrollments.some(item =>
            item.registration.toLocaleLowerCase('es-MX') === student?.registration.toLocaleLowerCase('es-MX') &&
            item.group === group && item.subject === subject
        )
    );
    if (duplicateSubjects.length) errors.push(`Ya existe inscripción para: ${duplicateSubjects.join(', ')}.`);
    if (errors.length) {
        setAcademicFeedback(errors.join(' '), true);
        return;
    }
    let added = 0;
    subjects.forEach(subject => {
        const error = addStudentEnrollment(registration, group, subject);
        if (!error) added += 1;
    });

    renderStudentEnrollments();
    renderSelectedGroup();
    document.getElementById('enrollment-registration').value = '';
    setAcademicFeedback(`${added} materia(s) asignada(s) correctamente.`);
}

function renderStudentEnrollments() {
    const container = document.getElementById('student-enrollments-list');
    if (!container) return;
    const selectedGroup = document.getElementById('enrollment-group')?.value || '';
    const entries = selectedGroup
        ? studentEnrollments.filter(item => item.group === selectedGroup)
        : studentEnrollments;

    if (entries.length === 0) {
        container.innerHTML = `<div class="academic-empty-state">${selectedGroup ? `Sin alumnos inscritos en ${escapeAcademicHtml(selectedGroup)}` : 'Aún no hay alumnos inscritos'}</div>`;
        return;
    }

    container.innerHTML = `
        <div class="academic-table-wrap">
            <table class="academic-table">
                <thead><tr><th>Alumno</th><th>Matrícula</th><th>Grupo</th><th>Materia</th><th>Acciones</th></tr></thead>
                <tbody>${entries.map(item => `
                    <tr>
                        <td>${escapeAcademicHtml(item.name)}</td>
                        <td>${escapeAcademicHtml(item.registration)}</td>
                        <td>${escapeAcademicHtml(item.group)}</td>
                        <td>${escapeAcademicHtml(item.subject)}</td>
                        <td><button class="academic-remove-button" type="button" onclick="removeStudentEnrollment(${item.id})" aria-label="Eliminar inscripción"><i class="fa-solid fa-user-minus"></i></button></td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`;
}

function removeStudentEnrollment(id) {
    studentEnrollments = studentEnrollments.filter(item => item.id !== id);
    renderStudentEnrollments();
    renderSelectedGroup();
    setAcademicFeedback('La inscripción fue eliminada.');
}

function populateExistingGroupSelect() {
    const select = document.getElementById('existing-group-select');
    if (!select) return;
    select.innerHTML = academicGroupRecords.map(group =>
        `<option value="${escapeAcademicHtml(group.id)}">${escapeAcademicHtml(group.id)} · ${escapeAcademicHtml(group.career)}</option>`
    ).join('');
}

function renderSelectedGroup() {
    const select = document.getElementById('existing-group-select');
    const container = document.getElementById('existing-group-details');
    if (!select || !container) return;
    const group = academicGroupRecords.find(item => item.id === select.value);
    if (!group) {
        container.innerHTML = '<p class="academic-empty-state">No hay grupos registrados.</p>';
        return;
    }
    const assignments = teacherAssignments.filter(item => item.group === group.id);
    const enrollments = getUniqueGroupStudents(group.id);
    const subjects = academicSubjectsByCareer[group.career] || [];
    container.innerHTML = `
        <div class="academic-group-summary">
            <div><span>Grupo</span><strong>${escapeAcademicHtml(group.label)} · ${escapeAcademicHtml(group.id)}</strong></div>
            <div><span>Especialidad / Carrera</span><strong>${escapeAcademicHtml(group.career)}</strong></div>
            <div><span>Cuatrimestre</span><strong>${escapeAcademicHtml(group.period)}</strong></div>
        </div>
        <div class="academic-group-detail-grid">
            <section class="academic-group-detail">
                <h4>Materias y docentes</h4>
                <div class="academic-table-wrap"><table class="academic-table">
                    <thead><tr><th>Materia</th><th>Docente</th></tr></thead>
                    <tbody>${subjects.map(subject => {
                        const assignment = assignments.find(item => item.subject === subject);
                        return `<tr><td>${escapeAcademicHtml(subject)}</td><td>${escapeAcademicHtml(assignment?.teacherName || 'Sin docente asignado')}</td></tr>`;
                    }).join('')}</tbody>
                </table></div>
            </section>
            <section class="academic-group-detail">
                <h4>Alumnos inscritos <span class="academic-count">${enrollments.length}</span></h4>
                <div class="academic-table-wrap"><table class="academic-table">
                    <thead><tr><th>Matrícula</th><th>Nombre</th><th>Estatus</th></tr></thead>
                    <tbody>${enrollments.length ? enrollments.map(item => `
                        <tr><td>${escapeAcademicHtml(item.registration)}</td><td>${escapeAcademicHtml(item.name)}</td><td><span class="user-status-badge user-active">Inscrito</span></td></tr>`
                    ).join('') : '<tr><td colspan="3" class="academic-empty-state">Aún no hay alumnos inscritos.</td></tr>'}</tbody>
                </table></div>
            </section>
        </div>
        <div class="academic-group-report-actions">
            <button class="btn-primary academic-primary-button" type="button" onclick="exportAcademicGroupPdf('${escapeAcademicHtml(group.id)}')">
                <i class="fa-solid fa-file-pdf" aria-hidden="true"></i> Exportar Reporte de Grupo (PDF)
            </button>
        </div>`;
}

function getUniqueGroupStudents(groupId) {
    const studentsByRegistration = new Map();
    studentEnrollments.filter(item => item.group === groupId).forEach(item => {
        const key = item.registration.toLocaleLowerCase('es-MX');
        const existing = studentsByRegistration.get(key);
        if (existing) {
            if (!existing.subjects.includes(item.subject)) existing.subjects.push(item.subject);
            return;
        }
        studentsByRegistration.set(key, { ...item, subjects: [item.subject] });
    });
    return [...studentsByRegistration.values()];
}

function exportAcademicGroupPdf(groupId) {
    if (!window.jspdf?.jsPDF) {
        setAcademicFeedback('No se pudo exportar el reporte: la librería PDF no está disponible.', true);
        return;
    }
    const group = academicGroupRecords.find(item => item.id === groupId);
    if (!group) {
        setAcademicFeedback('No se encontró el grupo para exportar.', true);
        return;
    }
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    const assignments = teacherAssignments.filter(item => item.group === group.id);
    const enrollments = getUniqueGroupStudents(group.id);
    const subjects = academicSubjectsByCareer[group.career] || [];
    doc.setFontSize(16);
    doc.text('CEPTRI · Reporte de Grupo', 14, 18);
    doc.setFontSize(10);
    doc.text(`Grupo: ${group.label} · ${group.id}`, 14, 29);
    doc.text(`Especialidad: ${group.career}`, 14, 36);
    doc.text(`Cuatrimestre: ${group.period}`, 14, 43);
    doc.text('Materias y docentes', 14, 56);
    let y = 64;
    subjects.forEach(subject => {
        const assignment = assignments.find(item => item.subject === subject);
        const lines = doc.splitTextToSize(`${subject} — ${assignment?.teacherName || 'Sin docente asignado'}`, 180);
        doc.text(lines, 14, y);
        y += lines.length * 5 + 2;
    });
    y += 5;
    if (y > 250) { doc.addPage(); y = 18; }
    doc.text(`Alumnos inscritos (${enrollments.length})`, 14, y);
    y += 8;
    enrollments.forEach((item, index) => {
        const lines = doc.splitTextToSize(`${index + 1}. ${item.registration} · ${item.name} · Inscrito`, 180);
        if (y + lines.length * 5 > 280) { doc.addPage(); y = 18; }
        doc.text(lines, 14, y);
        y += lines.length * 5 + 2;
    });
    doc.save(`reporte-grupo-${group.id}.pdf`);
    setAcademicFeedback(`Reporte PDF del grupo ${group.id} descargado.`);
}

function parseCsvRow(line) {
    const values = [];
    let value = '';
    let quoted = false;
    for (let index = 0; index < line.length; index += 1) {
        const character = line[index];
        if (character === '"' && quoted && line[index + 1] === '"') {
            value += '"';
            index += 1;
        } else if (character === '"') {
            quoted = !quoted;
        } else if (character === ',' && !quoted) {
            values.push(value.trim());
            value = '';
        } else {
            value += character;
        }
    }
    values.push(value.trim());
    return values;
}

async function importStudentEnrollments(event) {
    const input = event.target;
    const file = input.files?.[0];
    if (!file) return;
    if (!file.name.toLocaleLowerCase('es-MX').endsWith('.csv')) {
        setAcademicFeedback('Selecciona un archivo CSV válido.', true);
        input.value = '';
        return;
    }

    try {
        const content = await file.text();
        const rows = content.split(/\r?\n/).filter(line => line.trim()).map(parseCsvRow);
        if (rows.length === 0) {
            setAcademicFeedback('El archivo CSV está vacío.', true);
            return;
        }

        const first = rows[0].map(value => value.toLocaleLowerCase('es-MX'));
        const startAt = first[0] === 'matricula' || first[0] === 'matrícula' ? 1 : 0;
        let imported = 0;
        const errors = [];
        rows.slice(startAt).forEach((row, index) => {
            if (row.length < 3 || row.slice(0, 3).some(value => !value)) {
                errors.push(`Fila ${index + startAt + 1}: se requieren matrícula, grupo y materia.`);
                return;
            }
            const error = addStudentEnrollment(row[0], row[1], row[2]);
            if (error) errors.push(`Fila ${index + startAt + 1}: ${error}`);
            else imported += 1;
        });

        renderStudentEnrollments();
        const summary = `${imported} inscripción(es) importada(s).`;
        setAcademicFeedback(errors.length ? `${summary} ${errors.slice(0, 3).join(' ')}` : summary, errors.length > 0);
    } catch (error) {
        console.error('Error al importar inscripciones CSV:', error);
        setAcademicFeedback('No se pudo leer el archivo CSV.', true);
    } finally {
        input.value = '';
    }
}
