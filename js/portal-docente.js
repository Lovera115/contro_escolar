const teacherStudents = [
    { id: '2024-0312', registration: '2024-0312', name: 'Ana García López', attendance: 96 },
    { id: '2024-0313', registration: '2024-0313', name: 'Luis Hernández Cruz', attendance: 92 },
    { id: '2024-0314', registration: '2024-0314', name: 'María Fernanda Ruiz', attendance: 89 },
    { id: '2024-0315', registration: '2024-0315', name: 'Carlos Eduardo Pérez', attendance: 84 },
    { id: '2024-0316', registration: '2024-0316', name: 'Sofía Martínez Torres', attendance: 98 },
    { id: '2024-0317', registration: '2024-0317', name: 'Jorge Ramírez Soto', attendance: 91 }
];

const teacherGroups = [
    { id: 'der-301-3a', period: '2026-1', subject: 'Derecho Constitucional', cuatrimestre: '3er Cuatrimestre', group: '3º A - Lic. en Derecho', room: 'A-201', days: 'Lun-Mié-Vie', time: '08:00–10:00', students: 6 },
    { id: 'der-302-3b', period: '2026-1', subject: 'Teoría del Estado', cuatrimestre: '3er Cuatrimestre', group: '3º B - Lic. en Derecho', room: 'A-203', days: 'Mar-Jue', time: '10:00–12:00', students: 6 },
    { id: 'der-303-2a', period: '2026-1', subject: 'Derecho Administrativo', cuatrimestre: '2º Cuatrimestre', group: '2º A - Lic. en Derecho', room: 'B-105', days: 'Lun-Mié', time: '13:00–15:00', students: 6 },
    { id: 'der-201-2a', period: '2025-3', subject: 'Introducción al Derecho', cuatrimestre: '2º Cuatrimestre', group: '2º A - Lic. en Derecho', room: 'A-104', days: 'Mar-Jue', time: '08:00–10:00', students: 6 }
];

const teacherExamScores = [
    [9.2, 8.4, 9.0, 7.2, 9.8, 8.3],
    [8.8, 7.8, 9.2, 6.8, 9.4, 8.2],
    [9.0, 8.2, 8.8, 7.0, 9.6, 8.0]
];

let teacherAttendanceState = {};
let teacherPeriod = '2026-1';

function escapeTeacherHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

function initializeTeacherPortal() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('toggle-sidebar');
    if (sidebarToggle) {
        sidebarToggle.onclick = () => {
            const isOpen = sidebar.classList.toggle('teacher-sidebar-open');
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        };
        sidebarToggle.setAttribute('aria-label', 'Abrir o cerrar navegación');
    }
    const today = new Date();
    const localDate = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    document.getElementById('attendance-date').value = localDate;
    populateTeacherGroupFilters();
    restoreTeacherDrafts();
    renderTeacherGroupDashboard();
    renderTeacherAttendance();
    renderTeacherGrades();
}

function switchTeacherTab(tab, selectedButton) {
    const tabs = ['groups', 'attendance', 'grades'];
    if (!tabs.includes(tab)) return;
    document.querySelectorAll('.teacher-tab').forEach(button => {
        button.classList.toggle('active', button === selectedButton);
        button.setAttribute('aria-selected', String(button === selectedButton));
    });
    document.querySelectorAll('.teacher-panel').forEach(panel => { panel.hidden = true; });
    document.getElementById(`teacher-panel-${tab}`).hidden = false;
    const headings = {
        groups: 'Mis Grupos y Horario',
        attendance: 'Pase de Asistencia',
        grades: 'Cierre de Actas & Parciales'
    };
    document.getElementById('teacher-page-heading').textContent = headings[tab];
}

function changeTeacherPeriod(period) {
    teacherPeriod = period;
    populateTeacherGroupFilters();
    restoreTeacherDrafts();
    renderTeacherGroupDashboard();
    renderTeacherAttendance();
    renderTeacherGrades();
}

function teacherGroupsForPeriod() {
    return teacherGroups.filter(group => group.period === teacherPeriod);
}

function populateTeacherGroupFilters() {
    const groups = teacherGroupsForPeriod();
    const groupOptions = groups.map(group =>
        `<option value="${group.id}">${escapeTeacherHtml(group.subject)} · ${escapeTeacherHtml(group.group)}</option>`
    ).join('');
    ['attendance-group', 'grades-group'].forEach(id => {
        const select = document.getElementById(id);
        select.innerHTML = groupOptions;
        select.value = groups[0]?.id || '';
    });
}

