const fs = require('fs');

// Helper function to create request
function req(name, method, path, body = [], desc = "", autoSaveToken = false, noAuth = false) {
    const headers = [{ key: "Accept", value: "application/json", type: "text" }];
    
    const request = {
        name: name,
        request: {
            method: method,
            header: headers,
            url: {
                raw: "{{base_url}}" + path,
                host: ["{{base_url}}"],
                path: path.replace(/^\//, '').split("/")
            },
            description: desc
        },
        response: []
    };
    
    if (!noAuth) {
        request.request.auth = {
            type: "bearer",
            bearer: [{ key: "token", value: "{{token}}", type: "string" }]
        };
    }
    
    if (body.length > 0 && (method === "POST" || method === "PUT")) {
        request.request.body = {
            mode: "formdata",
            formdata: body.map(([k, v]) => ({ key: k, value: v, type: "text" }))
        };
    }
    
    if (autoSaveToken) {
        request.event = [{
            listen: "test",
            script: {
                exec: [
                    "if (pm.response.code === 200) {",
                    "    var jsonData = pm.response.json();",
                    "    if (jsonData.data && jsonData.data.token) {",
                    "        pm.collectionVariables.set('token', jsonData.data.token);",
                    "    }",
                    "}"
                ],
                type: "text/javascript"
            }
        }];
    }
    
    return request;
}

// Create collection structure
const collection = {
    info: {
        _postman_id: "erp-complete-api-v2",
        name: "ERP System - Complete Professional API Collection",
        description: "Complete API collection for ERP System with all modules: HRM, CRM, Accounting, Projects, Inventory, POS and more. Ready to use!",
        schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        version: "2.0.0"
    },
    auth: { 
        type: "bearer", 
        bearer: [{ key: "token", value: "{{token}}", type: "string" }] 
    },
    variable: [
        { key: "base_url", value: "http://localhost", type: "string" },
        { key: "token", value: "", type: "string" },
        { key: "user_id", value: "", type: "string" }
    ],
    item: []
};

// Add all folders with endpoints
const folders = [
    {
        name: "01. Authentication",
        description: "User authentication and profile management",
        item: [
            req("Login", "POST", "/api/v1/login", [["email", "admin@example.com"], ["password", "password"]], "Login to get authentication token", true, true),
            req("Register", "POST", "/api/v1/register", [["name", "John Doe"], ["email", "john@example.com"], ["password", "password123"]], "Register new user", false, true),
            req("Logout", "POST", "/api/v1/logout", [], "Logout and revoke tokens"),
            req("Get Profile", "GET", "/api/v1/profile", [], "Get current user profile"),
            req("Update Profile", "PUT", "/api/v1/profile", [["name", "Updated Name"]], "Update user profile"),
            req("Change Password", "POST", "/api/v1/change-password", [["current_password", "old"], ["password", "new"], ["password_confirmation", "new"]], "Change password"),
            req("Forgot Password", "POST", "/api/v1/forgot-password", [["email", "user@example.com"]], "Request password reset", false, true),
            req("Reset Password", "POST", "/api/v1/reset-password", [["email", "user@example.com"], ["password", "newpass"], ["password_confirmation", "newpass"], ["token", "reset_token"]], "Reset password", false, true),
            req("Upload Avatar", "POST", "/api/v1/upload-avatar", [], "Upload user avatar")
        ]
    },
    {
        name: "02. Dashboard",
        description: "Dashboard statistics and analytics",
        item: [
            req("Main Dashboard", "GET", "/api/v1/dashboard", [], "Get main dashboard data"),
            req("Dashboard Stats", "GET", "/api/v1/dashboard/stats", [], "Get dashboard statistics"),
            req("HRM Dashboard", "GET", "/api/v1/dashboard/hrm", [], "Get HRM dashboard"),
            req("CRM Dashboard", "GET", "/api/v1/dashboard/crm", [], "Get CRM dashboard"),
            req("Project Dashboard", "GET", "/api/v1/dashboard/project", [], "Get project dashboard"),
            req("Account Dashboard", "GET", "/api/v1/dashboard/account", [], "Get accounting dashboard"),
            req("POS Dashboard", "GET", "/api/v1/dashboard/pos", [], "Get POS dashboard")
        ]
    },
    {
        name: "03. HRM - Employees",
        description: "Employee management",
        item: [
            req("Get All Employees", "GET", "/api/v1/employees?page=1&per_page=15", [], "List all employees with pagination"),
            req("Create Employee", "POST", "/api/v1/employees", [["name", "John Doe"], ["email", "emp@example.com"], ["password", "pass123"], ["employee_id", "EMP001"], ["department_id", "1"]], "Create new employee"),
            req("Get Employee", "GET", "/api/v1/employees/1", [], "Get employee by ID"),
            req("Update Employee", "PUT", "/api/v1/employees/1", [["name", "Updated Name"]], "Update employee"),
            req("Delete Employee", "DELETE", "/api/v1/employees/1", [], "Delete employee"),
            req("Get Employee Documents", "GET", "/api/v1/employees/1/documents", [], "Get employee documents"),
            req("Get Employee Salary", "GET", "/api/v1/employees/1/salary", [], "Get salary information"),
            req("Set Employee Salary", "POST", "/api/v1/employees/1/salary", [["basic_salary", "5000"]], "Set employee salary"),
            req("Get Employee Attendance", "GET", "/api/v1/employees/1/attendance", [], "Get attendance records"),
            req("Get Employee Leaves", "GET", "/api/v1/employees/1/leaves", [], "Get leave records"),
            req("Get Employee Payslips", "GET", "/api/v1/employees/1/payslips", [], "Get payslips"),
            req("Import Employees", "POST", "/api/v1/import/employees", [], "Import from CSV/Excel"),
            req("Export Employees", "GET", "/api/v1/export/employees", [], "Export to CSV/Excel")
        ]
    },
    {
        name: "04. HRM - Attendance",
        description: "Attendance management",
        item: [
            req("Get Attendance", "GET", "/api/v1/attendance?date=2024-01-01", [], "Get attendance records"),
            req("Mark Attendance", "POST", "/api/v1/attendance", [["employee_id", "1"], ["date", "2024-01-01"], ["clock_in", "09:00:00"], ["clock_out", "17:00:00"], ["status", "Present"]], "Mark attendance"),
            req("Bulk Attendance", "POST", "/api/v1/attendance/bulk", [], "Mark bulk attendance"),
            req("Get Attendance Detail", "GET", "/api/v1/attendance/1", [], "Get attendance by ID"),
            req("Update Attendance", "PUT", "/api/v1/attendance/1", [["clock_out", "18:00:00"]], "Update attendance"),
            req("Delete Attendance", "DELETE", "/api/v1/attendance/1", [], "Delete attendance"),
            req("Get Employee Attendance List", "GET", "/api/v1/attendance/employee/1", [], "Get employee attendance"),
            req("Import Attendance", "POST", "/api/v1/attendance/import", [], "Import attendance"),
            req("Export Attendance", "GET", "/api/v1/attendance/export", [], "Export attendance")
        ]
    },
    {
        name: "05. HRM - Leaves",
        description: "Leave management",
        item: [
            req("Get Leaves", "GET", "/api/v1/leaves", [], "Get all leave requests"),
            req("Create Leave", "POST", "/api/v1/leaves", [["employee_id", "1"], ["leave_type_id", "1"], ["start_date", "2024-02-01"], ["end_date", "2024-02-05"], ["reason", "Personal"]], "Create leave request"),
            req("Get Leave", "GET", "/api/v1/leaves/1", [], "Get leave by ID"),
            req("Update Leave", "PUT", "/api/v1/leaves/1", [["reason", "Updated reason"]], "Update leave"),
            req("Delete Leave", "DELETE", "/api/v1/leaves/1", [], "Delete leave"),
            req("Approve Leave", "POST", "/api/v1/leaves/1/approve", [], "Approve leave request"),
            req("Reject Leave", "POST", "/api/v1/leaves/1/reject", [["rejection_reason", "Not enough balance"]], "Reject leave"),
            req("Get Leave Types", "GET", "/api/v1/leaves/types", [], "Get all leave types"),
            req("Create Leave Type", "POST", "/api/v1/leaves/types", [["name", "Sick Leave"], ["days", "10"]], "Create leave type")
        ]
    },
    {
        name: "06. HRM - Departments",
        description: "Department management",
        item: [
            req("Get Departments", "GET", "/api/v1/departments", [], "Get all departments"),
            req("Create Department", "POST", "/api/v1/departments", [["name", "IT Department"], ["branch_id", "1"]], "Create department"),
            req("Get Department", "GET", "/api/v1/departments/1", [], "Get department by ID"),
            req("Update Department", "PUT", "/api/v1/departments/1", [["name", "Updated Name"]], "Update department"),
            req("Delete Department", "DELETE", "/api/v1/departments/1", [], "Delete department"),
            req("Get Department Employees", "GET", "/api/v1/departments/1/employees", [], "Get employees in department")
        ]
    },
    {
        name: "07. HRM - Designations",
        description: "Designation management",
        item: [
            req("Get Designations", "GET", "/api/v1/designations", [], "Get all designations"),
            req("Create Designation", "POST", "/api/v1/designations", [["name", "Senior Developer"]], "Create designation"),
            req("Get Designation", "GET", "/api/v1/designations/1", [], "Get designation by ID"),
            req("Update Designation", "PUT", "/api/v1/designations/1", [["name", "Lead Developer"]], "Update designation"),
            req("Delete Designation", "DELETE", "/api/v1/designations/1", [], "Delete designation")
        ]
    },
    {
        name: "08. HRM - Payroll",
        description: "Payroll management",
        item: [
            req("Get Payslips", "GET", "/api/v1/payroll/payslips", [], "Get all payslips"),
            req("Create Payslip", "POST", "/api/v1/payroll/payslips", [["employee_id", "1"], ["month", "2024-01"], ["basic_salary", "5000"]], "Create payslip"),
            req("Get Payslip", "GET", "/api/v1/payroll/payslips/1", [], "Get payslip by ID"),
            req("Update Payslip", "PUT", "/api/v1/payroll/payslips/1", [["basic_salary", "5500"]], "Update payslip"),
            req("Delete Payslip", "DELETE", "/api/v1/payroll/payslips/1", [], "Delete payslip"),
            req("Download Payslip PDF", "GET", "/api/v1/payroll/payslips/1/pdf", [], "Download PDF"),
            req("Send Payslip", "POST", "/api/v1/payroll/payslips/1/send", [], "Send via email"),
            req("Get Allowances", "GET", "/api/v1/payroll/allowances", [], "Get allowances"),
            req("Get Deductions", "GET", "/api/v1/payroll/deductions", [], "Get deductions"),
            req("Get Commissions", "GET", "/api/v1/payroll/commissions", [], "Get commissions"),
            req("Get Loans", "GET", "/api/v1/payroll/loans", [], "Get loans"),
            req("Get Overtime", "GET", "/api/v1/payroll/overtime", [], "Get overtime")
        ]
    },
    {
        name: "09. HRM - Awards & Complaints",
        description: "Awards, Complaints, Warnings, Terminations, Promotions, Transfers, Resignations, Travels",
        item: [
            req("Get Awards", "GET", "/api/v1/awards", [], "Get all awards"),
            req("Create Award", "POST", "/api/v1/awards", [["employee_id", "1"], ["award_type", "Best Employee"]], "Create award"),
            req("Get Complaints", "GET", "/api/v1/complaints", [], "Get all complaints"),
            req("Create Complaint", "POST", "/api/v1/complaints", [["title", "Complaint"], ["description", "Details"]], "Create complaint"),
            req("Get Warnings", "GET", "/api/v1/warnings", [], "Get all warnings"),
            req("Create Warning", "POST", "/api/v1/warnings", [["employee_id", "1"], ["subject", "Warning"]], "Create warning"),
            req("Get Terminations", "GET", "/api/v1/terminations", [], "Get all terminations"),
            req("Create Termination", "POST", "/api/v1/terminations", [["employee_id", "1"]], "Create termination"),
            req("Get Promotions", "GET", "/api/v1/promotions", [], "Get all promotions"),
            req("Create Promotion", "POST", "/api/v1/promotions", [["employee_id", "1"], ["designation_id", "2"]], "Create promotion"),
            req("Get Transfers", "GET", "/api/v1/transfers", [], "Get all transfers"),
            req("Create Transfer", "POST", "/api/v1/transfers", [["employee_id", "1"], ["branch_id", "2"]], "Create transfer"),
            req("Get Resignations", "GET", "/api/v1/resignations", [], "Get all resignations"),
            req("Create Resignation", "POST", "/api/v1/resignations", [["employee_id", "1"]], "Create resignation"),
            req("Get Travels", "GET", "/api/v1/travels", [], "Get all travels"),
            req("Create Travel", "POST", "/api/v1/travels", [["employee_id", "1"], ["purpose", "Business"]], "Create travel")
        ]
    },
    {
        name: "10. HRM - Training & Performance",
        description: "Training and Performance management",
        item: [
            req("Get Trainings", "GET", "/api/v1/trainings", [], "Get all trainings"),
            req("Create Training", "POST", "/api/v1/trainings", [["title", "Training"]], "Create training"),
            req("Get Training Types", "GET", "/api/v1/trainings/types", [], "Get training types"),
            req("Get Trainers", "GET", "/api/v1/trainings/trainers", [], "Get trainers"),
            req("Get Indicators", "GET", "/api/v1/performance/indicators", [], "Get performance indicators"),
            req("Create Indicator", "POST", "/api/v1/performance/indicators", [["name", "Sales Target"]], "Create indicator"),
            req("Get Appraisals", "GET", "/api/v1/performance/appraisals", [], "Get appraisals"),
            req("Create Appraisal", "POST", "/api/v1/performance/appraisals", [["employee_id", "1"]], "Create appraisal"),
            req("Get Goals", "GET", "/api/v1/performance/goals", [], "Get goals"),
            req("Create Goal", "POST", "/api/v1/performance/goals", [["title", "Q1 Target"]], "Create goal"),
            req("Get Competencies", "GET", "/api/v1/performance/competencies", [], "Get competencies")
        ]
    },
    {
        name: "11. Projects",
        description: "Project management",
        item: [
            req("Get Projects", "GET", "/api/v1/projects", [], "Get all projects"),
            req("Create Project", "POST", "/api/v1/projects", [["name", "New Project"], ["start_date", "2024-01-01"]], "Create project"),
            req("Get Project", "GET", "/api/v1/projects/1", [], "Get project by ID"),
            req("Update Project", "PUT", "/api/v1/projects/1", [["name", "Updated"]], "Update project"),
            req("Delete Project", "DELETE", "/api/v1/projects/1", [], "Delete project"),
            req("Duplicate Project", "POST", "/api/v1/projects/1/duplicate", [], "Duplicate project"),
            req("Get Project Tasks", "GET", "/api/v1/projects/1/tasks", [], "Get tasks"),
            req("Get Project Milestones", "GET", "/api/v1/projects/1/milestones", [], "Get milestones"),
            req("Get Project Members", "GET", "/api/v1/projects/1/members", [], "Get members"),
            req("Add Project Member", "POST", "/api/v1/projects/1/members", [["user_id", "2"]], "Add member"),
            req("Get Project Expenses", "GET", "/api/v1/projects/1/expenses", [], "Get expenses")
        ]
    },
    {
        name: "12. Tasks",
        description: "Task management",
        item: [
            req("Get Tasks", "GET", "/api/v1/tasks", [], "Get all tasks"),
            req("Create Task", "POST", "/api/v1/tasks", [["project_id", "1"], ["title", "Task"]], "Create task"),
            req("Get Task", "GET", "/api/v1/tasks/1", [], "Get task by ID"),
            req("Update Task", "PUT", "/api/v1/tasks/1", [["title", "Updated"]], "Update task"),
            req("Delete Task", "DELETE", "/api/v1/tasks/1", [], "Delete task"),
            req("Complete Task", "POST", "/api/v1/tasks/1/complete", [], "Mark complete"),
            req("Get Task Comments", "GET", "/api/v1/tasks/1/comments", [], "Get comments"),
            req("Add Task Comment", "POST", "/api/v1/tasks/1/comments", [["comment", "Comment"]], "Add comment"),
            req("Start Time Tracker", "POST", "/api/v1/tasks/1/time-tracker/start", [], "Start tracking"),
            req("Stop Time Tracker", "POST", "/api/v1/tasks/1/time-tracker/stop", [], "Stop tracking")
        ]
    },
    {
        name: "13. CRM - Customers",
        description: "Customer management",
        item: [
            req("Get Customers", "GET", "/api/v1/customers", [], "Get all customers"),
            req("Create Customer", "POST", "/api/v1/customers", [["name", "Customer"], ["email", "customer@example.com"]], "Create customer"),
            req("Get Customer", "GET", "/api/v1/customers/1", [], "Get customer by ID"),
            req("Update Customer", "PUT", "/api/v1/customers/1", [["name", "Updated"]], "Update customer"),
            req("Delete Customer", "DELETE", "/api/v1/customers/1", [], "Delete customer"),
            req("Get Customer Invoices", "GET", "/api/v1/customers/1/invoices", [], "Get invoices"),
            req("Get Customer Payments", "GET", "/api/v1/customers/1/payments", [], "Get payments"),
            req("Import Customers", "POST", "/api/v1/import/customers", [], "Import customers"),
            req("Export Customers", "GET", "/api/v1/export/customers", [], "Export customers")
        ]
    },
    {
        name: "14. CRM - Leads",
        description: "Lead management",
        item: [
            req("Get Leads", "GET", "/api/v1/leads", [], "Get all leads"),
            req("Create Lead", "POST", "/api/v1/leads", [["name", "Lead"], ["email", "lead@example.com"]], "Create lead"),
            req("Get Lead", "GET", "/api/v1/leads/1", [], "Get lead by ID"),
            req("Update Lead", "PUT", "/api/v1/leads/1", [["name", "Updated"]], "Update lead"),
            req("Delete Lead", "DELETE", "/api/v1/leads/1", [], "Delete lead"),
            req("Convert Lead", "POST", "/api/v1/leads/1/convert", [], "Convert to customer"),
            req("Get Lead Stages", "GET", "/api/v1/leads/stages", [], "Get stages"),
            req("Get Lead Sources", "GET", "/api/v1/leads/sources", [], "Get sources")
        ]
    },
    {
        name: "15. CRM - Deals",
        description: "Deal management",
        item: [
            req("Get Deals", "GET", "/api/v1/deals", [], "Get all deals"),
            req("Create Deal", "POST", "/api/v1/deals", [["name", "Deal"], ["amount", "10000"]], "Create deal"),
            req("Get Deal", "GET", "/api/v1/deals/1", [], "Get deal by ID"),
            req("Update Deal", "PUT", "/api/v1/deals/1", [["amount", "15000"]], "Update deal"),
            req("Delete Deal", "DELETE", "/api/v1/deals/1", [], "Delete deal"),
            req("Add Deal Product", "POST", "/api/v1/deals/1/products", [["product_id", "1"]], "Add product"),
            req("Get Pipelines", "GET", "/api/v1/deals/pipelines", [], "Get pipelines"),
            req("Get Deal Stages", "GET", "/api/v1/deals/stages", [], "Get stages")
        ]
    },
    {
        name: "16. Accounting - Invoices",
        description: "Invoice management",
        item: [
            req("Get Invoices", "GET", "/api/v1/invoices", [], "Get all invoices"),
            req("Create Invoice", "POST", "/api/v1/invoices", [["customer_id", "1"], ["issue_date", "2024-01-01"]], "Create invoice"),
            req("Get Invoice", "GET", "/api/v1/invoices/1", [], "Get invoice by ID"),
            req("Update Invoice", "PUT", "/api/v1/invoices/1", [["due_date", "2024-02-01"]], "Update invoice"),
            req("Delete Invoice", "DELETE", "/api/v1/invoices/1", [], "Delete invoice"),
            req("Download Invoice PDF", "GET", "/api/v1/invoices/1/pdf", [], "Download PDF"),
            req("Send Invoice", "POST", "/api/v1/invoices/1/send", [], "Send via email"),
            req("Add Invoice Payment", "POST", "/api/v1/invoices/1/payment", [["amount", "1000"]], "Add payment"),
            req("Duplicate Invoice", "POST", "/api/v1/invoices/1/duplicate", [], "Duplicate"),
            req("Export Invoices", "GET", "/api/v1/export/invoices", [], "Export invoices")
        ]
    },
    {
        name: "17. Accounting - Bills",
        description: "Bill management",
        item: [
            req("Get Bills", "GET", "/api/v1/bills", [], "Get all bills"),
            req("Create Bill", "POST", "/api/v1/bills", [["vendor_id", "1"], ["bill_date", "2024-01-01"]], "Create bill"),
            req("Get Bill", "GET", "/api/v1/bills/1", [], "Get bill by ID"),
            req("Update Bill", "PUT", "/api/v1/bills/1", [["bill_date", "2024-01-05"]], "Update bill"),
            req("Delete Bill", "DELETE", "/api/v1/bills/1", [], "Delete bill"),
            req("Download Bill PDF", "GET", "/api/v1/bills/1/pdf", [], "Download PDF"),
            req("Add Bill Payment", "POST", "/api/v1/bills/1/payment", [["amount", "500"]], "Add payment"),
            req("Export Bills", "GET", "/api/v1/export/bills", [], "Export bills")
        ]
    },
    {
        name: "18. Accounting - Vendors",
        description: "Vendor management",
        item: [
            req("Get Vendors", "GET", "/api/v1/vendors", [], "Get all vendors"),
            req("Create Vendor", "POST", "/api/v1/vendors", [["name", "Vendor"], ["email", "vendor@example.com"]], "Create vendor"),
            req("Get Vendor", "GET", "/api/v1/vendors/1", [], "Get vendor by ID"),
            req("Update Vendor", "PUT", "/api/v1/vendors/1", [["name", "Updated"]], "Update vendor"),
            req("Delete Vendor", "DELETE", "/api/v1/vendors/1", [], "Delete vendor"),
            req("Get Vendor Bills", "GET", "/api/v1/vendors/1/bills", [], "Get bills"),
            req("Import Vendors", "POST", "/api/v1/import/vendors", [], "Import vendors"),
            req("Export Vendors", "GET", "/api/v1/export/vendors", [], "Export vendors")
        ]
    },
    {
        name: "19. Accounting - Products",
        description: "Product/Service management",
        item: [
            req("Get Products", "GET", "/api/v1/products", [], "Get all products"),
            req("Create Product", "POST", "/api/v1/products", [["name", "Product"], ["price", "100"]], "Create product"),
            req("Get Product", "GET", "/api/v1/products/1", [], "Get product by ID"),
            req("Update Product", "PUT", "/api/v1/products/1", [["price", "120"]], "Update product"),
            req("Delete Product", "DELETE", "/api/v1/products/1", [], "Delete product"),
            req("Get Product Categories", "GET", "/api/v1/products/categories", [], "Get categories"),
            req("Get Product Units", "GET", "/api/v1/products/units", [], "Get units"),
            req("Get Product Stock", "GET", "/api/v1/products/1/stock", [], "Get stock"),
            req("Import Products", "POST", "/api/v1/import/products", [], "Import products"),
            req("Export Products", "GET", "/api/v1/export/products", [], "Export products")
        ]
    },
    {
        name: "20. Accounting - Finance",
        description: "Expenses, Revenue, Budget, Transactions",
        item: [
            req("Get Expenses", "GET", "/api/v1/expenses", [], "Get all expenses"),
            req("Create Expense", "POST", "/api/v1/expenses", [["amount", "500"]], "Create expense"),
            req("Get Revenues", "GET", "/api/v1/revenue", [], "Get all revenues"),
            req("Create Revenue", "POST", "/api/v1/revenue", [["amount", "5000"]], "Create revenue"),
            req("Get Budgets", "GET", "/api/v1/budgets", [], "Get all budgets"),
            req("Create Budget", "POST", "/api/v1/budgets", [["name", "Q1 Budget"], ["amount", "50000"]], "Create budget"),
            req("Get Chart of Accounts", "GET", "/api/v1/chart-of-accounts", [], "Get chart of accounts"),
            req("Get Journal Entries", "GET", "/api/v1/journal-entries", [], "Get journal entries"),
            req("Get Bank Accounts", "GET", "/api/v1/bank-accounts", [], "Get bank accounts"),
            req("Create Bank Account", "POST", "/api/v1/bank-accounts", [["bank_name", "Bank"], ["account_number", "123456"]], "Create account"),
            req("Get Transactions", "GET", "/api/v1/transactions", [], "Get transactions")
        ]
    },
    {
        name: "21. Inventory - Warehouses",
        description: "Warehouse management",
        item: [
            req("Get Warehouses", "GET", "/api/v1/warehouses", [], "Get all warehouses"),
            req("Create Warehouse", "POST", "/api/v1/warehouses", [["name", "Main Warehouse"], ["location", "City"]], "Create warehouse"),
            req("Get Warehouse", "GET", "/api/v1/warehouses/1", [], "Get warehouse by ID"),
            req("Update Warehouse", "PUT", "/api/v1/warehouses/1", [["name", "Updated"]], "Update warehouse"),
            req("Delete Warehouse", "DELETE", "/api/v1/warehouses/1", [], "Delete warehouse"),
            req("Get Warehouse Products", "GET", "/api/v1/warehouses/1/products", [], "Get products"),
            req("Create Warehouse Transfer", "POST", "/api/v1/warehouses/transfers", [["from_warehouse", "1"], ["to_warehouse", "2"]], "Create transfer")
        ]
    },
    {
        name: "22. Inventory - Purchases",
        description: "Purchase management",
        item: [
            req("Get Purchases", "GET", "/api/v1/purchases", [], "Get all purchases"),
            req("Create Purchase", "POST", "/api/v1/purchases", [["vendor_id", "1"], ["purchase_date", "2024-01-01"]], "Create purchase"),
            req("Get Purchase", "GET", "/api/v1/purchases/1", [], "Get purchase by ID"),
            req("Update Purchase", "PUT", "/api/v1/purchases/1", [["purchase_date", "2024-01-05"]], "Update purchase"),
            req("Delete Purchase", "DELETE", "/api/v1/purchases/1", [], "Delete purchase")
        ]
    },
    {
        name: "23. POS",
        description: "Point of Sale system",
        item: [
            req("Get POS Products", "GET", "/api/v1/pos/products", [], "Get POS products"),
            req("Add to Cart", "POST", "/api/v1/pos/cart/add", [["product_id", "1"], ["quantity", "2"]], "Add to cart"),
            req("Remove from Cart", "POST", "/api/v1/pos/cart/remove", [["product_id", "1"]], "Remove from cart"),
            req("Get Cart", "GET", "/api/v1/pos/cart", [], "Get cart items"),
            req("POS Checkout", "POST", "/api/v1/pos/checkout", [["customer_id", "1"], ["payment_method", "cash"]], "Checkout"),
            req("Get POS Sales", "GET", "/api/v1/pos/sales", [], "Get sales")
        ]
    },
    {
        name: "24. Proposals & Contracts",
        description: "Proposal and contract management",
        item: [
            req("Get Proposals", "GET", "/api/v1/proposals", [], "Get all proposals"),
            req("Create Proposal", "POST", "/api/v1/proposals", [["customer_id", "1"], ["subject", "Proposal"]], "Create proposal"),
            req("Get Proposal", "GET", "/api/v1/proposals/1", [], "Get proposal by ID"),
            req("Download Proposal PDF", "GET", "/api/v1/proposals/1/pdf", [], "Download PDF"),
            req("Send Proposal", "POST", "/api/v1/proposals/1/send", [], "Send proposal"),
            req("Get Contracts", "GET", "/api/v1/contracts", [], "Get all contracts"),
            req("Create Contract", "POST", "/api/v1/contracts", [["customer_id", "1"], ["subject", "Contract"]], "Create contract"),
            req("Get Contract", "GET", "/api/v1/contracts/1", [], "Get contract by ID"),
            req("Sign Contract", "POST", "/api/v1/contracts/1/sign", [], "Sign contract")
        ]
    },
    {
        name: "25. Recruitment",
        description: "Job postings, applications, interviews",
        item: [
            req("Get Jobs", "GET", "/api/v1/jobs", [], "Get all jobs"),
            req("Create Job", "POST", "/api/v1/jobs", [["title", "Developer"], ["department_id", "1"]], "Create job"),
            req("Get Job", "GET", "/api/v1/jobs/1", [], "Get job by ID"),
            req("Get Job Applications", "GET", "/api/v1/jobs/1/applications", [], "Get applications"),
            req("Get Public Jobs", "GET", "/api/v1/jobs/public", [], "Public job listings", false, true),
            req("Apply for Job", "POST", "/api/v1/jobs/1/apply", [["name", "Applicant"], ["email", "applicant@example.com"]], "Apply", false, true),
            req("Get Job Applications", "GET", "/api/v1/job-applications", [], "Get all applications"),
            req("Get Interviews", "GET", "/api/v1/interviews", [], "Get all interviews"),
            req("Schedule Interview", "POST", "/api/v1/interviews", [["application_id", "1"], ["date", "2024-02-01"]], "Schedule interview")
        ]
    },
    {
        name: "26. Support & Tickets",
        description: "Support ticket management",
        item: [
            req("Get Tickets", "GET", "/api/v1/tickets", [], "Get all tickets"),
            req("Create Ticket", "POST", "/api/v1/tickets", [["subject", "Issue"], ["priority", "high"]], "Create ticket"),
            req("Get Ticket", "GET", "/api/v1/tickets/1", [], "Get ticket by ID"),
            req("Update Ticket", "PUT", "/api/v1/tickets/1", [["status", "in_progress"]], "Update ticket"),
            req("Add Ticket Reply", "POST", "/api/v1/tickets/1/reply", [["message", "Reply"]], "Add reply"),
            req("Close Ticket", "POST", "/api/v1/tickets/1/close", [], "Close ticket"),
            req("Get Support Requests", "GET", "/api/v1/support-requests", [], "Get support requests"),
            req("Create Support Request", "POST", "/api/v1/support-requests", [["title", "Support"]], "Create request")
        ]
    },
    {
        name: "27. Assets & Documents",
        description: "Asset and document management",
        item: [
            req("Get Assets", "GET", "/api/v1/assets", [], "Get all assets"),
            req("Create Asset", "POST", "/api/v1/assets", [["name", "Laptop"], ["purchase_date", "2024-01-01"]], "Create asset"),
            req("Get Asset", "GET", "/api/v1/assets/1", [], "Get asset by ID"),
            req("Get Documents", "GET", "/api/v1/documents", [], "Get all documents"),
            req("Upload Document", "POST", "/api/v1/documents", [["title", "Document"]], "Upload document"),
            req("Download Document", "GET", "/api/v1/documents/1/download", [], "Download document")
        ]
    },
    {
        name: "28. Communication",
        description: "Announcements, Meetings, Events",
        item: [
            req("Get Announcements", "GET", "/api/v1/announcements", [], "Get all announcements"),
            req("Create Announcement", "POST", "/api/v1/announcements", [["title", "Announcement"], ["description", "Details"]], "Create announcement"),
            req("Get Meetings", "GET", "/api/v1/meetings", [], "Get all meetings"),
            req("Create Meeting", "POST", "/api/v1/meetings", [["title", "Meeting"], ["date", "2024-02-01"]], "Create meeting"),
            req("Create Zoom Meeting", "POST", "/api/v1/meetings/zoom", [["topic", "Zoom Meeting"]], "Create Zoom meeting"),
            req("Get Events", "GET", "/api/v1/events", [], "Get all events"),
            req("Create Event", "POST", "/api/v1/events", [["title", "Event"], ["date", "2024-03-01"]], "Create event")
        ]
    },
    {
        name: "29. HR Administration",
        description: "Holidays, Policies, Timesheet",
        item: [
            req("Get Holidays", "GET", "/api/v1/holidays", [], "Get all holidays"),
            req("Create Holiday", "POST", "/api/v1/holidays", [["name", "New Year"], ["date", "2024-01-01"]], "Create holiday"),
            req("Get Policies", "GET", "/api/v1/policies", [], "Get all policies"),
            req("Create Policy", "POST", "/api/v1/policies", [["title", "Policy"], ["description", "Details"]], "Create policy"),
            req("Get Timesheets", "GET", "/api/v1/timesheet", [], "Get all timesheets"),
            req("Create Timesheet", "POST", "/api/v1/timesheet", [["project_id", "1"], ["hours", "8"]], "Create timesheet"),
            req("Start Time Tracker", "POST", "/api/v1/time-tracker/start", [["project_id", "1"]], "Start tracker"),
            req("Stop Time Tracker", "POST", "/api/v1/time-tracker/stop", [], "Stop tracker"),
            req("Get Time Tracker History", "GET", "/api/v1/time-tracker/history", [], "Get history")
        ]
    },
    {
        name: "30. Reports",
        description: "Business reports",
        item: [
            req("Income Summary", "GET", "/api/v1/reports/income-summary", [], "Income report"),
            req("Expense Summary", "GET", "/api/v1/reports/expense-summary", [], "Expense report"),
            req("Income vs Expense", "GET", "/api/v1/reports/income-vs-expense", [], "Comparison"),
            req("Profit & Loss", "GET", "/api/v1/reports/profit-loss", [], "P&L report"),
            req("Balance Sheet", "GET", "/api/v1/reports/balance-sheet", [], "Balance sheet"),
            req("Attendance Report", "GET", "/api/v1/reports/attendance", [], "Attendance"),
            req("Payroll Report", "GET", "/api/v1/reports/payroll", [], "Payroll"),
            req("Project Report", "GET", "/api/v1/reports/project", [], "Projects"),
            req("Lead Report", "GET", "/api/v1/reports/lead", [], "Leads"),
            req("Deal Report", "GET", "/api/v1/reports/deal", [], "Deals")
        ]
    },
    {
        name: "31. Branches",
        description: "Branch management",
        item: [
            req("Get Branches", "GET", "/api/v1/branches", [], "Get all branches"),
            req("Create Branch", "POST", "/api/v1/branches", [["name", "Branch"], ["address", "Address"]], "Create branch"),
            req("Get Branch", "GET", "/api/v1/branches/1", [], "Get branch by ID"),
            req("Update Branch", "PUT", "/api/v1/branches/1", [["name", "Updated"]], "Update branch"),
            req("Delete Branch", "DELETE", "/api/v1/branches/1", [], "Delete branch")
        ]
    },
    {
        name: "32. Users & Roles",
        description: "User and role management",
        item: [
            req("Get Users", "GET", "/api/v1/users", [], "Get all users"),
            req("Create User", "POST", "/api/v1/users", [["name", "User"], ["email", "user@example.com"], ["password", "pass"]], "Create user"),
            req("Get User", "GET", "/api/v1/users/1", [], "Get user by ID"),
            req("Update User", "PUT", "/api/v1/users/1", [["name", "Updated"]], "Update user"),
            req("Delete User", "DELETE", "/api/v1/users/1", [], "Delete user"),
            req("Reset User Password", "POST", "/api/v1/users/1/reset-password", [["password", "newpass"]], "Reset password"),
            req("Get Roles", "GET", "/api/v1/roles", [], "Get all roles"),
            req("Create Role", "POST", "/api/v1/roles", [["name", "Manager"]], "Create role"),
            req("Get Role", "GET", "/api/v1/roles/1", [], "Get role by ID"),
            req("Get Permissions", "GET", "/api/v1/permissions", [], "Get permissions")
        ]
    },
    {
        name: "33. Settings",
        description: "System settings",
        item: [
            req("Get Settings", "GET", "/api/v1/settings", [], "Get all settings"),
            req("Update Settings", "PUT", "/api/v1/settings", [["site_name", "My ERP"]], "Update settings"),
            req("Get Company Settings", "GET", "/api/v1/settings/company", [], "Get company settings"),
            req("Update Company Settings", "PUT", "/api/v1/settings/company", [["company_name", "My Company"]], "Update company"),
            req("Get Email Settings", "GET", "/api/v1/settings/email", [], "Get email settings"),
            req("Get Payment Settings", "GET", "/api/v1/settings/payment", [], "Get payment settings")
        ]
    },
    {
        name: "34. Notifications & Search",
        description: "Notifications and global search",
        item: [
            req("Get Notifications", "GET", "/api/v1/notifications", [], "Get all notifications"),
            req("Mark Notification Read", "POST", "/api/v1/notifications/1/read", [], "Mark as read"),
            req("Mark All Read", "POST", "/api/v1/notifications/read-all", [], "Mark all read"),
            req("Delete Notification", "DELETE", "/api/v1/notifications/1", [], "Delete notification"),
            req("Global Search", "GET", "/api/v1/search?q=keyword", [], "Search across system")
        ]
    }
];

collection.item = folders;

// Write to file
fs.writeFileSync('ERP_API_COLLECTION_COMPLETE.json', JSON.stringify(collection, null, 2), 'utf8');

// Calculate stats
const totalRequests = folders.reduce((sum, folder) => sum + folder.item.length, 0);

console.log('✅ Postman collection generated successfully!');
console.log('📁 File: ERP_API_COLLECTION_COMPLETE.json');
console.log(`📊 Total folders: ${folders.length}`);
console.log(`📋 Total requests: ${totalRequests}`);
console.log('\n🎯 Import this file into Postman to start using the API!');
console.log('💡 Set the base_url variable to your server URL');
console.log('🔐 Login first to get the authentication token');
