document.addEventListener("DOMContentLoaded", () => {
    const container = document.querySelector("#example");
    const data = window.sheetData;

    const hot = new Handsontable(container, {
        themeName: "ht-theme-main",
        data: data,
        rowHeaders: true,
        colHeaders: true,
        height: "auto",
        width: "100%",
        stretchH: "all",
        contextMenu: true,
        manualRowResize: true,
        manualColumnResize: true,
        columnSorting: true,
        filters: true,
        dropdownMenu: true,
        mergeCells: true,
        undo: true,
        copyPaste: true,
        licenseKey: "non-commercial-and-evaluation",
    });

    document.getElementById("saveBtn").addEventListener("click", () => {
        fetch(window.saveUrl, {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": window.csrfToken,
            },
            body: JSON.stringify({
                data: hot.getData(),
            }),
        })
            .then(() => {
                Swal.fire({
                    icon: "success",
                    showConfirmButton: false,
                    timer: 1500,
                });
            })
            .catch(() => {
                Swal.fire({
                    icon: "error",
                    title: "Oops...",
                    text: "Error saving the sheet!",
                });
            });
    });

    document.getElementById("exportBtn").addEventListener("click", () => {
        try {
            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet(hot.getData());
            XLSX.utils.book_append_sheet(wb, ws, "Sheet1");
            const wbout = XLSX.write(wb, { bookType: "xlsx", type: "array" });
            saveAs(new Blob([wbout]), "sheet.xlsx");

            Swal.fire({
                icon: "success",
                timer: 1500,
                showConfirmButton: false,
            });
        } catch (error) {
            Swal.fire({
                icon: "error",
            });
        }
    });

    document.getElementById("importBtn").addEventListener("click", () => {
        document.getElementById("importFile").click();
    });

    document.getElementById("importFile").addEventListener("change", (e) => {
        const file = e.target.files[0];
        if (!file) {
            Swal.fire({
                icon: "warning",
            });
            return;
        }

        const reader = new FileReader();
        reader.onload = (evt) => {
            try {
                const workbook = XLSX.read(evt.target.result, {
                    type: "binary",
                });
                const ws = workbook.Sheets[workbook.SheetNames[0]];
                const imported = XLSX.utils.sheet_to_json(ws, { header: 1 });

                hot.loadData(imported);

                fetch(window.saveUrl, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": window.csrfToken,
                    },
                    body: JSON.stringify({
                        data: hot.getData(),
                    }),
                });

                Swal.fire({
                    icon: "success",
                    timer: 1500,
                    showConfirmButton: false,
                });
            } catch (error) {
                Swal.fire({
                    icon: "error",
                });
            }
        };

        reader.readAsBinaryString(file);
    });
});