function renderTeacherGroupDashboard() {
    const cards = document.getElementById('teacher-group-cards');
    if (!cards) return;
    const groups = teacherGroupsForPeriod();
    cards.innerHTML = groups.map(group => `
        <article class="teacher-group-card">
            <div class="teacher-group-card-top"><span class="teacher-course-icon"><i class="fa-solid fa-book-open"></i></span><span class="teacher-period-chip">${escapeTeacherHtml(group.cuatrimestre)}</span></div>
            <h3>${escapeTeacherHtml(group.subject)}</h3>
            <p>${escapeTeacherHtml(group.group)}</p>
            <dl>
                <div><dt>Aula</dt><dd>${escapeTeacherHtml(group.room)}</dd></div>
                <div><dt>Horario</dt><dd>${escapeTeacherHtml(group.days)} ${escapeTeacherHtml(group.time)}</dd></div>
                <div><dt>Alumnos</dt><dd>${group.students}</dd></div>
            </dl>
        </article>`).join('');

    const scheduleRows = teacherPeriod === '2025-3' ? [
        ['07:00 – 08:00', '', '', '', '', ''],
        ['08:00 – 09:00', '', 'introduction', '', 'introduction', ''],
        ['09:00 – 10:00', '', 'introduction', '', 'introduction', ''],
        ['10:00 – 11:00', '', '', '', '', ''],
        ['11:00 – 12:00', '', '', '', '', ''],
        ['12:00 – 13:00', '', '', '', '', ''],
        ['13:00 – 14:00', '', '', '', '', ''],
        ['14:00 – 15:00', '', '', '', '', ''],
        ['15:00 – 16:00', '', '', '', '', ''],
        ['16:00 – 17:00', '', '', '', '', ''],
        ['17:00 – 19:00', '', '', '', '', ''],
        ['19:00 – 21:00', '', '', '', '', '']
    ] : [
        ['07:00 – 08:00', '', '', '', '', ''],
        ['08:00 – 09:00', 'constitutional', '', 'constitutional', '', 'constitutional'],
        ['09:00 – 10:00', 'constitutional', '', 'constitutional', '', 'constitutional'],
        ['10:00 – 11:00', '', 'state', '', 'state', ''],
        ['11:00 – 12:00', '', 'state', '', 'state', ''],
        ['12:00 – 13:00', '', '', '', '', ''],
        ['13:00 – 14:00', 'administrative', '', 'administrative', '', ''],
        ['14:00 – 15:00', 'administrative', '', 'administrative', '', ''],
        ['15:00 – 16:00', '', '', '', '', ''],
        ['16:00 – 17:00', '', '', '', '', ''],
        ['17:00 – 19:00', '', '', '', '', ''],
        ['19:00 – 21:00', '', '', '', '', '']
    ];
    const scheduleClasses = {
        constitutional: ['Derecho Constitucional', 'A-201 · 3º A'],
        state: ['Teoría del Estado', 'A-203 · 3º B'],
        administrative: ['Derecho Administrativo', 'B-105 · 2º A'],
        introduction: ['Introducción al Derecho', 'A-104 · 2º A']
    };
    document.getElementById('teacher-schedule-body').innerHTML = scheduleRows.map(row => `
        <tr><th>${row[0]}</th>${row.slice(1).map(key => {
            const course = scheduleClasses[key];
            return `<td>${course ? `<span class="teacher-schedule-block ${key}"><strong>${course[0]}</strong><small>${course[1]}</small></span>` : ''}</td>`;
        }).join('')}</tr>`).join('');
}

function selectedTeacherGroup(selectId) {
    return teacherGroups.find(group => group.id === document.getElementById(selectId)?.value) || teacherGroups[0];
}

function getAttendanceKey(groupId, date, studentId) {
    return `${groupId}|${date}|${studentId}`;
}

function getAttendanceRecord(groupId, date, student) {
    const key = getAttendanceKey(groupId, date, student.id);
    if (!teacherAttendanceState[key]) {
        const defaults = ['presente', 'presente', 'presente', 'retardo', 'presente', 'falta'];
        teacherAttendanceState[key] = { status: defaults[teacherStudents.indexOf(student)] || 'presente', observation: '' };
    }
    return teacherAttendanceState[key];
}

