/**
 * Smart Installments Manager
 * Handles inline CRUD operations for installments within Deal Purchase forms
 */

class InstallmentsManager {
    constructor(options = {}) {
        this.dealPurchaseId = options.dealPurchaseId || null;
        this.totalPrice = 0;
        this.paidAmount = 0; // Amount already paid upfront
        this.installments = [];
        this.editingIndex = -1;
        this.currency = options.currency || "";

        // Translations
        this.translations = options.translations || {
            editInstallment: "Edit Installment",
            addNewInstallment: "Add New Installment",
            amountExceedsBalance: "Amount exceeds remaining balance",
            pleaseSelectDate: "Please select a date",
            installmentUpdated: "Installment updated successfully",
            installmentAdded: "Installment added successfully",
            confirmDelete: "Are you sure?",
            confirmDeleteText:
                "This action cannot be undone. Do you want to continue?",
            confirmYes: "Yes",
            confirmNo: "No",
            installmentDeleted: "Installment deleted successfully",
            noInstallments:
                'No installments added yet. Click "Add Installment" to get started.',
            noDescription: "No description",
            edit: "Edit",
            deleteBtn: "Delete",
            remainingBalance: "Remaining balance: ",
            paid: "Paid",
            unpaid: "Unpaid",
        };

        this.init();
    }

    init() {
        this.bindEvents();
        this.loadExistingInstallments();
    }

    bindEvents() {
        const self = this;

        // Add installment button
        $("#add-installment-btn").on("click", function () {
            self.showForm();
        });

        // Save installment button
        $("#save-installment-btn").on("click", function () {
            self.saveInstallment();
        });

        // Cancel button
        $("#cancel-installment-btn").on("click", function () {
            self.hideForm();
        });

        // Set max amount button
        $("#set-max-amount-btn").on("click", function () {
            const remaining = self.getRemainingBalance();
            $("#installment-amount").val(remaining.toFixed(2)).trigger("input");
        });

        // Amount input validation
        $("#installment-amount").on("input blur", function () {
            self.validateAmount();
        });

        // Hopeless toggle
        $("#installment-hopeless").on("change", function () {
            self.updateHopelessLabel();
        });

        // Exemption and amount changes
        $("#installment-exemption, #installment-amount").on("input", function () {
            self.updateEffectiveAmount();
        });

        // Listen to price changes from main form
        $(document).on("priceChanged", function (e, newPrice) {
            self.updateTotalPrice(newPrice);
        });
    }

    showForm(installment = null, index = -1) {
        this.editingIndex = index;

        if (installment) {
            // Edit mode
            $("#form-title").html(
                '<i class="ti ti-edit"></i> ' +
                this.translations.editInstallment
            );
            $("#installment-id").val(installment.id || "");
            $("#installment-index").val(index);
            $("#installment-date").val(installment.date);
            $("#installment-amount").val(installment.amount);
            $("#installment-exemption").val(installment.exemption || 0);
            $("#installment-hopeless").prop('checked', installment.is_hopeless || false);
            $("#installment-description").val(installment.description || "");
            $("#installment-status").val(installment.status || "unpaid");
            this.updateHopelessLabel();
            this.updateEffectiveAmount();
        } else {
            // Add mode
            $("#form-title").html(
                '<i class="ti ti-plus"></i> ' +
                this.translations.addNewInstallment
            );
            $("#installment-id").val("");
            $("#installment-index").val("");
            $("#installment-date").val("");
            $("#installment-amount").val("");
            $("#installment-exemption").val(0);
            $("#installment-hopeless").prop('checked', false);
            $("#installment-description").val("");
            $("#installment-status").val("unpaid");
            this.updateHopelessLabel();
            this.updateEffectiveAmount();
        }

        $("#installment-form-container").slideDown(300);
        $("#installment-date").focus();
        this.updateRemainingHelper();
    }

    hideForm() {
        $("#installment-form-container").slideUp(300);
        this.editingIndex = -1;
        this.clearFormValidation();
    }

    clearFormValidation() {
        $("#installment-date, #installment-amount").removeClass("is-invalid");
    }

    validateAmount() {
        const amount = parseFloat($("#installment-amount").val()) || 0;
        const remaining = this.getRemainingBalance();
        const $input = $("#installment-amount");

        if (amount <= 0) {
            $input.addClass("is-invalid");
            return false;
        }

        if (amount > remaining) {
            $input.addClass("is-invalid");
            this.showToast("error", this.translations.amountExceedsBalance);
            return false;
        }

        $input.removeClass("is-invalid");
        return true;
    }

