// BASE DE DATOS SIMULADA CON MODELO CUATRIMESTRAL Y HISTORIAL DE PAGOS
let studentsDB = [
    {
        id: 1,
        matricula: "2024-0312",
        nombre: "Ana García López",
        generacion: "2024-2027",
        grupo: "3º A",
        email: "ana.garcia@ceptri.edu.mx",
        telefono: "722 123 4567",
        carrera: "Especialidad en Derecho Administrativo (EDA)",
        cuatrimestre: "2026-1",
        categoriaBeca: "Excelencia Académica",
        porcentajeBeca: 0,
        cuotaBaseInsc: 2500,
        cuotaBaseCol: 3200,
        estado: "al_corriente",
        pagos: [
            { folio: "FAC-2026-00341", concepto: "Inscripción", monto: 2500, periodo: "2026-1", fecha: "08 Ago 2026", metodo: "Transferencia", comprobante: "BBVA-TRF-88321", registradoPor: "Lic. Carmen Flores" },
            { folio: "FAC-2026-00342", concepto: "Septiembre", monto: 3200, periodo: "2026-1", fecha: "08 Ago 2026", metodo: "Tarjeta", comprobante: "VISA-****4521", registradoPor: "Lic. Carmen Flores" },
            { folio: "FAC-2026-00587", concepto: "Octubre", monto: 3200, periodo: "2026-1", fecha: "08 Sep 2026", metodo: "Transferencia", comprobante: "BBVA-TRF-91244", registradoPor: "Lic. Carmen Flores" }
        ]
    },
    {
        id: 2,
        matricula: "2023-1145",
        nombre: "Luis Pérez Mora",
        generacion: "2023-2026",
        grupo: "2º B",
        email: "luis.perez@ceptri.edu.mx",
        telefono: "722 987 6543",
        carrera: "Especialidad en Justicia Administrativa (EJA)",
        cuatrimestre: "2026-1",
        categoriaBeca: "Apoyo Socioeconómico",
        porcentajeBeca: 0,
        cuotaBaseInsc: 2500,
        cuotaBaseCol: 3200,
        estado: "pendiente",
        pagos: [
            { folio: "FAC-2026-00102", concepto: "Inscripción", monto: 2500, periodo: "2026-1", fecha: "01 Ago 2026", metodo: "Efectivo", comprobante: "EF-99120", registradoPor: "C.P. Mario Soto" },
            { folio: "FAC-2026-00210", concepto: "Septiembre", monto: 3200, periodo: "2026-1", fecha: "05 Sep 2026", metodo: "Transferencia", comprobante: "SPEI-00129", registradoPor: "C.P. Mario Soto" }
        ]
    },
    {
        id: 3,
        matricula: "2025-0019",
        nombre: "Carlos Eduardo Ríos",
        generacion: "2025-2028",
        grupo: "1º A",
        email: "carlos.rios@ceptri.edu.mx",
        telefono: "722 444 5566",
        carrera: "Especialidad en Responsabilidades Administrativas (ERA)",
        cuatrimestre: "2026-1",
        categoriaBeca: "Ninguna",
        porcentajeBeca: 0,
        cuotaBaseInsc: 2500,
        cuotaBaseCol: 3200,
        estado: "vencido",
        pagos: [
            { folio: "FAC-2026-00015", concepto: "Inscripción", monto: 2500, periodo: "2026-1", fecha: "15 Jul 2026", metodo: "Efectivo", comprobante: "EF-10023", registradoPor: "Lic. Carmen Flores" }
        ]
    },
    {
        id: 4,
        matricula: "2022-0981",
        nombre: "Sofia Villalobos",
        generacion: "2022-2025",
        grupo: "6º C",
        email: "sofia.v@ceptri.edu.mx",
        telefono: "722 333 2211",
        carrera: "Especialidad en Derecho Administrativo (EDA)",
        cuatrimestre: "2026-2",
        categoriaBeca: "Convenio Institucional",
        porcentajeBeca: 20,
        cuotaBaseInsc: 2500,
        cuotaBaseCol: 3200,
        estado: "baja",
        pagos: []
    }
];

let currentFilterStatus = 'todos';
let previousFinanceModalBodyOverflow = '';

function syncFinanceModalScrollLock() {
    const hasOpenModal = [...document.querySelectorAll('.modal-overlay')].some(modal => modal.classList.contains('active'));
    if (hasOpenModal) {
        if (!document.body.classList.contains('finance-modal-open')) {
            previousFinanceModalBodyOverflow = document.body.style.overflow;
        }
        document.body.classList.add('finance-modal-open');
        return;
    }
    document.body.classList.remove('finance-modal-open');
    document.body.style.overflow = previousFinanceModalBodyOverflow;
    previousFinanceModalBodyOverflow = '';
}

function initializeFinanceAudit(section = 'audit') {
    const showAudit = section !== 'payments';
    const auditHeading = document.getElementById('finance-audit-heading');
    const auditView = document.getElementById('finance-audit-view');
    const paymentsView = document.getElementById('legacy-finance-view');
    if (auditHeading) auditHeading.hidden = !showAudit;
    if (auditView) auditView.hidden = !showAudit;
    ['finance-new-student-button', 'legacy-new-student-button'].forEach(id => {
        const newStudentButton = document.getElementById(id);
        if (newStudentButton) newStudentButton.addEventListener('click', () => openStudentModal());
    });
    if (paymentsView) {
        paymentsView.hidden = showAudit;
        paymentsView.open = !showAudit;
    }

    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('toggle-sidebar');
    if (!sidebar || !sidebarToggle) return;
    sidebarToggle.onclick = () => {
        const isOpen = sidebar.classList.toggle('finance-sidebar-open');
        sidebarToggle.setAttribute('aria-expanded', String(isOpen));
    };
    sidebarToggle.setAttribute('aria-label', 'Abrir o cerrar navegación');
}

// FORMATEADOR DE MONEDA
const fmtMXN = (amount) => new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(amount);

function escapeFinanceHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);
}