function renderTeacherAttendance() {
    const group = selectedTeacherGroup('attendance-group');
    const date = document.getElementById('attendance-date')?.value || '';
    const body = document.getElementById('teacher-attendance-body');
    if (!body) return;
    body.innerHTML = teacherStudents.map((student, index) => {
        const record = getAttendanceRecord(group.id, date, student);
        return `<tr>
            <td>${index + 1}</td><td>${escapeTeacherHtml(student.registration)}</td><td><strong>${escapeTeacherHtml(student.name)}</strong></td>
            <td><select class="teacher-status-select status-${record.status}" aria-label="Asistencia de ${escapeTeacherHtml(student.name)}" onchange="updateTeacherAttendance('${student.id}', 'status', this.value)">${attendanceOptions(record.status)}</select></td>
            <td><span class="teacher-attendance-percent">${student.attendance}%</span></td>
            <td><input class="teacher-observation-input" type="text" maxlength="180" placeholder="Agregar observación" value="${escapeTeacherHtml(record.observation)}" aria-label="Observación de ${escapeTeacherHtml(student.name)}" onchange="updateTeacherAttendance('${student.id}', 'observation', this.value)"></td>
        </tr>`;
    }).join('');
    renderAttendanceSummary(group.id, date);
}

function attendanceOptions(selected) {
    return [
        ['presente', 'Presente'], ['retardo', 'Retardo'], ['justificada', 'Falta justificada'], ['falta', 'Falta']
    ].map(([value, label]) => `<option value="${value}" ${selected === value ? 'selected' : ''}>${label}</option>`).join('');
}

function updateTeacherAttendance(studentId, field, value) {
    const group = selectedTeacherGroup('attendance-group');
    const date = document.getElementById('attendance-date').value;
    const student = teacherStudents.find(item => item.id === studentId);
    if (!student) return;
    const record = getAttendanceRecord(group.id, date, student);
    record[field] = value;
    if (field === 'status') renderTeacherAttendance();
    else renderAttendanceSummary(group.id, date);
}

function renderAttendanceSummary(groupId, date) {
    const counts = { presente: 0, retardo: 0, justificada: 0, falta: 0 };
    teacherStudents.forEach(student => { counts[getAttendanceRecord(groupId, date, student).status] += 1; });
    document.getElementById('attendance-summary').innerHTML = `
        <span>Total: <strong>${teacherStudents.length}</strong></span>
        <span>Presentes: <strong>${counts.presente}</strong></span>
        <span>Faltas: <strong>${counts.falta + counts.justificada}</strong></span>
        <span>Retardos: <strong>${counts.retardo}</strong></span>
        <span>Justificadas: <strong>${counts.justificada}</strong></span>`;
}

