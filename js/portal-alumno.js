const studentDocumentRequirements = [
    { id: 'acta', name: 'Acta de Nacimiento' },
    { id: 'bachillerato', name: 'Certificado de Bachillerato' },
    { id: 'curp', name: 'CURP' },
    { id: 'domicilio', name: 'Comprobante de Domicilio' },
    { id: 'identificacion', name: 'Identificación Oficial' }
];

const studentGradeRecords = [
    { period: '2026-1', code: 'DER-301', subject: 'Derecho Constitucional', partials: [9.2, 9.5, 9.8], status: 'Aprobada' },
    { period: '2026-1', code: 'DER-302', subject: 'Teoría del Estado', partials: [8.5, 8.8, 8.8], status: 'Aprobada' },
    { period: '2026-1', code: 'DER-303', subject: 'Derecho Administrativo', partials: [7.0, 7.5, 7.7], status: 'Aprobada' },
    { period: '2026-1', code: 'DER-304', subject: 'Argumentación Jurídica', partials: [8.0, 7.5, null], status: 'En curso' },
    { period: '2025-3', code: 'DER-201', subject: 'Introducción al Derecho', partials: [8.5, 9.0, 9.2], status: 'Aprobada' }
];

let studentDocumentStore = {};
let studentDocumentPreviewUrl = '';
let selectedReviewDocumentId = '';
let studentProfileData = {};

function escapePortalHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    })[character]);
}

function getPortalStudent() {
    const studentUser = (typeof usersDB !== 'undefined' ? usersDB : [])
        .find(user => user.type === 'alumno' && user.registration === '2024-0312');
    const studentRecord = (typeof studentsDB !== 'undefined' ? studentsDB : [])
        .find(student => student.matricula === '2024-0312');
    return {
        name: studentUser?.name || studentRecord?.nombre || 'Ana García López',
        registration: studentUser?.registration || studentRecord?.matricula || '2024-0312',
        career: studentUser?.career || studentRecord?.carrera || 'Lic. en Derecho'
    };
}

function getStudentDocuments(registration) {
    if (!studentDocumentStore[registration]) {
        studentDocumentStore[registration] = studentDocumentRequirements.map(requirement => ({
            ...requirement,
            status: 'pendiente',
            observation: '',
            fileName: '',
            fileType: '',
            fileUrl: ''
        }));
    }
    return studentDocumentStore[registration];
}

function initializeStudentPortal() {
    const student = getPortalStudent();
    document.querySelector('.student-portal')?.classList.add('portal-sidebar-navigation');
    document.getElementById('portal-student-name').textContent = student.name;
    document.getElementById('portal-registration').textContent = student.registration;
    document.getElementById('portal-student-career').textContent = student.career;
    document.getElementById('profile-name').value = student.name;
    document.getElementById('profile-registration').value = student.registration;
    document.getElementById('portal-avatar').textContent = student.name.split(/\s+/).slice(0, 2).map(part => part[0]).join('').toLocaleUpperCase('es-MX');
    initializeStudentProfileForm();
    renderStudentDocuments();
    renderStudentGrades();
    const section = ['dashboard', 'profile', 'documents', 'grades'].includes(requestedStudentPortalSection)
        ? requestedStudentPortalSection
        : 'dashboard';
    const selectedTab = document.querySelector(`.portal-tab[onclick*="'${section}'"]`);
    switchStudentPortalTab(section, selectedTab || document.querySelector('.portal-tab'));
}

function switchStudentPortalTab(tab, clickedTab) {
    const validTabs = ['dashboard', 'profile', 'documents', 'grades'];
    if (!validTabs.includes(tab)) return;
    document.querySelectorAll('.portal-tab').forEach(button => {
        button.classList.remove('active');
        button.removeAttribute('aria-current');
    });
    clickedTab.classList.add('active');
    clickedTab.setAttribute('aria-current', 'page');
    document.querySelectorAll('.portal-panel').forEach(panel => { panel.hidden = true; });
    document.getElementById(`portal-panel-${tab}`).hidden = false;
}

function initializeStudentProfileForm() {
    const form = document.getElementById('student-profile-form');
    if (!form) return;
    if (Object.keys(studentProfileData).length === 0) {
        [...form.elements].forEach(element => {
            if (element.id && !element.readOnly && element.type !== 'submit') {
                studentProfileData[element.id] = element.value;
            }
        });
        return;
    }
    Object.entries(studentProfileData).forEach(([id, value]) => {
        const field = document.getElementById(id);
        if (field) field.value = value;
    });
}

