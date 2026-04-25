document.addEventListener('DOMContentLoaded', function() {
    const shiftsTableBody = document.querySelector('#shifts-table tbody');
    const reportsContainer = document.querySelector('.reports-container');
    const reportsSummary = document.getElementById('reports-summary');
    const reportDetailsContainer = document.getElementById('report-details');
    const exportAllBtn = document.getElementById('export-all-btn');
    const totalShiftsCount = document.getElementById('total-shifts-count');

    let allShiftsData = []; // Store all shifts data for export

    function formatCurrency(value) {
        return '$' + new Intl.NumberFormat('es-CL').format(Number(value || 0));
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
        const button = event.target.closest('.view-report-btn');
        if (!button) return;
        const shiftId = button.dataset.shiftId;
        if (!shiftId) return;
        fetchReportDetails(shiftId);
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

    async function fetchDailyReportData() {
        const response = await fetch('get_daily_report_api.php', { cache: 'no-store' });
        return response.json();
    }

    function buildDailyXlsx(report) {
        const dateLabel = report.date || new Date().toISOString().slice(0, 10);
        const summary = report.summary || {};

        const rows = [];

        rows.push([`Resumen Diario — ${dateLabel}`]);
        rows.push([]);
        rows.push(['Resumen general del día']);
        rows.push(['Métrica', 'Valor']);
        rows.push(['Ventas brutas', summary.gross_sales || 0]);
        rows.push(['Ingreso neto final', summary.net_income || 0]);
        rows.push(['Devoluciones', summary.returns || 0]);
        rows.push(['Gastos operacionales', summary.operational_expenses || 0]);
        rows.push(['Total anulaciones', summary.voided_sales || 0]);
        rows.push(['Cantidad de transacciones', summary.transactions_count || 0]);
        rows.push([]);

        const shiftStart = rows.length + 1;
        rows.push(['Detalle de ventas por turno']);
        rows.push(['Turno', 'Vendedor', 'Cantidad', 'Monto']);
        (report.sales_by_shift || []).forEach(item => {
            rows.push([
                `#${item.shift_id}`,
                item.seller,
                item.sales_count,
                item.sales_amount
            ]);
        });
        rows.push([]);

        const salesStart = rows.length + 1;
        rows.push(['Ventas generales del día']);
        rows.push(['Hora', 'Monto', 'Vendedor']);
        (report.general_sales || []).forEach(item => {
            rows.push([formatDateTime(item.sale_time), item.amount, item.seller]);
        });
        rows.push([]);

        const voidedStart = rows.length + 1;
        rows.push(['Anulaciones del día']);
        rows.push(['Hora', 'Motivo', 'Monto', 'Vendedor']);
        (report.voided_sales || []).forEach(item => {
            rows.push([formatDateTime(item.sale_time), item.reason, item.amount, item.seller]);
        });
        rows.push([]);

        const topStart = rows.length + 1;
        rows.push(['Productos más vendidos']);
        rows.push(['Producto', 'Cantidad', 'Monto total']);
        (report.top_products || []).forEach(item => {
            rows.push([item.name, item.qty, item.total_amount]);
        });

        const ws = XLSX.utils.aoa_to_sheet(rows);

        ws['!cols'] = autoFitColumns(rows);

        const primaryHeader = {
            fill: { fgColor: { rgb: '7C3AED' } },
            font: { color: { rgb: 'FFFFFF' }, bold: true },
            alignment: { horizontal: 'center', vertical: 'center' }
        };

        const alternatingFill = { fill: { fgColor: { rgb: 'F5F3FF' } } };
        const amountFmt = '#,##0';

        styleRow(ws, 4, primaryHeader, 2);
        styleRow(ws, shiftStart + 1, primaryHeader, 4);
        styleRow(ws, salesStart + 1, primaryHeader, 3);
        styleRow(ws, voidedStart + 1, primaryHeader, 4);
        styleRow(ws, topStart + 1, primaryHeader, 3);

        applyAlternating(ws, 5, 10, 2, alternatingFill);
        applyAlternating(ws, shiftStart + 2, shiftStart + 1 + (report.sales_by_shift || []).length, 4, alternatingFill);
        applyAlternating(ws, salesStart + 2, salesStart + 1 + (report.general_sales || []).length, 3, alternatingFill);
        applyAlternating(ws, voidedStart + 2, voidedStart + 1 + (report.voided_sales || []).length, 4, alternatingFill);
        applyAlternating(ws, topStart + 2, topStart + 1 + (report.top_products || []).length, 3, alternatingFill);

        applyNumberFormat(ws, [
            { start: 5, end: 9, col: 2 },
            { start: shiftStart + 2, end: shiftStart + 1 + (report.sales_by_shift || []).length, col: 4 },
            { start: salesStart + 2, end: salesStart + 1 + (report.general_sales || []).length, col: 2 },
            { start: voidedStart + 2, end: voidedStart + 1 + (report.voided_sales || []).length, col: 3 },
            { start: topStart + 2, end: topStart + 1 + (report.top_products || []).length, col: 3 }
        ], amountFmt);

        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, `Diario_${dateLabel}`);
        XLSX.writeFile(wb, `Reporte_Diario_${dateLabel}.xlsx`, { bookType: 'xlsx', cellStyles: true });
    }

    function autoFitColumns(rows) {
        const maxCols = Math.max(...rows.map(r => r.length), 0);
        const widths = Array.from({ length: maxCols }, () => 10);
        rows.forEach(row => {
            row.forEach((value, colIndex) => {
                const len = String(value ?? '').length;
                widths[colIndex] = Math.max(widths[colIndex], Math.min(45, len + 2));
            });
        });
        return widths.map(w => ({ wch: w }));
    }

    function styleRow(ws, rowNumber, styleObj, totalCols) {
        for (let col = 1; col <= totalCols; col++) {
            const ref = XLSX.utils.encode_cell({ r: rowNumber - 1, c: col - 1 });
            if (!ws[ref]) ws[ref] = { t: 's', v: '' };
            ws[ref].s = { ...(ws[ref].s || {}), ...styleObj };
        }
    }

    function applyAlternating(ws, startRow, endRow, totalCols, styleObj) {
        for (let row = startRow; row <= endRow; row++) {
            if ((row - startRow) % 2 !== 0) continue;
            for (let col = 1; col <= totalCols; col++) {
                const ref = XLSX.utils.encode_cell({ r: row - 1, c: col - 1 });
                if (!ws[ref]) ws[ref] = { t: 's', v: '' };
                ws[ref].s = { ...(ws[ref].s || {}), ...styleObj };
            }
        }
    }

    function applyNumberFormat(ws, ranges, format) {
        ranges.forEach(range => {
            for (let row = range.start; row <= range.end; row++) {
                const ref = XLSX.utils.encode_cell({ r: row - 1, c: range.col - 1 });
                if (!ws[ref]) continue;
                ws[ref].z = format;
            }
        });
    }

    async function buildPdfPresentation(report) {
        const jspdfNs = window.jspdf;
        if (!jspdfNs || !jspdfNs.jsPDF) {
            throw new Error('PDF library not loaded');
        }

        const dateLabel = report.date || new Date().toISOString().slice(0, 10);
        const generatedAt = report.generated_at || new Date().toISOString().slice(0, 19).replace('T', ' ');
        const summary = report.summary || {};
        const shifts = report.sales_by_shift || [];
        const topProducts = (report.top_products || []).slice(0, 8);

        const pdf = new jspdfNs.jsPDF({
            orientation: 'landscape',
            unit: 'pt',
            format: 'letter'
        });

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();
        const margin = 28;
        const contentWidth = pageWidth - margin * 2;
        const primary = [124, 58, 237];

        const logoDataUrl = await loadImageAsDataUrl('img/logo.png').catch(() => null);

        const canUseGState = typeof pdf.setGState === 'function' && typeof pdf.GState === 'function';

        if (logoDataUrl) {
            const wmW = pageWidth * 0.46;
            const wmH = wmW;
            const wmX = (pageWidth - wmW) / 2;
            const wmY = (pageHeight - wmH) / 2;
            if (canUseGState) {
                pdf.setGState(new pdf.GState({ opacity: 0.12 }));
            }
            pdf.addImage(logoDataUrl, 'PNG', wmX, wmY, wmW, wmH);
            if (canUseGState) {
                pdf.setGState(new pdf.GState({ opacity: 1 }));
            }
        }

        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(primary[0], primary[1], primary[2]);
        pdf.setFontSize(20);
        pdf.text('Resumen del Día — Libro de Ventas', margin, 44);

        pdf.setFont('helvetica', 'normal');
        pdf.setFontSize(11);
        pdf.setTextColor(75, 85, 99);
        pdf.text(`Fecha: ${dateLabel}`, margin, 62);

        const kpis = [
            { label: 'Total ventas', value: formatCurrency(summary.gross_sales || 0) },
            { label: 'Anulaciones', value: formatCurrency(summary.voided_sales || 0) },
            { label: 'Transacciones', value: Number(summary.transactions_count || 0).toLocaleString('es-CL') },
            { label: 'Ingreso neto', value: formatCurrency(summary.net_income || 0) }
        ];

        const kpiTop = 74;
        const kpiGap = 10;
        const kpiW = (contentWidth - kpiGap * 3) / 4;
        const kpiH = 72;
        kpis.forEach((kpi, index) => {
            const x = margin + (kpiW + kpiGap) * index;
            pdf.setDrawColor(232, 226, 255);
            pdf.setFillColor(255, 255, 255);
            pdf.roundedRect(x, kpiTop, kpiW, kpiH, 10, 10, 'FD');
            pdf.setTextColor(100, 116, 139);
            pdf.setFontSize(10);
            pdf.text(kpi.label, x + 10, kpiTop + 18);
            pdf.setTextColor(30, 27, 75);
            pdf.setFont('helvetica', 'bold');
            pdf.setFontSize(14);
            pdf.text(String(kpi.value), x + 10, kpiTop + 44);
            pdf.setFont('helvetica', 'normal');
        });

        const chartTop = kpiTop + kpiH + 16;
        const leftW = contentWidth * 0.6;
        const rightW = contentWidth - leftW - 12;

        drawBarsCard(pdf, {
            x: margin,
            y: chartTop,
            w: leftW,
            h: 216,
            title: 'Ventas por turno',
            values: shifts.map(s => ({ label: `#${s.shift_id}`, value: Number(s.sales_amount || 0) }))
        });

        drawDonutCard(pdf, {
            x: margin + leftW + 12,
            y: chartTop,
            w: rightW,
            h: 216,
            title: 'Distribución del día',
            values: [
                { label: 'Ventas', value: Math.max(0, Number(summary.gross_sales || 0)), color: [124, 58, 237] },
                { label: 'Devoluciones', value: Math.max(0, Number(summary.returns || 0)), color: [239, 68, 68] },
                { label: 'Gastos', value: Math.max(0, Number(summary.operational_expenses || 0)), color: [245, 158, 11] }
            ]
        });

        pdf.addPage('letter', 'landscape');

        if (logoDataUrl) {
            const wmW = pageWidth * 0.44;
            const wmH = wmW;
            const wmX = (pageWidth - wmW) / 2;
            const wmY = (pageHeight - wmH) / 2;
            if (canUseGState) {
                pdf.setGState(new pdf.GState({ opacity: 0.1 }));
            }
            pdf.addImage(logoDataUrl, 'PNG', wmX, wmY, wmW, wmH);
            if (canUseGState) {
                pdf.setGState(new pdf.GState({ opacity: 1 }));
            }
        }

        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(16);
        pdf.setTextColor(primary[0], primary[1], primary[2]);
        pdf.text('Top productos vendidos', margin, 42);

        drawHorizontalBarsCard(pdf, {
            x: margin,
            y: 56,
            w: contentWidth,
            h: pageHeight - 100,
            title: 'Ranking por cantidad',
            values: topProducts.map(item => ({
                label: String(item.name || 'Producto').slice(0, 44),
                value: Number(item.qty || 0)
            }))
        });

        const footer = `${generatedAt} · Sistema POS Libro de Ventas`;
        const pages = pdf.getNumberOfPages();
        for (let page = 1; page <= pages; page++) {
            pdf.setPage(page);
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(9);
            pdf.setTextColor(100, 116, 139);
            pdf.text(footer, pageWidth / 2, pageHeight - 12, { align: 'center' });
        }

        pdf.save(`Resumen_Dia_Libro_Ventas_${dateLabel}.pdf`);
    }

    function drawCardFrame(pdf, x, y, w, h, title) {
        pdf.setDrawColor(232, 226, 255);
        pdf.setFillColor(255, 255, 255);
        pdf.roundedRect(x, y, w, h, 10, 10, 'FD');
        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(11);
        pdf.setTextColor(51, 65, 85);
        pdf.text(title, x + 10, y + 16);
    }

    function drawBarsCard(pdf, { x, y, w, h, title, values }) {
        drawCardFrame(pdf, x, y, w, h, title);
        const chartX = x + 24;
        const chartY = y + 30;
        const chartW = w - 48;
        const chartH = h - 56;
        const safeValues = values.length ? values : [{ label: 'Sin datos', value: 0 }];
        const maxValue = Math.max(1, ...safeValues.map(item => item.value));
        const barW = Math.max(20, (chartW / safeValues.length) * 0.58);
        const gap = safeValues.length > 1 ? (chartW - barW * safeValues.length) / (safeValues.length - 1) : 0;

        safeValues.forEach((item, index) => {
            const ratio = Math.max(0, Number(item.value || 0)) / maxValue;
            const currentH = Math.max(2, ratio * chartH);
            const bx = chartX + index * (barW + gap);
            const by = chartY + (chartH - currentH);
            pdf.setFillColor(124, 58, 237);
            pdf.roundedRect(bx, by, barW, currentH, 4, 4, 'F');

            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(8);
            pdf.setTextColor(71, 85, 105);
            pdf.text(item.label, bx + barW / 2, chartY + chartH + 12, { align: 'center' });
            pdf.text(formatCurrency(item.value || 0), bx + barW / 2, by - 4, { align: 'center' });
        });
    }

    function drawDonutCard(pdf, { x, y, w, h, title, values }) {
        drawCardFrame(pdf, x, y, w, h, title);
        const centerX = x + w * 0.34;
        const centerY = y + h * 0.55;
        const outerR = Math.min(w, h) * 0.22;
        const innerR = outerR * 0.58;
        const total = Math.max(1, values.reduce((sum, item) => sum + Math.max(0, Number(item.value || 0)), 0));
        let start = -90;

        values.forEach((item) => {
            const value = Math.max(0, Number(item.value || 0));
            const sweep = (value / total) * 360;
            const color = item.color || [124, 58, 237];
            drawDonutSlice(pdf, centerX, centerY, outerR, innerR, start, start + sweep, color);
            start += sweep;
        });

        pdf.setFillColor(255, 255, 255);
        pdf.circle(centerX, centerY, innerR, 'F');

        pdf.setFont('helvetica', 'bold');
        pdf.setFontSize(12);
        pdf.setTextColor(30, 27, 75);
        pdf.text('100%', centerX, centerY + 4, { align: 'center' });

        let legendY = y + 56;
        values.forEach((item) => {
            const color = item.color || [124, 58, 237];
            pdf.setFillColor(color[0], color[1], color[2]);
            pdf.roundedRect(x + w * 0.58, legendY, 10, 10, 2, 2, 'F');
            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(9);
            pdf.setTextColor(51, 65, 85);
            pdf.text(`${item.label}: ${formatCurrency(item.value || 0)}`, x + w * 0.58 + 16, legendY + 8);
            legendY += 18;
        });
    }

    function drawDonutSlice(pdf, cx, cy, outerR, innerR, startDeg, endDeg, rgb) {
        const step = 6;
        const points = [];
        for (let a = startDeg; a <= endDeg; a += step) {
            const rad = (a * Math.PI) / 180;
            points.push([cx + outerR * Math.cos(rad), cy + outerR * Math.sin(rad)]);
        }
        for (let a = endDeg; a >= startDeg; a -= step) {
            const rad = (a * Math.PI) / 180;
            points.push([cx + innerR * Math.cos(rad), cy + innerR * Math.sin(rad)]);
        }
        if (points.length < 3) return;
        pdf.setFillColor(rgb[0], rgb[1], rgb[2]);
        pdf.lines(points.slice(1).map((p, i) => [p[0] - points[i][0], p[1] - points[i][1]]), points[0][0], points[0][1], [1, 1], 'F', true);
    }

    function drawHorizontalBarsCard(pdf, { x, y, w, h, title, values }) {
        drawCardFrame(pdf, x, y, w, h, title);
        const rows = values.length ? values : [{ label: 'Sin datos', value: 0 }];
        const maxValue = Math.max(1, ...rows.map(r => Number(r.value || 0)));
        const startY = y + 30;
        const rowGap = Math.max(20, (h - 54) / rows.length);
        const labelW = Math.min(260, w * 0.34);
        const barX = x + 14 + labelW;
        const barW = w - labelW - 40;

        rows.forEach((row, index) => {
            const ry = startY + index * rowGap;
            const ratio = Math.max(0, Number(row.value || 0)) / maxValue;
            const currentW = Math.max(2, ratio * barW);

            pdf.setFont('helvetica', 'normal');
            pdf.setFontSize(9);
            pdf.setTextColor(51, 65, 85);
            pdf.text(String(row.label || 'Producto'), x + 12, ry + 9);

            pdf.setFillColor(237, 233, 254);
            pdf.roundedRect(barX, ry, barW, 11, 3, 3, 'F');

            pdf.setFillColor(124, 58, 237);
            pdf.roundedRect(barX, ry, currentW, 11, 3, 3, 'F');

            pdf.setTextColor(30, 27, 75);
            pdf.text(Number(row.value || 0).toLocaleString('es-CL'), barX + currentW + 6, ry + 9);
        });
    }

    function loadImageAsDataUrl(url) {
        return new Promise((resolve, reject) => {
            const image = new Image();
            image.crossOrigin = 'anonymous';
            image.onload = () => {
                const canvas = document.createElement('canvas');
                canvas.width = image.naturalWidth;
                canvas.height = image.naturalHeight;
                const context = canvas.getContext('2d');
                if (!context) {
                    reject(new Error('Canvas unavailable'));
                    return;
                }
                context.drawImage(image, 0, 0);
                resolve(canvas.toDataURL('image/png'));
            };
            image.onerror = () => reject(new Error('Image load error'));
            image.src = url;
        });
    }

    function createShiftBarsSvg(shifts) {
        const width = 760;
        const height = 260;
        const padding = 36;
        const chartW = width - padding * 2;
        const chartH = height - 70;
        const max = Math.max(1, ...shifts.map(s => Number(s.sales_amount || 0)));
        const n = Math.max(1, shifts.length);
        const barW = Math.max(36, (chartW / n) * 0.55);
        const gap = (chartW - barW * n) / Math.max(1, n - 1 || 1);

        const bars = shifts.map((s, i) => {
            const value = Number(s.sales_amount || 0);
            const h = (value / max) * chartH;
            const x = padding + i * (barW + gap);
            const y = padding + (chartH - h);
            return `
                <defs>
                  <linearGradient id="g${i}" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#9f7aea" />
                    <stop offset="100%" stop-color="#7c3aed" />
                  </linearGradient>
                </defs>
                <rect x="${x}" y="${y}" width="${barW}" height="${h}" rx="8" fill="url(#g${i})"></rect>
                <text x="${x + barW / 2}" y="${y - 6}" text-anchor="middle" font-size="11" fill="#334155">${formatCurrency(value)}</text>
                <text x="${x + barW / 2}" y="${padding + chartH + 16}" text-anchor="middle" font-size="11" fill="#475569">#${s.shift_id}</text>
            `;
        }).join('');

        return `<svg viewBox="0 0 ${width} ${height}" width="100%" height="260" aria-label="Ventas por turno">${bars}</svg>`;
    }

    function createDonutSvg(summary) {
        const net = Math.max(0, Number(summary.net_income || 0));
        const returns = Math.max(0, Number(summary.returns || 0));
        const expenses = Math.max(0, Number(summary.operational_expenses || 0));
        const total = Math.max(1, net + returns + expenses);

        const c = 2 * Math.PI * 62;
        const netArc = (net / total) * c;
        const retArc = (returns / total) * c;
        const expArc = (expenses / total) * c;

        const pNet = Math.round((net / total) * 100);
        const pRet = Math.round((returns / total) * 100);
        const pExp = Math.max(0, 100 - pNet - pRet);

        return `
            <svg viewBox="0 0 420 240" width="100%" height="240" aria-label="Distribución del día">
                <g transform="translate(140,120) rotate(-90)">
                    <circle r="62" cx="0" cy="0" fill="none" stroke="#e2e8f0" stroke-width="24"></circle>
                    <circle r="62" cx="0" cy="0" fill="none" stroke="#7c3aed" stroke-width="24" stroke-dasharray="${netArc} ${c - netArc}" stroke-linecap="butt"></circle>
                    <circle r="62" cx="0" cy="0" fill="none" stroke="#ef4444" stroke-width="24" stroke-dasharray="${retArc} ${c - retArc}" stroke-dashoffset="-${netArc}" stroke-linecap="butt"></circle>
                    <circle r="62" cx="0" cy="0" fill="none" stroke="#f59e0b" stroke-width="24" stroke-dasharray="${expArc} ${c - expArc}" stroke-dashoffset="-${netArc + retArc}" stroke-linecap="butt"></circle>
                </g>
                <text x="140" y="118" text-anchor="middle" font-size="18" font-weight="700" fill="#1e1b4b">${pNet}%</text>
                <text x="140" y="136" text-anchor="middle" font-size="11" fill="#64748b">Neto</text>
                <g transform="translate(255,65)">
                    <rect width="12" height="12" fill="#7c3aed" rx="2"></rect>
                    <text x="18" y="10" font-size="12" fill="#334155">Ventas netas (${pNet}%)</text>
                    <rect y="28" width="12" height="12" fill="#ef4444" rx="2"></rect>
                    <text x="18" y="38" font-size="12" fill="#334155">Devoluciones (${pRet}%)</text>
                    <rect y="56" width="12" height="12" fill="#f59e0b" rx="2"></rect>
                    <text x="18" y="66" font-size="12" fill="#334155">Gastos (${pExp}%)</text>
                </g>
            </svg>
        `;
    }

    function createTopProductsSvg(products) {
        const width = 980;
        const barHeight = 24;
        const gap = 10;
        const max = Math.max(1, ...products.map(p => Number(p.qty || 0)));
        const totalHeight = Math.max(120, products.length * (barHeight + gap) + 24);

        const rows = products.map((p, i) => {
            const y = 10 + i * (barHeight + gap);
            const w = Math.max(24, (Number(p.qty || 0) / max) * 620);
            return `
                <text x="10" y="${y + 16}" font-size="11" fill="#334155">${String(p.name || 'Producto').slice(0, 42)}</text>
                <rect x="280" y="${y}" width="${w}" height="${barHeight}" fill="#7c3aed" rx="8"></rect>
                <text x="${280 + w + 8}" y="${y + 16}" font-size="11" fill="#1e293b">${Number(p.qty || 0).toLocaleString('es-CL')}</text>
            `;
        }).join('');

        return `<svg viewBox="0 0 ${width} ${totalHeight}" width="100%" height="${totalHeight}" aria-label="Top productos">${rows}</svg>`;
    }

    exportAllBtn.addEventListener('click', async function() {
        exportAllBtn.disabled = true;
        try {
            const payload = await fetchDailyReportData();
            if (!payload.success) {
                alert(payload.message || 'No se pudo generar el reporte diario.');
                return;
            }

            const report = payload.data;
            buildDailyXlsx(report);
            await buildPdfPresentation(report);
        } catch (error) {
            alert('Error de conexión al generar reportes diarios.');
        } finally {
            exportAllBtn.disabled = false;
        }
    });

    fetchShifts();
    showListView();
});