function setAttendanceToday() {
    const today = new Date();
    document.getElementById('attendance-date').value = new Date(today.getTime() - today.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
    renderTeacherAttendance();
}

function markAllPresent() {
    const group = selectedTeacherGroup('attendance-group');
    const date = document.getElementById('attendance-date').value;
    teacherStudents.forEach(student => { getAttendanceRecord(group.id, date, student).status = 'presente'; });
    renderTeacherAttendance();
}

function downloadTeacherFile(content, type, fileName) {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function exportAttendanceCsv() {
    const group = selectedTeacherGroup('attendance-group');
    const date = document.getElementById('attendance-date').value;
    const rows = [
        ['No.', 'Matrícula', 'Nombre del alumno', 'Estatus', 'Asistencia del cuatrimestre', 'Observaciones'],
        ...teacherStudents.map((student, index) => {
            const record = getAttendanceRecord(group.id, date, student);
            return [index + 1, student.registration, student.name, record.status, `${student.attendance}%`, record.observation];
        })
    ];
    const csv = '\uFEFF' + rows.map(row => row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')).join('\r\n');
    downloadTeacherFile(csv, 'text/csv;charset=utf-8', `asistencia-${group.id}-${date}.csv`);
    showTeacherFeedback('Lista descargada en formato CSV compatible con Excel.');
}

function exportAttendancePdf() {
    if (!window.jspdf?.jsPDF) {
        showTeacherFeedback('No se pudo exportar el PDF porque la librería de reportes no está disponible.');
        return;
    }
    const group = selectedTeacherGroup('attendance-group');
    const date = document.getElementById('attendance-date').value;
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    doc.setFontSize(16);
    doc.text('Reporte de asistencia', 14, 18);
    doc.setFontSize(10);
    doc.text(`${group.subject} · ${group.group} · ${date} · ${teacherPeriod}`, 14, 26);
    let y = 38;
    doc.text('No.   Matrícula      Alumno                              Estatus          Asistencia', 14, y);
    y += 8;
    teacherStudents.forEach((student, index) => {
        const record = getAttendanceRecord(group.id, date, student);
        doc.text(`${index + 1}.    ${student.registration}   ${student.name}   ${record.status}   ${student.attendance}%`, 14, y);
        y += 7;
        if (y > 280) { doc.addPage(); y = 18; }
    });
    doc.save(`asistencia-${group.id}-${date}.pdf`);
    showTeacherFeedback('Reporte de asistencia exportado a PDF.');
}

function calculateTeacherPartial(student, partialNumber) {
    const grade = teacherExamScores[partialNumber - 1][teacherStudents.indexOf(student)];
    return Number.isFinite(grade) ? grade : null;
}

function teacherStudentFinalGrade(student) {
    const grades = [1, 2, 3].map(partial => calculateTeacherPartial(student, partial))
        .filter(Number.isFinite);
    return grades.length ? grades.reduce((total, grade) => total + grade, 0) / grades.length : null;
}

function finalGradeStatus(grade) {
    if (grade === null) return ['Pendiente', 'badge-warning'];
    if (grade >= 8) return ['Aprobado', 'badge-success'];
    if (grade >= 6) return ['A Extraordinario', 'badge-warning'];
    return ['Reprobado', 'badge-danger'];
}

function renderTeacherGrades() {
    const body = document.getElementById('teacher-grades-body');
    if (!body) return;
    const group = selectedTeacherGroup('grades-group');
    const rows = teacherStudents.map((student, index) => {
        const partials = [1, 2, 3].map(partial => {
            const grade = calculateTeacherPartial(student, partial);
            return `<td><div class="teacher-partial-inputs"><label>Nota <input type="number" min="0" max="10" step="0.1" value="${grade === null ? '' : grade.toFixed(1)}" aria-label="Calificación parcial ${partial} de ${escapeTeacherHtml(student.name)}" onchange="updateTeacherExam(${index}, ${partial}, this.value)"></label></div></td>`;
        }).join('');
        const average = teacherStudentFinalGrade(student);
        const [status, badgeClass] = finalGradeStatus(average);
        return `<tr><td>${index + 1}</td><td>${escapeTeacherHtml(student.registration)}</td><td><strong>${escapeTeacherHtml(student.name)}</strong></td>${partials}<td><strong class="teacher-final-average">${average === null ? '—' : average.toFixed(1)}</strong></td><td><span class="teacher-badge ${badgeClass}">${status}</span></td></tr>`;
    });
    body.innerHTML = rows.join('');
    const averages = teacherStudents.map(teacherStudentFinalGrade).filter(Number.isFinite);
    const groupAverage = averages.length ? averages.reduce((sum, grade) => sum + grade, 0) / averages.length : 0;
    const passingRate = averages.length ? averages.filter(grade => grade >= 8).length / averages.length * 100 : 0;
    document.getElementById('teacher-grade-metrics').innerHTML = `
        <article><span>Promedio del grupo</span><strong>${groupAverage.toFixed(1)}</strong><small>${escapeTeacherHtml(group.subject)}</small></article>
        <article><span>Porcentaje de aprobación</span><strong>${passingRate.toFixed(0)}%</strong><small>Calificación final ≥ 8</small></article>
        <article><span>Actas pendientes de firma</span><strong>${averages.length}</strong><small>${escapeTeacherHtml(group.group)}</small></article>`;
}

function updateTeacherExam(studentIndex, partialNumber, value) {
    const grade = Number(value);
    if (!Number.isFinite(grade) || grade < 0 || grade > 10) {
        showTeacherFeedback('La nota del examen debe estar entre 0 y 10.');
        renderTeacherGrades();
        return;
    }
    teacherExamScores[partialNumber - 1][studentIndex] = grade;
    renderTeacherGrades();
}

function parseTeacherCsvRow(line) {
    const values = [];
    let value = '';
    let quoted = false;
    for (let index = 0; index < line.length; index += 1) {
        const character = line[index];
        if (character === '"' && quoted && line[index + 1] === '"') {
            value += '"';
            index += 1;
        } else if (character === '"') quoted = !quoted;
        else if (character === ',' && !quoted) {
            values.push(value.trim());
            value = '';
        } else value += character;
    }
    values.push(value.trim());
    return values;
}

async function importTeacherGradesCsv(event) {
    const input = event.target;
    const file = input.files?.[0];
    if (!file) return;
    const feedback = document.getElementById('teacher-bulk-feedback');
    if (!file.name.toLocaleLowerCase('es-MX').endsWith('.csv')) {
        feedback.textContent = 'Selecciona un archivo CSV válido.';
        input.value = '';
        return;
    }
    try {
        const rows = (await file.text()).replace(/^\uFEFF/, '').split(/\r?\n/)
            .filter(line => line.trim()).map(parseTeacherCsvRow);
        if (rows.length === 0) {
            feedback.textContent = 'El archivo CSV está vacío.';
            return;
        }
        const firstCell = rows[0][0]?.toLocaleLowerCase('es-MX');
        const startAt = firstCell === 'matricula' || firstCell === 'matrícula' ? 1 : 0;
        let updated = 0;
        const errors = [];
        rows.slice(startAt).forEach((row, index) => {
            const lineNumber = index + startAt + 1;
            if (row.length < 4 || !row[0]) {
                errors.push(`Fila ${lineNumber}: se requiere matrícula y tres columnas de parcial.`);
                return;
            }
            const studentIndex = teacherStudents.findIndex(student => student.registration === row[0]);
            if (studentIndex < 0) {
                errors.push(`Fila ${lineNumber}: matrícula no encontrada en el grupo.`);
                return;
            }
            const values = row.slice(1, 4).map(value => value === '' ? null : Number(value));
            if (values.every(value => value === null) ||
                values.some(value => value !== null && (!Number.isFinite(value) || value < 0 || value > 10))) {
                errors.push(`Fila ${lineNumber}: ingresa al menos una calificación entre 0 y 10.`);
                return;
            }
            values.forEach((value, partialIndex) => {
                if (value !== null) teacherExamScores[partialIndex][studentIndex] = value;
            });
            updated += 1;
        });
        renderTeacherGrades();
        feedback.textContent = errors.length
            ? `${updated} alumno(s) actualizado(s). ${errors.slice(0, 3).join(' ')}`
            : `${updated} alumno(s) actualizado(s) correctamente.`;
    } catch (error) {
        console.error('Error al importar calificaciones CSV:', error);
        feedback.textContent = 'No se pudo leer el archivo CSV.';
    } finally {
        input.value = '';
    }
}

function saveTeacherGradeDraft() {
    const group = selectedTeacherGroup('grades-group');
    const draft = {
        period: teacherPeriod,
        groupId: group.id,
        exams: teacherExamScores,
        savedAt: new Date().toISOString()
    };
    try {
        localStorage.setItem(`teacher-grade-draft-${teacherPeriod}-${group.id}`, JSON.stringify(draft));
        document.getElementById('teacher-save-feedback').textContent = 'Borrador guardado en este navegador.';
    } catch (error) {
        console.error('No se pudo guardar el borrador docente:', error);
        document.getElementById('teacher-save-feedback').textContent = 'No se pudo guardar el borrador en este navegador.';
    }
}

function restoreTeacherDrafts() {
    try {
        const group = selectedTeacherGroup('grades-group');
        if (!group) return;
        const saved = localStorage.getItem(`teacher-grade-draft-${teacherPeriod}-${group.id}`);
        if (!saved) return;
        const draft = JSON.parse(saved);
        if (!Array.isArray(draft.exams) || draft.exams.length !== 3 ||
            !draft.exams.every(partial => Array.isArray(partial) && partial.length === teacherStudents.length &&
                partial.every(grade => Number.isFinite(grade) && grade >= 0 && grade <= 10))) {
            showTeacherFeedback('El borrador guardado no tiene un formato de calificaciones válido.');
            return;
        }
        teacherExamScores.splice(0, teacherExamScores.length, ...draft.exams.map(partial => [...partial]));
    } catch (error) {
        console.error('No se pudieron recuperar borradores docentes:', error);
        showTeacherFeedback('No se pudieron recuperar borradores guardados en este navegador.');
    }
}

function openActaSignature() {
    const dialog = document.getElementById('acta-signature-dialog');
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else showTeacherFeedback('Este navegador no permite abrir la confirmación de firma.');
}

function closeActaSignature() {
    document.getElementById('acta-signature-dialog').close();
}

function signTeacherActas(event) {
    event.preventDefault();
    const pin = document.getElementById('acta-signature-pin').value;
    if (!pin.trim()) return;
    document.getElementById('acta-signature-dialog').close();
    document.getElementById('acta-signature-pin').value = '';
    showTeacherFeedback('Firma simulada: el envío real requiere conectar el servicio institucional de firma y Control Escolar.');
}

function showTeacherFeedback(message) {
    const feedback = document.getElementById('teacher-feedback');
    if (feedback) feedback.textContent = message;
}