function saveStudentProfile(event) {
    event.preventDefault();
    const email = document.getElementById('contact-email').value.trim();
    const confirmEmail = document.getElementById('contact-email-confirm').value.trim();
    if (email.toLocaleLowerCase('es-MX') !== confirmEmail.toLocaleLowerCase('es-MX')) {
        document.getElementById('profile-save-feedback').textContent = 'Los correos electrónicos no coinciden.';
        document.getElementById('profile-save-feedback').className = 'portal-feedback error';
        document.getElementById('contact-email-confirm').focus();
        return;
    }

    const form = document.getElementById('student-profile-form');
    [...form.elements].forEach(element => {
        if (element.id && !element.readOnly && element.type !== 'submit') {
            studentProfileData[element.id] = element.value;
        }
    });
    document.getElementById('profile-save-feedback').textContent = 'Tus datos de contacto y domicilio fueron actualizados.';
    document.getElementById('profile-save-feedback').className = 'portal-feedback success';
}

function getDocumentStatusLabel(status) {
    const statuses = {
        aprobado: { label: 'Aprobado', className: 'status-approved' },
        en_revision: { label: 'En Revisión', className: 'status-review' },
        rechazado: { label: 'Rechazado', className: 'status-rejected' },
        pendiente: { label: 'Pendiente', className: 'status-review' }
    };
    return statuses[status] || statuses.pendiente;
}

function getExpedienteComplete(registration) {
    return getStudentDocuments(registration).every(document => document.status === 'aprobado');
}

function renderStudentDocuments() {
    const list = document.getElementById('student-documents-list');
    if (!list) return;
    const registration = getPortalStudent().registration;
    const documents = getStudentDocuments(registration);
    const statusBadge = document.getElementById('student-file-status');
    const complete = getExpedienteComplete(registration);
    statusBadge.textContent = complete ? 'Inscripción Completa / Aprobada' : 'Inscripción Incompleta';
    statusBadge.className = `document-status-badge ${complete ? 'status-approved' : 'status-review'}`;

    list.innerHTML = documents.map(document => {
        const status = getDocumentStatusLabel(document.status);
        const observation = document.observation
            ? `<p class="document-observation"><strong>Observación:</strong> ${escapePortalHtml(document.observation)}</p>`
            : '';
        const uploadedFile = document.fileUrl
            ? `<a class="document-view-link" href="${document.fileUrl}" target="_blank" rel="noopener">Ver archivo: ${escapePortalHtml(document.fileName)}</a>`
            : '<span class="document-no-file">Aún no has subido este documento.</span>';
        return `
            <article class="student-document-row">
                <div class="student-document-info">
                    <div class="student-document-title"><i class="fa-solid fa-file-lines"></i><div><strong>${escapePortalHtml(document.name)}</strong><span>PDF, JPG o PNG · Máximo 5 MB</span></div></div>
                    <span class="document-status-badge ${status.className}">${status.label}</span>
                </div>
                <div class="student-document-controls">
                    <label class="document-upload-button" for="upload-${document.id}"><i class="fa-solid fa-cloud-arrow-up"></i> Subir Documento</label>
                    <input id="upload-${document.id}" class="document-file-input" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" onchange="uploadStudentDocument(event, '${document.id}')">
                    ${uploadedFile}
                </div>
                ${observation}
            </article>`;
    }).join('');
}

function uploadStudentDocument(event, documentId) {
    const file = event.target.files?.[0];
    if (!file) return;
    const allowedTypes = ['application/pdf', 'image/jpeg', 'image/png'];
    const allowedExtensions = /\.(pdf|jpe?g|png)$/i;
    if (!allowedTypes.includes(file.type) || !allowedExtensions.test(file.name)) {
        alert('Formato no válido. Sube un archivo PDF, JPG o PNG.');
        event.target.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        alert('El archivo excede el límite de 5 MB.');
        event.target.value = '';
        return;
    }

    const registration = getPortalStudent().registration;
    const documentRecord = getStudentDocuments(registration).find(item => item.id === documentId);
    if (!documentRecord) {
        alert('No se encontró el requisito de documento.');
        event.target.value = '';
        return;
    }
    if (documentRecord.fileUrl) URL.revokeObjectURL(documentRecord.fileUrl);
    documentRecord.fileUrl = URL.createObjectURL(file);
    documentRecord.fileName = file.name;
    documentRecord.fileType = file.type;
    documentRecord.status = 'en_revision';
    documentRecord.observation = '';
    renderStudentDocuments();
}

