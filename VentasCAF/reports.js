document.addEventListener('DOMContentLoaded', function() {
    const shiftsTableBody = document.querySelector('#shifts-table tbody');
    const reportDetailsContainer = document.getElementById('report-details');
    const exportAllBtn = document.getElementById('export-all-btn');

    let allShiftsData = []; // Store all shifts data for export

    function formatCurrency(value) {
        return '$' + new Intl.NumberFormat('es-CL').format(value);
    }

    function fetchShifts() {
        fetch('get_shifts_api.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allShiftsData = data.data; // Save for export
                    shiftsTableBody.innerHTML = '';
                    allShiftsData.forEach(shift => {
                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td>${shift.id}</td>
                            <td>${shift.user}</td>
                            <td>${new Date(shift.start_time).toLocaleString()}</td>
                            <td>${shift.end_time ? new Date(shift.end_time).toLocaleString() : 'N/A'}</td>
                            <td>${formatCurrency(shift.cash_sales)}</td>
                            <td>${formatCurrency(shift.transfer_sales)}</td>
                            <td>${shift.final_cash ? formatCurrency(shift.final_cash) : 'N/A'}</td>
                            <td>${shift.status}</td>
                            <td>
                                <button class="view-report-btn" data-shift-id="${shift.id}">Ver Reporte</button>
                            </td>
                        `;
                        shiftsTableBody.appendChild(row);
                    });
                }
            });
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
                        <h2>Reporte de Turno (ID: ${report.shift_id})</h2>
                        <button id="export-single-btn" class="btn btn-success" data-shift-id="${report.shift_id}">Exportar a Excel</button>
                        <p><strong>Usuario:</strong> ${report.username}</p>
                        <p><strong>Hora de Inicio:</strong> ${new Date(report.start_time).toLocaleString()}</p>
                        <p><strong>Hora de Fin:</strong> ${report.end_time ? new Date(report.end_time).toLocaleString() : 'N/A'}</p>
                        <p><strong>Estado:</strong> ${report.status}</p>
                        <hr>
                        <p><strong>Efectivo Inicial:</strong> ${formatCurrency(report.initial_cash)}</p>
                        <p><strong>Ventas Totales:</strong> ${formatCurrency(report.total_sales)}</p>
                        <p><strong>Gastos Totales:</strong> ${formatCurrency(report.total_expenses)}</p>
                        <p><strong>Efectivo Esperado:</strong> ${formatCurrency(report.expected_cash)}</p>
                        <p><strong>Efectivo Final:</strong> ${formatCurrency(report.final_cash)}</p>
                        <p><strong>Diferencia:</strong> ${formatCurrency(report.difference)}</p>
                    `;
                    reportDetailsContainer.style.display = 'block';

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
            ["Hora de Inicio", new Date(reportData.start_time).toLocaleString()],
            ["Hora de Fin", reportData.end_time ? new Date(reportData.end_time).toLocaleString() : "N/A"],
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
            "Hora de Inicio": new Date(shift.start_time).toLocaleString(),
            "Hora de Fin": shift.end_time ? new Date(shift.end_time).toLocaleString() : 'N/A',
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
});


