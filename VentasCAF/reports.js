document.addEventListener('DOMContentLoaded', function() {
    const shiftsTableBody = document.querySelector('#shifts-table tbody');
    const reportsContainer = document.querySelector('.reports-container');
    const reportsSummary = document.getElementById('reports-summary');
    const reportDetailsContainer = document.getElementById('report-details');
    const exportAllBtn = document.getElementById('export-all-btn');
    const totalShiftsCount = document.getElementById('total-shifts-count');

    let allShiftsData = []; // Store all shifts data for export

    function formatCurrency(value) {
        return '$' + new Intl.NumberFormat('es-CL').format(value);
    }

    function formatDateTime(value) {
        if (!value) return 'N/A';
        const dateValue = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(dateValue.getTime())) return value;
        return dateValue.toLocaleString('es-CL', { timeZone: 'America/Santiago' });
    }

    function fetchShifts() {
        fetch('get_shifts_api.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allShiftsData = data.data; // Save for export
                    shiftsTableBody.innerHTML = '';
                    if (totalShiftsCount) totalShiftsCount.textContent = String(allShiftsData.length);
                    allShiftsData.forEach(shift => {
                        const row = document.createElement('tr');
                        const statusClass = shift.status === 'open' ? 'open' : 'closed';
                        row.innerHTML = `
                            <td data-label="ID Turno">${shift.id}</td>
                            <td data-label="Usuario">${shift.user}</td>
                            <td data-label="Hora de Inicio">${formatDateTime(shift.start_time)}</td>
                            <td data-label="Hora de Fin">${formatDateTime(shift.end_time)}</td>
                            <td data-label="Venta Efectivo">${formatCurrency(shift.cash_sales)}</td>
                            <td data-label="Venta Transferencia">${formatCurrency(shift.transfer_sales)}</td>
                            <td data-label="Efectivo Final">${shift.final_cash ? formatCurrency(shift.final_cash) : 'N/A'}</td>
                            <td data-label="Estado"><span class="status-pill-table ${statusClass}">${shift.status}</span></td>
                            <td data-label="Acción">
                                <button class="view-report-btn" data-shift-id="${shift.id}">Ver Reporte</button>
                            </td>
                        `;
                        shiftsTableBody.appendChild(row);
                    });
                }
            });
    }

    function showListView() {
        if (reportsContainer) {
            reportsContainer.style.display = 'block';
        }
        if (reportsSummary) {
            reportsSummary.style.display = 'block';
        }
        if (reportDetailsContainer) {
            reportDetailsContainer.style.display = 'none';
            reportDetailsContainer.innerHTML = '';
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function showDetailView() {
        if (reportsContainer) {
            reportsContainer.style.display = 'none';
        }
        if (reportsSummary) {
            reportsSummary.style.display = 'none';
        }
        if (reportDetailsContainer) {
            reportDetailsContainer.style.display = 'block';
            reportDetailsContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    shiftsTableBody.addEventListener('click', function(event) {
        if (event.target.classList.contains('view-report-btn')) {
            const shiftId = event.target.dataset.shiftId;
            fetchReportDetails(shiftId);
        }
    });

    function fetchReportDetails(shiftId) {
        fetch(`get_shift_report_api.php?shift_id=${shiftId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const report = data.data;
                    reportDetailsContainer.innerHTML = `
                        <div class="report-details-head">
                            <h2>Reporte de Turno (ID: ${report.shift_id})</h2>
                            <div class="report-details-actions">
                                <button id="back-to-list-btn" class="btn btn-secondary" type="button">Volver al listado</button>
                                <button id="export-single-btn" class="btn btn-success" data-shift-id="${report.shift_id}" type="button">Exportar a Excel</button>
                            </div>
                        </div>
                        <p><strong>Usuario:</strong> ${report.username}</p>
                        <p><strong>Hora de Inicio:</strong> ${formatDateTime(report.start_time)}</p>
                        <p><strong>Hora de Fin:</strong> ${formatDateTime(report.end_time)}</p>
                        <p><strong>Estado:</strong> ${report.status}</p>
                        <hr>
                        <p><strong>Efectivo Inicial:</strong> ${formatCurrency(report.initial_cash)}</p>
                        <p><strong>Ventas Totales:</strong> ${formatCurrency(report.total_sales)}</p>
                        <p><strong>Gastos Totales:</strong> ${formatCurrency(report.total_expenses)}</p>
                        <p><strong>Efectivo Esperado:</strong> ${formatCurrency(report.expected_cash)}</p>
                        <p><strong>Efectivo Final:</strong> ${formatCurrency(report.final_cash)}</p>
                        <p><strong>Diferencia:</strong> ${formatCurrency(report.difference)}</p>
                    `;
                    showDetailView();

                    document.getElementById('back-to-list-btn').addEventListener('click', function() {
                        showListView();
                    });

                    document.getElementById('export-single-btn').addEventListener('click', function() {
                        exportSingleShift(report);
                    });
                } else {
                    alert(data.message);
                }
            });
    }

    function exportSingleShift(reportData) {
        const data = [
            ["Reporte de Turno"],
            ["ID Turno", reportData.shift_id],
            ["Usuario", reportData.username],
            ["Hora de Inicio", formatDateTime(reportData.start_time)],
            ["Hora de Fin", reportData.end_time ? formatDateTime(reportData.end_time) : "N/A"],
            ["Estado", reportData.status],
            [],
            ["Efectivo Inicial", reportData.initial_cash],
            ["Ventas Totales", reportData.total_sales],
            ["Gastos Totales", reportData.total_expenses],
            ["Efectivo Esperado", reportData.expected_cash],
            ["Efectivo Final", reportData.final_cash],
            ["Diferencia", reportData.difference]
        ];

        const ws = XLSX.utils.aoa_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Reporte de Turno");
        XLSX.writeFile(wb, `Reporte_Turno_${reportData.shift_id}.xlsx`);
    }

    exportAllBtn.addEventListener('click', function() {
        const data = allShiftsData.map(shift => ({
            "ID Turno": shift.id,
            "Usuario": shift.user,
            "Hora de Inicio": formatDateTime(shift.start_time),
            "Hora de Fin": shift.end_time ? formatDateTime(shift.end_time) : 'N/A',
            "Venta Efectivo": shift.cash_sales,
            "Venta Transferencia": shift.transfer_sales,
            "Efectivo Final": shift.final_cash,
            "Estado": shift.status
        }));

        const ws = XLSX.utils.json_to_sheet(data);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Todos los Turnos");
        XLSX.writeFile(wb, "Reporte_Todos_los_Turnos.xlsx");
    });

    fetchShifts();
    showListView();
});