function renderStudentGrades() {
    const tbody = document.getElementById('student-grades-body');
    if (!tbody) return;
    const period = document.getElementById('grades-period-filter').value;
    const records = studentGradeRecords.filter(record => record.period === period);
    const passedSubjects = records.filter(record => record.status === 'Aprobada').length;
    const passedSubjectsMetric = document.getElementById('portal-passed-subjects');
    const enrolledSubjectsMetric = document.getElementById('portal-enrolled-subjects');
    const periodAverageMetric = document.getElementById('portal-period-average');
    if (passedSubjectsMetric) passedSubjectsMetric.textContent = passedSubjects;
    if (enrolledSubjectsMetric) enrolledSubjectsMetric.textContent = records.length;
    const subjectAverages = records.map(record => {
        const grades = record.partials.filter(grade => typeof grade === 'number' && Number.isFinite(grade));
        return grades.length ? grades.reduce((sum, grade) => sum + grade, 0) / grades.length : null;
    }).filter(grade => grade !== null);
    const periodAverage = subjectAverages.length
        ? (subjectAverages.reduce((sum, grade) => sum + grade, 0) / subjectAverages.length).toFixed(2)
        : '—';
    if (periodAverageMetric) periodAverageMetric.textContent = periodAverage;
    if (records.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="portal-empty-table">No hay asignaturas registradas para este cuatrimestre.</td></tr>';
        return;
    }
    tbody.innerHTML = records.map(record => {
        const validPartials = record.partials.filter(grade => typeof grade === 'number' && Number.isFinite(grade));
        const generalGrade = validPartials.length === 0
            ? '—'
            : (validPartials.reduce((sum, grade) => sum + grade, 0) / validPartials.length).toFixed(1);
        return `
            <tr>
                <td class="portal-course-code">${escapePortalHtml(record.code)}</td>
                <td><strong>${escapePortalHtml(record.subject)}</strong></td>
                ${record.partials.map(grade => `<td class="portal-partial-grade">${grade === null ? 'Pendiente' : Number(grade).toFixed(1)}</td>`).join('')}
                <td><strong class="portal-general-grade">${generalGrade}</strong></td>
                <td><span class="document-status-badge ${record.status === 'Aprobada' ? 'status-approved' : 'status-review'}">${escapePortalHtml(record.status)}</span></td>
            </tr>`;
    }).join('');
}

function initializeDocumentReview() {
    const select = document.getElementById('review-student-select');
    if (!select) return;
    const students = (typeof usersDB !== 'undefined' ? usersDB : []).filter(user => user.type === 'alumno');
    select.innerHTML = students.map(student =>
        `<option value="${escapePortalHtml(student.registration)}">${escapePortalHtml(student.name)} · ${escapePortalHtml(student.registration)}</option>`
    ).join('');
    if (students.length === 0) {
        select.innerHTML = '<option value="">No hay alumnos registrados</option>';
        document.getElementById('direct-document-file').disabled = true;
        renderDocumentReview();
        return;
    }
    const typeSelect = document.getElementById('direct-document-type');
    typeSelect.innerHTML = studentDocumentRequirements.map(requirement =>
        `<option value="${escapePortalHtml(requirement.id)}">${escapePortalHtml(requirement.name)}</option>`
    ).join('');
    const dropzone = document.getElementById('direct-document-dropzone');
    dropzone.addEventListener('dragover', event => {
        event.preventDefault();
        dropzone.classList.add('drag-over');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
    dropzone.addEventListener('drop', event => {
        event.preventDefault();
        dropzone.classList.remove('drag-over');
        const file = event.dataTransfer?.files?.[0];
        if (file) saveDirectDocumentFile(file);
    });
    dropzone.addEventListener('keydown', event => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            document.getElementById('direct-document-file').click();
        }
    });
    renderDocumentReview();
}