    saveInstallment() {
        // Validate
        const date = $("#installment-date").val();
        const amount = parseFloat($("#installment-amount").val()) || 0;
        const description = $("#installment-description").val();
        const index = parseInt($("#installment-index").val());

        if (!date) {
            $("#installment-date").addClass("is-invalid");
            this.showToast("error", this.translations.pleaseSelectDate);
            return;
        }

        if (!this.validateAmount()) {
            return;
        }

        const installment = {
            id: $("#installment-id").val() || null,
            date: date,
            amount: amount,
            exemption: parseFloat($("#installment-exemption").val()) || 0,
            is_hopeless: $("#installment-hopeless").is(':checked'),
            description: description,
            status: $("#installment-status").val() || "unpaid",
            is_new: !$("#installment-id").val(),
        };

        if (this.editingIndex >= 0) {
            // Update existing
            this.installments[this.editingIndex] = installment;
            this.showToast("success", this.translations.installmentUpdated);
        } else {
            // Add new
            this.installments.push(installment);
            this.showToast("success", this.translations.installmentAdded);
        }

        this.updateInstallmentsData();
        this.renderInstallments();
        this.hideForm();
        this.updateSummary();
        // Note: Don't call updatePaidAmount() - paid_amount is the upfront payment, not installments total
    }

    editInstallment(index) {
        const installment = this.installments[index];
        this.showForm(installment, index);
    }

    deleteInstallment(index) {
        const self = this;

        // Use SweetAlert2 for confirmation (same as application's delete pattern)
        const swalWithBootstrapButtons = Swal.mixin({
            customClass: {
                confirmButton: "btn btn-success",
                cancelButton: "btn btn-danger",
            },
            buttonsStyling: false,
        });

        swalWithBootstrapButtons
            .fire({
                title: this.translations.confirmDelete,
                text:
                    this.translations.confirmDeleteText ||
                    "This action cannot be undone.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: this.translations.confirmYes || "Yes",
                cancelButtonText: this.translations.confirmNo || "No",
                reverseButtons: true,
            })
            .then((result) => {
                if (result.isConfirmed) {
                    self.installments.splice(index, 1);
                    self.updateInstallmentsData();
                    self.renderInstallments();
                    self.updateSummary();
                    // Note: Don't call updatePaidAmount() - paid_amount is the upfront payment, not installments total
                    self.showToast(
                        "success",
                        self.translations.installmentDeleted
                    );
                }
            });
    }