function getStudentGeneration(student) {
    if (student.generacion) return student.generacion;
    const startYear = Number(String(student.matricula || '').slice(0, 4));
    return Number.isFinite(startYear) && startYear >= 2021
        ? `${startYear}-${startYear + 3}` : 'Sin generación';
}

function getStudentGroup(student) {
    return student.grupo || '1º A';
}

function getStudentTotalPaid(student) {
    return student.pagos.reduce((sum, payment) => sum + (Number(payment.monto) || 0), 0);
}

function normalizeCarrera(carrera) {
    const mapping = {
        'Ing. en TI': 'Especialidad en Responsabilidades Administrativas (ERA)',
        'Ingeniería en TI': 'Especialidad en Responsabilidades Administrativas (ERA)',
        'Lic. en Derecho': 'Especialidad en Derecho Administrativo (EDA)',
        'Lic. en Administración': 'Especialidad en Justicia Administrativa (EJA)'
    };
    return mapping[carrera] || carrera || 'Especialidad en Responsabilidades Administrativas (ERA)';
}

function normalizeCategoriaBeca(categoria) {
    const mapping = {
        Convenio: 'Convenio Institucional'
    };
    return mapping[categoria] || categoria || 'Ninguna';
}

// OBTENER CUOTA REAL CON BECA
function getRealFee(base, becaPct) {
    return base * (1 - (becaPct / 100));
}

// CALCULAR SALDO PENDIENTE DEL CUATRIMESTRE
// (Cuota Real Inscripción + 2 colegiaturas esperadas a la fecha) - Total Pagado
function calculateStudentBalance(student) {
    if (student.estado === 'baja') return 0;

    if (Number.isFinite(student.saldoPendiente)) {
        return Math.max(0, student.saldoPendiente);
    }

    const realInsc = getRealFee(student.cuotaBaseInsc, student.porcentajeBeca);
    const realCol = getRealFee(student.cuotaBaseCol, student.porcentajeBeca);

    // Total a pagar en el cuatrimestre actual (inscripción más las cuotas devengadas).
    const cuatrimestreExpected = realInsc + (realCol * 2);
    const totalPaid = student.pagos.reduce((sum, p) => sum + p.monto, 0);

    const balance = cuatrimestreExpected - totalPaid;
    return balance > 0 ? balance : 0;
}

// --- ACTUALIZAR KPIS Y PILLS COUNTERS ---
function updateKPIs() {
    const totalAlumnos = studentsDB.length;
    const alCorriente = studentsDB.filter(s => s.estado === 'al_corriente').length;
    const adeudos = studentsDB.filter(s => s.estado === 'adeudo').length;
    const pendientes = studentsDB.filter(s => s.estado === 'pendiente').length;
    const vencidos = studentsDB.filter(s => s.estado === 'vencido').length;
    const bajas = studentsDB.filter(s => s.estado === 'baja').length;

    const saldoTotal = studentsDB.reduce((sum, s) => sum + calculateStudentBalance(s), 0);

    // Asignar en el DOM
    if(document.getElementById('kpi-total-alumnos')) document.getElementById('kpi-total-alumnos').textContent = totalAlumnos;
    if(document.getElementById('kpi-al-corriente')) document.getElementById('kpi-al-corriente').textContent = alCorriente;
    if(document.getElementById('kpi-pendientes')) document.getElementById('kpi-pendientes').textContent = pendientes + adeudos;
    if(document.getElementById('kpi-vencidos')) document.getElementById('kpi-vencidos').textContent = vencidos;
    if(document.getElementById('kpi-saldo-total')) document.getElementById('kpi-saldo-total').textContent = fmtMXN(saldoTotal);

    // Actualizar contadores en las pills
    if(document.getElementById('count-todos')) document.getElementById('count-todos').textContent = totalAlumnos;
    if(document.getElementById('count-corriente')) document.getElementById('count-corriente').textContent = alCorriente;
    if(document.getElementById('count-adeudo')) document.getElementById('count-adeudo').textContent = adeudos;
    if(document.getElementById('count-pendiente')) document.getElementById('count-pendiente').textContent = pendientes;
    if(document.getElementById('count-vencido')) document.getElementById('count-vencido').textContent = vencidos;
    if(document.getElementById('count-baja')) document.getElementById('count-baja').textContent = bajas;
}