function renderDocumentReview() {
    const select = document.getElementById('review-student-select');
    const list = document.getElementById('review-document-list');
    if (!select || !list) return;
    const registration = select.value;
    const student = (typeof usersDB !== 'undefined' ? usersDB : []).find(user => user.registration === registration);
    selectedReviewDocumentId = '';
    document.getElementById('review-student-summary').textContent = student
        ? `${student.name} · Matrícula ${student.registration}`
        : 'Selecciona un alumno para revisar sus documentos.';
    document.getElementById('document-preview').innerHTML = 'Selecciona un archivo para previsualizarlo.';
    document.getElementById('document-preview').className = 'document-preview-empty';
    document.getElementById('document-review-actions').hidden = true;
    document.getElementById('document-rejection-form').hidden = true;
    if (!student) {
        list.innerHTML = '<p class="portal-empty-table">No hay alumnos disponibles para revisión.</p>';
        updateReviewCompletionBadge('');
        return;
    }

    const documents = getStudentDocuments(registration);
    list.innerHTML = documents.map(document => {
        const status = getDocumentStatusLabel(document.status);
        return `<button type="button" class="review-document-item${document.id === selectedReviewDocumentId ? ' selected' : ''}" onclick="selectReviewDocument('${document.id}')">
            <span><i class="fa-solid fa-file-lines"></i><strong>${escapePortalHtml(document.name)}</strong></span>
            <span class="document-status-badge ${status.className}">${status.label}</span>
            <small>${document.fileName ? escapePortalHtml(document.fileName) : 'Sin archivo cargado'}</small>
            ${document.observation ? `<small class="document-observation">${escapePortalHtml(document.observation)}</small>` : ''}
        </button>`;
    }).join('');
    updateReviewCompletionBadge(registration);
}

function updateReviewCompletionBadge(registration) {
    const badge = document.getElementById('review-completion-status');
    if (!badge) return;
    const documents = registration ? getStudentDocuments(registration) : [];
    const complete = documents.length > 0 && documents.every(document => document.status === 'aprobado');
    const uploaded = documents.some(document => Boolean(document.fileName));
    badge.textContent = complete ? 'Expediente Validado' : uploaded ? 'Documentos Subidos' : 'Incompleto';
    badge.className = `document-status-badge ${complete ? 'status-approved' : uploaded ? 'status-review' : 'status-rejected'}`;
}

function handleDirectDocumentUpload(event) {
    const file = event.target.files?.[0];
    if (file) saveDirectDocumentFile(file);
    event.target.value = '';
}

function saveDirectDocumentFile(file) {
    const registration = document.getElementById('review-student-select')?.value;
    const documentId = document.getElementById('direct-document-type')?.value;
    const feedback = document.getElementById('review-feedback');
    if (!registration || !documentId) {
        feedback.textContent = 'Selecciona un alumno y el tipo de documento antes de cargar.';
        feedback.className = 'portal-feedback error';
        return;
    }
    const extension = file.name.split('.').pop()?.toLocaleLowerCase('es-MX') || '';
    const allowed = ['pdf', 'jpg', 'jpeg', 'png'];
    if (!allowed.includes(extension) || (file.type && !['application/pdf', 'image/jpeg', 'image/png'].includes(file.type))) {
        feedback.textContent = 'Formato no válido. Sube un archivo PDF, JPG o PNG.';
        feedback.className = 'portal-feedback error';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        feedback.textContent = 'El archivo supera el límite permitido de 5 MB.';
        feedback.className = 'portal-feedback error';
        return;
    }
    const documentRecord = getStudentDocuments(registration).find(item => item.id === documentId);
    if (!documentRecord) {
        feedback.textContent = 'No se encontró el requisito documental seleccionado.';
        feedback.className = 'portal-feedback error';
        return;
    }
    if (documentRecord.fileUrl.startsWith('blob:')) URL.revokeObjectURL(documentRecord.fileUrl);
    documentRecord.fileName = file.name;
    documentRecord.fileType = file.type || (extension === 'pdf' ? 'application/pdf' : 'image/png');
    documentRecord.fileUrl = URL.createObjectURL(file);
    documentRecord.status = 'en_revision';
    documentRecord.observation = '';
    renderDocumentReview();
    selectReviewDocument(documentId);
    feedback.textContent = `Se agregó ${file.name} al expediente del alumno seleccionado.`;
    feedback.className = 'portal-feedback success';
}