    renderInstallments() {
        const $tbody = $("#installments-tbody");
        $tbody.empty();

        if (this.installments.length === 0) {
            $tbody.append(`
                <tr id="no-installments-row">
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="ti ti-folder-off" style="font-size: 3rem; display: block; margin-bottom: 1rem;"></i>
                        <p class="mb-0">${this.translations.noInstallments}</p>
                    </td>
                </tr>
            `);
            return;
        }

        this.installments.forEach((inst, index) => {
            const formattedDate = this.formatDateTime(inst.date);
            const formattedAmount = parseFloat(inst.amount).toFixed(2);
            const exemption = parseFloat(inst.exemption || 0).toFixed(2);
            const effective = (parseFloat(inst.amount) - parseFloat(inst.exemption || 0)).toFixed(2);
            // Default to 'unpaid' if status is undefined/null
            const instStatus = inst.status || "unpaid";
            const statusClass =
                instStatus === "paid" ? "bg-success" : "bg-warning";
            const statusText =
                instStatus === "paid"
                    ? this.translations.paid
                    : this.translations.unpaid;
            const hopelessBadge = inst.is_hopeless
                ? '<span class="badge bg-danger">ON</span>'
                : '<span class="badge bg-secondary">OFF</span>';

            const row = `
                <tr class="installment-row-enter">
                    <td><strong>${index + 1}</strong></td>
                    <td>
                        <i class="ti ti-calendar text-muted"></i>
                        ${formattedDate}
                    </td>
                    <td>
                        <span class="badge badge-sm bg-primary">
                            ${formattedAmount} ${this.currency}
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-sm bg-info">
                            ${exemption} ${this.currency}
                        </span>
                    </td>
                    <td>
                        <strong class="text-success">${effective} ${this.currency}</strong>
                    </td>
                    <td>
                        <span class="badge ${statusClass}">${statusText}</span>
                    </td>
                    <td>${hopelessBadge}</td>
                    <td>
                        <small class="text-muted">${inst.description ||
                "<em>" + this.translations.noDescription + "</em>"
                }</small>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-primary" onclick="installmentsManager.editInstallment(${index})" 
                                title="${this.translations.edit}">
                            <i class="ti ti-edit"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="installmentsManager.deleteInstallment(${index})"
                                title="${this.translations.deleteBtn}">
                            <i class="ti ti-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
            $tbody.append(row);
        });
    }

    updateSummary() {
        // Calculate paid and unpaid installments separately
        const paidInstallments = this.installments
            .filter(inst => inst.status === 'paid')
            .reduce((sum, inst) => sum + parseFloat(inst.amount), 0);

        const unpaidInstallments = this.installments
            .filter(inst => inst.status !== 'paid')
            .reduce((sum, inst) => sum + parseFloat(inst.amount), 0);

        const totalExemption = this.installments
            .reduce((sum, inst) => sum + parseFloat(inst.exemption || 0), 0);

        const installmentsTotal = paidInstallments + unpaidInstallments;

        // Installable Amount = Total Price - Initial Payment (paid_amount)
        const installableAmount = this.totalPrice - this.paidAmount;
        // Remaining = Installable Amount - Installments Total
        const remaining = installableAmount - installmentsTotal;

        // Display: If there's a paid amount (initial payment), show the breakdown
        if (this.paidAmount > 0) {
            $("#summary-total-price").text(
                this.totalPrice.toFixed(2) +
                " - " +
                this.paidAmount.toFixed(2) +
                " = " +
                installableAmount.toFixed(2) +
                " " +
                this.currency
            );
        } else {
            $("#summary-total-price").text(
                this.totalPrice.toFixed(2) + " " + this.currency
            );
        }

        // Update Total Installments card to show paid amount
        $("#summary-installments-total").text(
            paidInstallments.toFixed(2) + " " + this.currency
        );

        // Update Exemption card
        $("#summary-exemption-total").text(
            totalExemption.toFixed(2) + " " + this.currency
        );

        // Update icon and color based on paid status
        const $installmentsCard = $("#summary-installments-total").closest('.card');
        const $installmentsIcon = $installmentsCard.find('.ti-check');

        if (paidInstallments > 0) {
            $installmentsCard.removeClass('card-stats-warning').addClass('card-stats-success');
            $installmentsIcon.removeClass('text-warning').addClass('text-success');
        } else {
            $installmentsCard.removeClass('card-stats-success').addClass('card-stats-warning');
            $installmentsIcon.removeClass('text-success').addClass('text-warning');
        }

        // Update Remaining Balance to show unpaid installments
        $("#summary-remaining-balance").text(
            (remaining + unpaidInstallments).toFixed(2) + " " + this.currency
        );

        // Update badge color based on remaining
        const $remainingBadge = $("#summary-remaining-balance")
            .parent()
            .parent();
        $remainingBadge.removeClass(
            "bg-warning bg-success bg-danger bg-opacity-10"
        );

        if (remaining === 0 && unpaidInstallments === 0) {
            $remainingBadge.addClass("bg-success bg-opacity-10");
            $("#summary-remaining-balance")
                .removeClass("text-warning text-danger")
                .addClass("text-success");
        } else if (remaining < 0) {
            $remainingBadge.addClass("bg-danger bg-opacity-10");
            $("#summary-remaining-balance")
                .removeClass("text-warning text-success")
                .addClass("text-danger");
        } else {
            $remainingBadge.addClass("bg-warning bg-opacity-10");
            $("#summary-remaining-balance")
                .removeClass("text-success text-danger")
                .addClass("text-warning");
        }
    }

    updateRemainingHelper() {
        const remaining = this.getRemainingBalance();
        $("#amount-helper").html(
            this.translations.remainingBalance +
            '<span id="remaining-for-input">' +
            remaining.toFixed(2) +
            " " +
            this.currency +
            "</span>"
        );
    }

    getRemainingBalance() {
        const total = this.installments.reduce((sum, inst, idx) => {
            // Exclude current editing installment
            if (idx === this.editingIndex) {
                return sum;
            }
            return sum + parseFloat(inst.amount);
        }, 0);

        // Remaining = Total Price - Paid Amount (upfront) - Installments Total
        const effectiveTotal = this.totalPrice - this.paidAmount;
        return Math.max(0, effectiveTotal - total);
    }

    updateTotalPrice(newPrice) {
        this.totalPrice = parseFloat(newPrice) || 0;
        this.updateSummary();
        this.updateRemainingHelper();
    }

    updateTotalPriceWithPaidAmount(newPrice, paidAmount) {
        this.totalPrice = parseFloat(newPrice) || 0;
        this.paidAmount = parseFloat(paidAmount) || 0;
        this.updateSummary();
        this.updateRemainingHelper();
    }

    updatePaidAmount() {
        // Auto-calculate paid amount from installments
        const total = this.installments.reduce(
            (sum, inst) => sum + parseFloat(inst.amount),
            0
        );
        $("#paid_amount").val(total.toFixed(2));
    }

    updateInstallmentsData() {
        // Store installments data in a hidden field for form submission
        const installmentsJson = JSON.stringify(this.installments);

        // Remove existing hidden field if any
        $('input[name="installments_data"]').remove();

        // Add new hidden field
        const hiddenField = $("<input>", {
            type: "hidden",
            name: "installments_data",
            value: installmentsJson,
        });

        $("form").append(hiddenField);
    }

    loadExistingInstallments() {
        // This will be called when editing
        // Installments will be loaded from server
        const existingData = $("#existing-installments-data").val();

        if (existingData) {
            try {
                this.installments = JSON.parse(existingData).map((inst) => {
                    // Normalize date to YYYY-MM-DD in case server provided datetime
                    if (inst && typeof inst.date === "string") {
                        inst.date = inst.date.length > 10 ? inst.date.substring(0, 10) : inst.date;
                    }
                    return inst;
                });
                this.renderInstallments();
                this.updateSummary();
            } catch (e) {
                console.error("Failed to parse installments data", e);
            }
        }
    }

    updateHopelessLabel() {
        const isChecked = $("#installment-hopeless").is(':checked');
        $("#hopeless-label").text(isChecked ? 'ON' : 'OFF');
        $("#hopeless-warning").toggle(isChecked);
    }

    updateEffectiveAmount() {
        const amount = parseFloat($("#installment-amount").val()) || 0;
        const exemption = parseFloat($("#installment-exemption").val()) || 0;
        const effective = Math.max(0, amount - exemption);
        $("#effective-amount-display").text(effective.toFixed(2) + " " + this.currency);
    }

    formatDateTime(dateTimeStr) {
        // Display date only
        try {
            // If it's already in YYYY-MM-DD, return as is in a readable format
            if (typeof dateTimeStr === "string" && /^\d{4}-\d{2}-\d{2}$/.test(dateTimeStr)) {
                const [y, m, d] = dateTimeStr.split("-");
                const date = new Date(parseInt(y), parseInt(m) - 1, parseInt(d));
                return date.toLocaleDateString(undefined, {
                    year: "numeric",
                    month: "short",
                    day: "numeric",
                });
            }
            const date = new Date(dateTimeStr);
            return date.toLocaleDateString(undefined, {
                year: "numeric",
                month: "short",
                day: "numeric",
            });
        } catch (e) {
            return dateTimeStr;
        }
    }

    showToast(type, message) {
        // Custom toast implementation for installments manager
        var f = document.getElementById("liveToast");
        if (f) {
            // Remove previous classes
            $("#liveToast").removeClass(
                "bg-primary bg-success bg-danger bg-warning bg-info"
            );

            // Add appropriate class based on type
            if (type == "success") {
                $("#liveToast").addClass("bg-success");
            } else {
                $("#liveToast").addClass("bg-danger");
            }

            // Set message and show toast
            $("#liveToast .toast-body").html(message);
            var toast = new bootstrap.Toast(f);
            toast.show();
        } else {
            // Fallback to alert if toast element not found
            alert(message);
        }
    }

    show() {
        $("#installments-manager-wrapper").slideDown(400);
        this.updateRemainingHelper();
    }

    hide() {
        $("#installments-manager-wrapper").slideUp(400);
        this.hideForm();
    }
}

// Global instance will be initialized in the view
var installmentsManager;