// --- RENDERIZAR TABLA DE ALUMNOS ---
function renderStudentsTable(data = studentsDB) {
    const tbody = document.getElementById('students-table-body');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (data.length === 0) {
        tbody.innerHTML = `<tr><td colspan="8" style="text-align:center; padding: 25px; color: var(--text-secondary);">No se encontraron alumnos con los criterios seleccionados.</td></tr>`;
        return;
    }

    data.forEach(s => {
        const saldo = calculateStudentBalance(s);

        const badgeMap = {
            al_corriente: { class: 'status-activo', label: 'Al corriente' },
            adeudo: { class: 'status-pendiente', label: 'Adeudo' },
            pendiente: { class: 'status-pendiente', label: 'Pendiente' },
            vencido: { class: 'status-atrasado', label: 'Vencido' },
            convenio: { class: 'status-info', label: 'Convenio / prórroga' },
            baja: { class: 'status-inactivo', label: 'Baja' }
        };

        const currentBadge = badgeMap[s.estado] || badgeMap.adeudo;

        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${s.matricula}</strong></td>
            <td><strong>${s.nombre}</strong></td>
            <td>${s.carrera}</td>
            <td>${s.cuatrimestre || '2026-1'}</td>
            <td><span class="badge ${currentBadge.class}">${currentBadge.label}</span></td>
            <td><strong style="color: ${saldo > 0 ? '#B91C1C' : '#15803D'};">${fmtMXN(saldo)}</strong></td>
            <td><span class="badge-count">${s.pagos.length} reg.</span></td>
            <td>
                <button class="action-btn-sm btn-view" onclick="openDetailModal(${s.id})" title="Ver Ficha"><i class="fa-solid fa-eye"></i> Ver</button>
                <button class="action-btn-sm btn-edit-sub" onclick="openStudentModal(${s.id})" title="Editar"><i class="fa-solid fa-pen"></i> Editar</button>
                <button class="action-btn-sm btn-pay-sub" onclick="openPayModal(${s.id})" title="Registrar Pago"><i class="fa-solid fa-credit-card"></i> Pago</button>
                <button class="action-btn-sm btn-del-sub" onclick="deleteStudent(${s.id})" title="Eliminar"><i class="fa-solid fa-trash"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function getFinancialAuditStudents() {
    const generation = document.getElementById('audit-generation')?.value || 'todas';
    const career = document.getElementById('audit-career')?.value || 'todas';
    const group = document.getElementById('audit-group')?.value || 'todos';
    const status = document.getElementById('audit-status')?.value || 'todos';
    const query = (document.getElementById('audit-search')?.value || '').trim().toLocaleLowerCase('es-MX');

    return studentsDB.filter(student => {
        const accountStatus = calculateStudentAccountState(student);
        const searchFields = [
            student.nombre, student.matricula, getStudentGeneration(student), getStudentGroup(student),
            ...student.pagos.map(payment => payment.folio)
        ].join(' ').toLocaleLowerCase('es-MX');
        const matchesGeneration = generation === 'todas' || getStudentGeneration(student) === generation;
        const matchesCareer = career === 'todas' || normalizeCarrera(student.carrera) === career;
        const matchesGroup = group === 'todos' || getStudentGroup(student) === group;
        const matchesStatus = status === 'todos' ||
            (status === 'al_corriente' && accountStatus === 'al_corriente') ||
            (status === 'adeudo' && ['adeudo', 'pendiente', 'vencido', 'convenio'].includes(accountStatus)) ||
            (status === 'beca' && Number(student.porcentajeBeca) > 0) ||
            (status === 'baja' && accountStatus === 'baja');
        return matchesGeneration && matchesCareer && matchesGroup && matchesStatus && searchFields.includes(query);
    });
}

function renderFinancialAudit() {
    const body = document.getElementById('financial-audit-body');
    if (!body) return;
    const filteredStudents = getFinancialAuditStudents();
    const totalCollected = filteredStudents.reduce((sum, student) => sum + getStudentTotalPaid(student), 0);
    const totalDebt = filteredStudents.reduce((sum, student) => sum + calculateStudentBalance(student), 0);
    const collectionRate = totalCollected + totalDebt > 0 ? totalCollected / (totalCollected + totalDebt) * 100 : 0;

    document.getElementById('audit-total-collected').textContent = fmtMXN(totalCollected);
    document.getElementById('audit-total-debt').textContent = fmtMXN(totalDebt);
    document.getElementById('audit-student-count').textContent = filteredStudents.length;
    document.getElementById('audit-collection-rate').textContent = `${collectionRate.toFixed(1)}%`;
    const selectedGeneration = document.getElementById('audit-generation').value;
    document.getElementById('audit-generation-caption').textContent = selectedGeneration === 'todas'
        ? 'En las generaciones seleccionadas' : `Generación ${selectedGeneration.replace('-', ' - ')}`;
    document.getElementById('audit-result-summary').textContent =
        `Mostrando ${filteredStudents.length} de ${studentsDB.length} alumnos`;

    if (!filteredStudents.length) {
        body.innerHTML = '<tr><td colspan="9" class="finance-audit-empty">No hay alumnos que coincidan con los filtros.</td></tr>';
        return;
    }
    body.innerHTML = filteredStudents.map(student => {
        const balance = calculateStudentBalance(student);
        const paid = getStudentTotalPaid(student);
        const accountStatus = calculateStudentAccountState(student);
        const status = accountStatus === 'al_corriente'
            ? ['Al corriente', 'finance-status-paid']
            : accountStatus === 'baja'
                ? ['Baja financiera', 'finance-status-overdue']
                : accountStatus === 'convenio'
                    ? ['Convenio / prórroga', 'finance-status-agreement']
                : accountStatus === 'vencido'
                    ? ['Con adeudo', 'finance-status-overdue']
                    : ['Con adeudo', 'finance-status-pending'];
        const scholarship = Number(student.porcentajeBeca) >= 100
            ? `<span class="finance-state-badge finance-status-scholarship">Beca 100%</span>`
            : Number(student.porcentajeBeca) > 0
                ? `${student.porcentajeBeca}% · ${escapeFinanceHtml(normalizeCategoriaBeca(student.categoriaBeca))}`
                : 'Sin beca';
        const initials = student.nombre.trim().split(/\s+/).slice(0, 2).map(part => part[0]).join('').toLocaleUpperCase('es-MX');
        return `<tr>
            <td><span class="finance-generation-badge">Gen ${escapeFinanceHtml(getStudentGeneration(student).replace('-', ' - '))}</span></td>
            <td><strong>${escapeFinanceHtml(student.matricula)}</strong></td>
            <td><span class="finance-audit-student"><span class="finance-audit-avatar">${escapeFinanceHtml(initials)}</span><strong>${escapeFinanceHtml(student.nombre)}</strong></span></td>
            <td><span class="finance-career">${escapeFinanceHtml(normalizeCarrera(student.carrera))}</span><small>${escapeFinanceHtml(getStudentGroup(student))}</small></td>
            <td>${scholarship}</td>
            <td><strong class="finance-amount-paid">${fmtMXN(paid)}</strong></td>
            <td><strong class="${balance > 0 ? 'finance-amount-debt' : 'finance-amount-zero'}">${fmtMXN(balance)}</strong></td>
            <td><span class="finance-state-badge ${status[1]}">${status[0]}</span></td>
            <td class="finance-audit-row-actions"><button type="button" class="teacher-button teacher-button-primary teacher-button-small" onclick="openDetailModal(${student.id})"><i class="fa-solid fa-eye"></i> Ver historial</button><button type="button" class="teacher-button teacher-button-light teacher-button-small" onclick="openPayModal(${student.id})"><i class="fa-solid fa-credit-card"></i> Registrar pago</button></td>
        </tr>`;
    }).join('');
}

function exportFinancialAuditCsv() {
    const students = getFinancialAuditStudents();
    const rows = [
        ['Generación', 'Matrícula', 'Alumno', 'Carrera', 'Grupo', 'Beca', 'Cobrado total', 'Adeudo histórico', 'Estado general', 'Folios'],
        ...students.map(student => [
            getStudentGeneration(student), student.matricula, student.nombre, normalizeCarrera(student.carrera),
            getStudentGroup(student), `${student.porcentajeBeca || 0}% ${normalizeCategoriaBeca(student.categoriaBeca)}`,
            getStudentTotalPaid(student).toFixed(2), calculateStudentBalance(student).toFixed(2),
            getAccountStatusLabel(calculateStudentAccountState(student)).label, student.pagos.map(payment => payment.folio).join(' | ')
        ])
    ];
    const csv = '\uFEFF' + rows.map(row => row.map(value => `"${String(value ?? '').replace(/"/g, '""')}"`).join(',')).join('\r\n');
    downloadFinanceFile(csv, 'text/csv;charset=utf-8', 'historial-financiero-global.csv');
}

function exportGenerationalBalancePdf() {
    if (!window.jspdf?.jsPDF) {
        alert('No se pudo exportar el balance porque la librería de PDF no está disponible.');
        return;
    }
    const students = getFinancialAuditStudents();
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });
    const generation = document.getElementById('audit-generation')?.value || 'todas';
    doc.setFillColor(30, 58, 138);
    doc.rect(0, 0, 297, 25, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(16);
    doc.text('Balance generacional · Auditoría financiera', 14, 16);
    doc.setTextColor(30, 41, 59);
    doc.setFontSize(10);
    doc.text(`Generación: ${generation} · Alumnos: ${students.length} · Fecha: ${new Date().toLocaleDateString('es-MX')}`, 14, 34);
    let y = 45;
    doc.setFont(undefined, 'bold');
    doc.text('Generación / Matrícula / Alumno', 14, y);
    doc.text('Carrera / Grupo', 108, y);
    doc.text('Cobrado', 205, y);
    doc.text('Adeudo', 245, y);
    doc.setFont(undefined, 'normal');
    y += 8;
    students.forEach(student => {
        if (y > 190) { doc.addPage(); y = 18; }
        doc.text(`${getStudentGeneration(student)} · ${student.matricula} · ${student.nombre}`.slice(0, 55), 14, y);
        doc.text(`${normalizeCarrera(student.carrera).slice(0, 34)} · ${getStudentGroup(student)}`, 108, y);
        doc.text(fmtMXN(getStudentTotalPaid(student)), 205, y);
        doc.text(fmtMXN(calculateStudentBalance(student)), 245, y);
        y += 7;
    });
    const collected = students.reduce((sum, student) => sum + getStudentTotalPaid(student), 0);
    const debt = students.reduce((sum, student) => sum + calculateStudentBalance(student), 0);
    if (y > 185) { doc.addPage(); y = 18; }
    doc.setFont(undefined, 'bold');
    doc.text(`Total recaudado: ${fmtMXN(collected)}     Adeudo total: ${fmtMXN(debt)}`, 14, y + 6);
    doc.save(`balance-generacional-${generation}.pdf`);
}

function downloadFinanceFile(content, type, fileName) {
    const url = URL.createObjectURL(new Blob([content], { type }));
    const link = document.createElement('a');
    link.href = url;
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

// --- FILTRADO DE TABLA ---
function filterByStatus(status) {
    currentFilterStatus = status;
    document.querySelectorAll('.filter-pills-bar .pill-btn').forEach(btn => btn.classList.remove('active'));

    const activeBtnMap = {
        todos: 'pill-todos',
        al_corriente: 'pill-corriente',
        adeudo: 'pill-adeudo',
        pendiente: 'pill-pendiente',
        vencido: 'pill-vencido',
        baja: 'pill-baja'
    };
    if(document.getElementById(activeBtnMap[status])) {
        document.getElementById(activeBtnMap[status]).classList.add('active');
    }

    filterStudentsTable();
}

function filterStudentsTable() {
    const searchVal = document.getElementById('search-student')?.value.toLowerCase() || '';
    const carreraVal = document.getElementById('filter-carrera')?.value || 'todas';
    const cuatrimestreVal = document.getElementById('filter-cuatrimestre')?.value || 'todos';

    const filtered = studentsDB.filter(s => {
        const matchesSearch = s.nombre.toLowerCase().includes(searchVal) || s.matricula.toLowerCase().includes(searchVal);
        const matchesStatus = currentFilterStatus === 'todos' || s.estado === currentFilterStatus;
        const matchesCarrera = carreraVal === 'todas' || normalizeCarrera(s.carrera) === normalizeCarrera(carreraVal);
        const matchesCuatrimestre = cuatrimestreVal === 'todos' || (s.cuatrimestre || '2026-1') === cuatrimestreVal;

        return matchesSearch && matchesStatus && matchesCarrera && matchesCuatrimestre;
    });

    renderStudentsTable(filtered);
    renderFinancialAudit();
}

// --- CÁLCULO DINÁMICO DE CUOTA REAL EN MODAL ---
function calculateRealFees() {
    const baseCol = parseFloat(document.getElementById('st-cuota-base-col')?.value) || 0;
    const pct = parseFloat(document.getElementById('st-porcentaje-beca').value) || 0;

    const realCol = getRealFee(baseCol, pct);

    const realColInput = document.getElementById('st-cuota-real-col');
    if (realColInput) realColInput.value = realCol.toFixed(2);
}

// --- MODAL DE CREAR / EDITAR ALUMNO ---
function openStudentModal(id = null) {
    const form = document.getElementById('student-form');
    if (form) form.reset();

    if (id) {
        const student = studentsDB.find(s => s.id === id);
        if (student) {
            document.getElementById('student-modal-title').textContent = 'Editar Alumno';
            document.getElementById('st-id').value = student.id;
            document.getElementById('st-matricula').value = student.matricula;
            document.getElementById('st-nombre').value = student.nombre;
            document.getElementById('st-email').value = student.email;
            document.getElementById('st-telefono').value = student.telefono;
            document.getElementById('st-carrera').value = normalizeCarrera(student.carrera);
            document.getElementById('st-cuatrimestre').value = student.cuatrimestre || '2026-1';
            document.getElementById('st-estado').value = student.estado;
            document.getElementById('st-saldo-pendiente').value = calculateStudentBalance(student).toFixed(2);
            document.getElementById('st-categoria-beca').value = normalizeCategoriaBeca(student.categoriaBeca);
            document.getElementById('st-porcentaje-beca').value = student.porcentajeBeca;
            document.getElementById('st-cuota-base-col').value = student.cuotaBaseCol;
        }
    } else {
        document.getElementById('student-modal-title').textContent = 'Registrar Nuevo Alumno';
        document.getElementById('st-id').value = '';
        document.getElementById('st-cuatrimestre').value = '2026-1';
        document.getElementById('st-estado').value = 'adeudo';
        document.getElementById('st-saldo-pendiente').value = '0.00';
    }

    calculateRealFees();
    document.getElementById('student-modal').classList.add('active');
}

function closeStudentModal() {
    document.getElementById('student-modal').classList.remove('active');
}

function saveStudentForm(e) {
    e.preventDefault();
    const id = document.getElementById('st-id').value;
    const existingStudent = id ? studentsDB.find(s => s.id == id) : null;

    const studentData = {
        matricula: document.getElementById('st-matricula').value,
        nombre: document.getElementById('st-nombre').value,
        generacion: existingStudent?.generacion || getStudentGeneration({ matricula: document.getElementById('st-matricula').value }),
        grupo: existingStudent?.grupo || '1º A',
        email: document.getElementById('st-email').value,
        telefono: document.getElementById('st-telefono').value,
        carrera: normalizeCarrera(document.getElementById('st-carrera').value),
        cuatrimestre: document.getElementById('st-cuatrimestre').value || '2026-1',
        estado: document.getElementById('st-estado').value,
        saldoPendiente: Math.max(0, parseFloat(document.getElementById('st-saldo-pendiente').value) || 0),
        categoriaBeca: normalizeCategoriaBeca(document.getElementById('st-categoria-beca').value),
        porcentajeBeca: parseFloat(document.getElementById('st-porcentaje-beca').value) || 0,
        cuotaBaseInsc: existingStudent?.cuotaBaseInsc || 2500,
        cuotaBaseCol: parseFloat(document.getElementById('st-cuota-base-col').value) || 0
    };

    if (id) {
        const index = studentsDB.findIndex(s => s.id == id);
        studentsDB[index] = { ...studentsDB[index], ...studentData };
    } else {
        const newId = studentsDB.length > 0 ? Math.max(...studentsDB.map(s => s.id)) + 1 : 1;
        studentsDB.push({ id: newId, ...studentData, pagos: [] });
    }

    closeStudentModal();
    updateKPIs();
    filterStudentsTable();
}

// --- MODAL DE REGISTRO DE PAGO ---
function openPayModal(studentId) {
    const student = studentsDB.find(s => s.id === studentId);
    if (!student) return;

    document.getElementById('pay-student-id').value = student.id;
    document.getElementById('pay-student-name').value = `${student.nombre} (${student.matricula})`;
    document.getElementById('pay-cuatrimestre').value = student.cuatrimestre || '2026-1';
    document.getElementById('pay-folio').value = `FAC-${student.cuatrimestre || '2026-1'}-${String(Math.floor(1000 + Math.random() * 9000))}`;
    document.getElementById('pay-receipt-pdf').value = '';
    document.getElementById('pay-receipt-pdf-status').textContent = 'Adjunta el comprobante digital en formato PDF (máximo 5 MB).';

    autoFillPayAmount();
    const payModal = document.getElementById('pay-modal');
    payModal.style.zIndex = document.getElementById('detail-modal')?.classList.contains('active') ? '2001' : '';
    payModal.classList.add('active');
    syncFinanceModalScrollLock();
}

function autoFillPayAmount() {
    const studentId = document.getElementById('pay-student-id').value;
    const student = studentsDB.find(s => s.id == studentId);
    if (!student) return;

    const concepto = document.getElementById('pay-concepto').value;
    let base = concepto === 'Inscripción' ? student.cuotaBaseInsc : student.cuotaBaseCol;
    let realFee = getRealFee(base, student.porcentajeBeca);

    document.getElementById('pay-monto').value = realFee.toFixed(2);
}

function closePayModal() {
    const payModal = document.getElementById('pay-modal');
    payModal.classList.remove('active');
    payModal.style.zIndex = '';
    syncFinanceModalScrollLock();
}

function updatePaymentReceiptFileName(input) {
    const file = input.files?.[0];
    const status = document.getElementById('pay-receipt-pdf-status');
    if (status && file) {
        status.textContent = isValidPaymentReceiptPdf(file)
            ? `PDF seleccionado: ${file.name}`
            : 'El comprobante debe ser un archivo PDF de máximo 5 MB.';
    }
}

function savePaymentForm(e) {
    e.preventDefault();
    const studentId = document.getElementById('pay-student-id').value;
    const student = studentsDB.find(s => s.id == studentId);
    if (!student) return;

    const receiptInput = document.getElementById('pay-receipt-pdf');
    const receiptFile = receiptInput.files?.[0];
    if (receiptFile && !isValidPaymentReceiptPdf(receiptFile)) {
        alert('El comprobante debe ser un archivo PDF de máximo 5 MB.');
        receiptInput.focus();
        return;
    }
    const receiptUrl = receiptFile ? URL.createObjectURL(receiptFile) : '';
    const payCuatrimestre = document.getElementById('pay-cuatrimestre').value || student.cuatrimestre || '2026-1';
    const folio = document.getElementById('pay-folio').value || `FAC-${payCuatrimestre}-${String(Math.floor(1000 + Math.random() * 9000))}`;

    const newPayment = {
        folio,
        concepto: document.getElementById('pay-concepto').value,
        monto: parseFloat(document.getElementById('pay-monto').value),
        montoBase: document.getElementById('pay-concepto').value === 'Inscripción'
            ? student.cuotaBaseInsc : student.cuotaBaseCol,
        descuento: (document.getElementById('pay-concepto').value === 'Inscripción'
            ? student.cuotaBaseInsc : student.cuotaBaseCol) * (Number(student.porcentajeBeca || 0) / 100),
        periodo: payCuatrimestre,
        fecha: new Date().toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }),
        metodo: document.getElementById('pay-metodo').value,
        comprobante: document.getElementById('pay-comprobante').value,
        receiptPdfName: receiptFile?.name || '',
        receiptPdfUrl: receiptUrl,
        registradoPor: "Admin Sistema"
    };

    const balanceBeforePayment = calculateStudentBalance(student);
    student.pagos.push(newPayment);
    student.saldoPendiente = Math.max(0, balanceBeforePayment - newPayment.monto);
    student.cuatrimestre = payCuatrimestre;
    student.estado = calculateStudentAccountState(student);

    closePayModal();
    updateKPIs();
    filterStudentsTable();

    // Si la ficha de detalle está abierta, refrescarla
    const detailModal = document.getElementById('detail-modal');
    if (detailModal && detailModal.classList.contains('active')) {
        openDetailModal(student.id);
    }
}

function isValidPaymentReceiptPdf(file) {
    return file.name.toLocaleLowerCase('es-MX').endsWith('.pdf') &&
        (!file.type || file.type === 'application/pdf') &&
        file.size <= 5 * 1024 * 1024;
}

function uploadPaymentReceiptPdf(input, studentId, paymentIndex) {
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    if (!isValidPaymentReceiptPdf(file)) {
        alert('El comprobante debe ser un archivo PDF de máximo 5 MB.');
        return;
    }
    const student = studentsDB.find(item => item.id === studentId);
    const payment = student?.pagos[paymentIndex];
    if (!student || !payment) {
        alert('No se encontró el pago al que deseas adjuntar el comprobante.');
        return;
    }
    if (payment.receiptPdfUrl) URL.revokeObjectURL(payment.receiptPdfUrl);
    payment.receiptPdfName = file.name;
    payment.receiptPdfUrl = URL.createObjectURL(file);
    renderFinancialTermTimeline(student);
}

function openPaymentReceiptPdf(studentId, paymentIndex) {
    const payment = studentsDB.find(item => item.id === studentId)?.pagos[paymentIndex];
    if (!payment?.receiptPdfUrl) {
        alert('Este pago todavía no tiene un comprobante PDF adjunto.');
        return;
    }
    const link = document.createElement('a');
    link.href = payment.receiptPdfUrl;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.click();
}

// --- MODAL DE DETALLE / FICHA DE PAGOS (IMAGEN 5) ---
function getAccountStatusLabel(status) {
    const statusMap = {
        al_corriente: { class: 'status-activo', label: 'Al corriente' },
        adeudo: { class: 'status-pendiente', label: 'Adeudo' },
        pendiente: { class: 'status-pendiente', label: 'Pendiente' },
        vencido: { class: 'status-atrasado', label: 'Vencido' },
        convenio: { class: 'finance-status-agreement', label: 'Convenio / prórroga' },
        baja: { class: 'status-inactivo', label: 'Baja' }
    };
    return statusMap[status] || statusMap.adeudo;
}

function calculateStudentAccountState(student) {
    if (!student || student.estado === 'baja') return 'baja';
    const balance = calculateStudentBalance(student);
    if (balance <= 0) return 'al_corriente';
    if (student.estado === 'convenio') return 'convenio';
    if (student.estado === 'vencido') return 'vencido';
    if (student.pagos.length === 0) return 'adeudo';
    return 'pendiente';
}

function getPaymentDueDate(payment) {
    if (payment.fechaVencimiento) return payment.fechaVencimiento;
    const year = String(payment.periodo || '').slice(0, 4) || '—';
    const dueMonths = {
        'Inscripción': 'Ene', 'Reinscripción': 'Ene',
        'Mes 1': 'Feb', 'Mes 2': 'Mar', 'Mes 3': 'Abr', 'Mes 4': 'May',
        Enero: 'Ene', Febrero: 'Feb', Marzo: 'Mar', Abril: 'Abr', Mayo: 'May',
        Junio: 'Jun', Julio: 'Jul', Agosto: 'Ago', Septiembre: 'Sep', Octubre: 'Oct',
        Noviembre: 'Nov', Diciembre: 'Dic'
    };
    const month = dueMonths[payment.concepto] || 'Ene';
    return `05 ${month} ${year}`;
}

function getPaymentBaseAmount(student, payment) {
    if (Number.isFinite(Number(payment.montoBase))) return Number(payment.montoBase);
    const isEnrollment = /inscripci[oó]n|reinscripci[oó]n/i.test(payment.concepto);
    const fee = isEnrollment ? Number(student.cuotaBaseInsc) : Number(student.cuotaBaseCol);
    return fee > 0 ? fee : Number(payment.monto) / Math.max(0.01, 1 - (Number(student.porcentajeBeca || 0) / 100));
}

function getFinancialTerms(student) {
    const termsByPeriod = new Map();
    student.pagos.forEach((payment, index) => {
        const period = payment.periodo || student.cuatrimestre || 'Sin cuatrimestre';
        if (!termsByPeriod.has(period)) termsByPeriod.set(period, { period, payments: [], pendingAmount: 0 });
        termsByPeriod.get(period).payments.push({ ...payment, sourceIndex: index });
    });
    const currentPeriod = student.cuatrimestre || '2026-1';
    const currentTerm = termsByPeriod.get(currentPeriod) || { period: currentPeriod, payments: [], pendingAmount: 0 };
    currentTerm.pendingAmount = calculateStudentBalance(student);
    if (currentTerm.pendingAmount > 0 && !termsByPeriod.has(currentPeriod)) termsByPeriod.set(currentPeriod, currentTerm);
    return [...termsByPeriod.values()].sort((left, right) => left.period.localeCompare(right.period));
}

function renderFinancialTermTimeline(student) {
    const timeline = document.getElementById('finance-term-timeline');
    if (!timeline) return;
    const terms = getFinancialTerms(student);
    if (!terms.length) {
        timeline.innerHTML = '<p class="finance-timeline-empty">Todavía no hay movimientos financieros registrados.</p>';
        return;
    }
    timeline.innerHTML = terms.map((term, termIndex) => {
        const rows = term.payments.map(payment => {
            const base = getPaymentBaseAmount(student, payment);
            const amount = Number(payment.monto) || 0;
            const discount = payment.descuento !== undefined
                ? Number(payment.descuento) || 0
                : base * (Number(student.porcentajeBeca || 0) / 100);
            const status = payment.estado === 'condonado' || Number(student.porcentajeBeca) >= 100
                ? ['Condonado / beca 100%', 'finance-status-scholarship']
                : payment.estado === 'convenio'
                    ? ['Convenio / prórroga', 'finance-status-agreement']
                    : ['Pagado', 'finance-status-paid'];
            return `<tr>
                <td>${escapeFinanceHtml(payment.concepto)}</td><td>${fmtMXN(base)}</td>
                <td>${discount > 0 ? `${fmtMXN(discount)} (${Number(student.porcentajeBeca || 0)}%)` : '—'}</td>
                <td><strong class="finance-amount-paid">${fmtMXN(amount)}</strong></td>
                <td>${escapeFinanceHtml(getPaymentDueDate(payment))}</td><td>${escapeFinanceHtml(payment.fecha || '—')}</td>
                <td><strong>${escapeFinanceHtml(payment.folio || '—')}</strong></td>
                <td>${escapeFinanceHtml(payment.metodo || '—')}</td>
                <td><span class="finance-state-badge ${status[1]}">${status[0]}</span></td>
                <td>
                    <div class="finance-receipt-cell">
                        ${payment.comprobante ? `<small>${escapeFinanceHtml(payment.comprobante)}</small>` : ''}
                        ${payment.receiptPdfUrl
                            ? `<button class="finance-receipt-button" type="button" onclick="openPaymentReceiptPdf(${student.id}, ${payment.sourceIndex})" title="Abrir comprobante PDF"><i class="fa-solid fa-file-pdf"></i> ${escapeFinanceHtml(payment.receiptPdfName || 'Ver comprobante PDF')}</button>`
                            : '<small class="finance-receipt-missing">Sin PDF adjunto</small>'}
                        <label class="finance-receipt-upload" for="payment-pdf-${student.id}-${payment.sourceIndex}"><i class="fa-solid fa-upload"></i> ${payment.receiptPdfUrl ? 'Cambiar PDF' : 'Subir PDF'}</label>
                        <input class="finance-receipt-file" type="file" id="payment-pdf-${student.id}-${payment.sourceIndex}" accept=".pdf,application/pdf" onchange="uploadPaymentReceiptPdf(this, ${student.id}, ${payment.sourceIndex})">
                    </div>
                </td>
                <td><button class="action-btn btn-delete" type="button" onclick="deletePayment(${student.id}, ${payment.sourceIndex})" title="Eliminar pago"><i class="fa-solid fa-trash"></i></button></td>
            </tr>`;
        });
        if (term.pendingAmount > 0) {
            const base = term.pendingAmount / Math.max(0.01, 1 - (Number(student.porcentajeBeca || 0) / 100));
            const status = student.estado === 'vencido'
                ? ['Vencido', 'finance-status-overdue']
                : student.estado === 'convenio'
                    ? ['Convenio / prórroga', 'finance-status-agreement']
                    : ['Pendiente', 'finance-status-pending'];
            rows.push(`<tr>
                <td>Saldo pendiente del cuatrimestre</td><td>${fmtMXN(base)}</td>
                <td>${Number(student.porcentajeBeca) > 0 ? `${fmtMXN(base - term.pendingAmount)} (${student.porcentajeBeca}%)` : '—'}</td>
                <td><strong class="finance-amount-debt">${fmtMXN(term.pendingAmount)}</strong></td>
                <td>Vencimientos del cuatrimestre</td><td>—</td><td>Sin recibo</td><td>—</td>
                <td><span class="finance-state-badge ${status[1]}">${status[0]}</span></td><td>—</td><td>—</td>
            </tr>`);
        }
        return `<details class="finance-term-block" ${termIndex === terms.length - 1 ? 'open' : ''}>
            <summary><span><i class="fa-solid fa-calendar-days"></i> Cuatrimestre ${escapeFinanceHtml(term.period)}</span><small>${term.payments.length} recibos · Adeudo ${fmtMXN(term.pendingAmount)}</small></summary>
            <div class="table-container finance-term-table-wrap"><table class="data-table finance-term-table">
                <thead><tr><th>Concepto</th><th>Monto base</th><th>Descuento / beca</th><th>Monto cobrado real</th><th>Vencimiento</th><th>Fecha de pago</th><th>Folio / transacción</th><th>Método de pago</th><th>Estatus</th><th>Comprobante</th><th>Acciones</th></tr></thead>
                <tbody>${rows.length ? rows.join('') : '<tr><td colspan="11">Sin cargos registrados para este cuatrimestre.</td></tr>'}</tbody>
            </table></div>
        </details>`;
    }).join('');
}

function openDetailModal(studentId) {
    const student = studentsDB.find(s => s.id === studentId);
    if (!student) return;

    const initials = student.nombre.trim().split(/\s+/).slice(0, 2).map(n => n[0]).join('').toLocaleUpperCase('es-MX');
    const saldo = calculateStudentBalance(student);
    document.getElementById('dt-breadcrumb-name').textContent = student.nombre;
    document.getElementById('dt-avatar').textContent = initials;
    document.getElementById('dt-nombre').textContent = student.nombre;
    document.getElementById('dt-subinfo').textContent = `${student.matricula} · Gen ${getStudentGeneration(student).replace('-', ' - ')} · ${normalizeCarrera(student.carrera)} · ${getStudentGroup(student)}`;
    document.getElementById('dt-scholarship').textContent = Number(student.porcentajeBeca) > 0
        ? `${student.porcentajeBeca}% Beca ${normalizeCategoriaBeca(student.categoriaBeca)}`
        : 'Sin beca asignada';
    document.getElementById('dt-saldo').textContent = fmtMXN(getStudentTotalPaid(student));
    document.getElementById('dt-debt-summary').textContent = `Adeudo histórico: ${fmtMXN(saldo)}`;

    const badgeStatusEl = document.getElementById('dt-badge-status');
    const accountStatusEl = document.getElementById('dt-estado-cuenta');
    const accountState = calculateStudentAccountState(student);
    const badge = getAccountStatusLabel(accountState);
    badgeStatusEl.className = `badge ${badge.class}`;
    badgeStatusEl.textContent = badge.label;
    if (accountStatusEl) {
        accountStatusEl.value = accountState;
        accountStatusEl.onchange = (event) => {
            const nextStatus = event.target.value;
            const studentToUpdate = studentsDB.find(s => s.id === studentId);
            if (!studentToUpdate) return;
            studentToUpdate.estado = nextStatus;
            updateKPIs();
            filterStudentsTable();
            openDetailModal(studentId);
        };
    }

    document.getElementById('btn-edit-from-detail').onclick = () => { closeDetailModal(); openStudentModal(student.id); };
    document.getElementById('btn-pay-from-detail').onclick = () => { openPayModal(student.id); };
    document.getElementById('btn-baja-from-detail').onclick = () => { registerBaja(student.id); };
    document.getElementById('btn-download-pdf').onclick = () => { downloadStudentPaymentsPDF(student.id); };

    renderFinancialTermTimeline(student);
    document.getElementById('dt-total-pagado').textContent = fmtMXN(getStudentTotalPaid(student));
    document.getElementById('dt-total-adeudo').textContent = fmtMXN(saldo);
    document.getElementById('detail-modal').classList.add('active');
    syncFinanceModalScrollLock();
}

function downloadStudentPaymentsPDF(studentId) {
    const student = studentsDB.find(s => s.id === studentId);
    if (!student) return;

    if (!window.jspdf || !window.jspdf.jsPDF) {
        alert('La librería de PDF no está disponible.');
        return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    doc.setFillColor(13, 138, 188);
    doc.rect(0, 0, 210, 20, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(18);
    doc.text('Estado de cuenta financiero', 14, 12);

    doc.setTextColor(0, 0, 0);
    doc.setFontSize(11);
    doc.text(`Alumno: ${student.nombre}`, 14, 30);
    doc.text(`Matrícula: ${student.matricula}`, 14, 38);
    doc.text(`Generación: ${getStudentGeneration(student)} · Grupo: ${getStudentGroup(student)}`, 14, 46);
    doc.text(`Carrera: ${normalizeCarrera(student.carrera)}`, 14, 54);
    doc.text(`Beca: ${student.porcentajeBeca || 0}% · ${normalizeCategoriaBeca(student.categoriaBeca)}`, 14, 62);
    doc.text(`Estado: ${getAccountStatusLabel(calculateStudentAccountState(student)).label}`, 14, 70);

    let y = 84;
    getFinancialTerms(student).forEach(term => {
        if (y > 260) { doc.addPage(); y = 20; }
        doc.setFont(undefined, 'bold');
        doc.text(`Cuatrimestre ${term.period}`, 14, y);
        doc.setFont(undefined, 'normal');
        y += 7;
        term.payments.forEach(payment => {
            if (y > 275) { doc.addPage(); y = 20; }
            doc.setFontSize(9);
            doc.text(`${payment.concepto} · ${payment.folio || 'Sin folio'} · ${payment.fecha || '—'} · ${payment.metodo || '—'}`, 14, y);
            doc.text(fmtMXN(payment.monto || 0), 157, y);
            y += 7;
        });
        if (term.pendingAmount > 0) {
            if (y > 275) { doc.addPage(); y = 20; }
            doc.setTextColor(185, 28, 28);
            doc.text('Saldo pendiente del cuatrimestre', 14, y);
            doc.text(fmtMXN(term.pendingAmount), 157, y);
            doc.setTextColor(0, 0, 0);
            y += 7;
        }
    });

    const totalPagado = getStudentTotalPaid(student);
    doc.setFontSize(12);
    doc.setFont(undefined, 'bold');
    doc.text(`Total pagado: ${fmtMXN(totalPagado)}     Adeudo histórico: ${fmtMXN(calculateStudentBalance(student))}`, 14, Math.min(y + 12, 285));
    doc.save(`estado-cuenta-${student.matricula}.pdf`);
}

function closeDetailModal() {
    document.getElementById('detail-modal').classList.remove('active');
    syncFinanceModalScrollLock();
}

function registerBaja(studentId) {
    if (confirm('¿Estás seguro de registrar la BAJA de este alumno?')) {
        const student = studentsDB.find(s => s.id === studentId);
        if (student) {
            student.estado = 'baja';
            updateKPIs();
            filterStudentsTable();
            closeDetailModal();
        }
    }
}

function deletePayment(studentId, paymentIndex) {
    if (confirm('¿Desea eliminar este recibo de pago?')) {
        const student = studentsDB.find(s => s.id === studentId);
        if (student) {
            const removedPayment = student.pagos[paymentIndex];
            if (Number.isFinite(student.saldoPendiente) && removedPayment) {
                student.saldoPendiente += removedPayment.monto;
            }
            if (removedPayment?.receiptPdfUrl) URL.revokeObjectURL(removedPayment.receiptPdfUrl);
            student.pagos.splice(paymentIndex, 1);
            student.estado = calculateStudentAccountState(student);
            updateKPIs();
            filterStudentsTable();
            openDetailModal(studentId);
        }
    }
}

function deleteStudent(id) {
    if (confirm('¿Estás seguro de eliminar permanentemente a este alumno?')) {
        const studentToDelete = studentsDB.find(student => student.id === id);
        studentToDelete?.pagos.forEach(payment => {
            if (payment.receiptPdfUrl) URL.revokeObjectURL(payment.receiptPdfUrl);
        });
        studentsDB = studentsDB.filter(s => s.id !== id);
        updateKPIs();
        filterStudentsTable();
    }
}

document.addEventListener('click', event => {
    if (event.target?.id === 'pay-modal') closePayModal();
});

document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && document.getElementById('pay-modal')?.classList.contains('active')) {
        closePayModal();
    }
});