function selectReviewDocument(documentId) {
    const registration = document.getElementById('review-student-select').value;
    const documentRecord = getStudentDocuments(registration).find(item => item.id === documentId);
    if (!documentRecord) return;
    selectedReviewDocumentId = documentId;
    const preview = document.getElementById('document-preview');
    const actions = document.getElementById('document-review-actions');
    const rejectionForm = document.getElementById('document-rejection-form');
    rejectionForm.hidden = true;
    if (!documentRecord.fileUrl) {
        preview.textContent = 'El alumno aún no ha subido este documento.';
        preview.className = 'document-preview-empty';
        actions.hidden = true;
    } else {
        preview.className = 'document-preview';
        if (documentRecord.fileType === 'application/pdf') {
            preview.innerHTML = `<iframe title="Previsualización de ${escapePortalHtml(documentRecord.name)}" src="${documentRecord.fileUrl}"></iframe>`;
        } else {
            preview.innerHTML = `<img alt="Previsualización de ${escapePortalHtml(documentRecord.name)}" src="${documentRecord.fileUrl}">`;
        }
        actions.hidden = false;
    }
    renderDocumentReviewListOnly(registration);
}

function renderDocumentReviewListOnly(registration) {
    const list = document.getElementById('review-document-list');
    const documents = getStudentDocuments(registration);
    list.innerHTML = documents.map(document => {
        const status = getDocumentStatusLabel(document.status);
        return `<button type="button" class="review-document-item${document.id === selectedReviewDocumentId ? ' selected' : ''}" onclick="selectReviewDocument('${document.id}')">
            <span><i class="fa-solid fa-file-lines"></i><strong>${escapePortalHtml(document.name)}</strong></span>
            <span class="document-status-badge ${status.className}">${status.label}</span>
            <small>${document.fileName ? escapePortalHtml(document.fileName) : 'Sin archivo cargado'}</small>
            ${document.observation ? `<small class="document-observation">${escapePortalHtml(document.observation)}</small>` : ''}
        </button>`;
    }).join('');
}

function reviewSelectedDocument(decision) {
    const registration = document.getElementById('review-student-select').value;
    const documentRecord = getStudentDocuments(registration).find(item => item.id === selectedReviewDocumentId);
    if (!documentRecord?.fileUrl) {
        document.getElementById('review-feedback').textContent = 'Selecciona primero un documento que tenga archivo.';
        document.getElementById('review-feedback').className = 'portal-feedback error';
        return;
    }

    documentRecord.status = decision;
    documentRecord.observation = '';
    renderDocumentReview();
    renderStudentDocuments();
    document.getElementById('review-feedback').textContent = decision === 'aprobado'
        ? 'Documento aprobado correctamente.'
        : 'Documento rechazado y motivo enviado al expediente.';
    document.getElementById('review-feedback').className = 'portal-feedback success';
}

function showDocumentRejectionForm() {
    const documentRecord = getStudentDocuments(document.getElementById('review-student-select').value)
        .find(item => item.id === selectedReviewDocumentId);
    if (!documentRecord?.fileUrl) {
        document.getElementById('review-feedback').textContent = 'Selecciona primero un documento que tenga archivo.';
        document.getElementById('review-feedback').className = 'portal-feedback error';
        return;
    }
    document.getElementById('document-rejection-reason').value = '';
    document.getElementById('document-rejection-form').hidden = false;
    document.getElementById('document-rejection-reason').focus();
}

function cancelDocumentRejection() {
    document.getElementById('document-rejection-form').hidden = true;
}

function confirmDocumentRejection() {
    const reason = document.getElementById('document-rejection-reason').value.trim();
    if (!reason) {
        document.getElementById('review-feedback').textContent = 'Escribe el motivo del rechazo para continuar.';
        document.getElementById('review-feedback').className = 'portal-feedback error';
        document.getElementById('document-rejection-reason').focus();
        return;
    }

    const registration = document.getElementById('review-student-select').value;
    const documentRecord = getStudentDocuments(registration).find(item => item.id === selectedReviewDocumentId);
    if (!documentRecord?.fileUrl) {
        document.getElementById('review-feedback').textContent = 'El archivo ya no está disponible para revisión.';
        document.getElementById('review-feedback').className = 'portal-feedback error';
        return;
    }
    documentRecord.status = 'rechazado';
    documentRecord.observation = reason;
    document.getElementById('document-rejection-form').hidden = true;
    renderDocumentReview();
    renderStudentDocuments();
    document.getElementById('review-feedback').textContent = 'Documento rechazado y motivo enviado al expediente.';
    document.getElementById('review-feedback').className = 'portal-feedback success';
}
