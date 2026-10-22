/**
 * Postman Collection Generator for ERP API
 * Run: node generate_postman_collection.js
 */

const fs = require('fs');

const baseUrl = "{{base_url}}";
const apiPrefix = "/api/v1";

// Helper to create request object
function createRequest(method, path, body = null, description = "", auth = true) {
    const request = {
        method: method.toUpperCase(),
        header: [
            { key: "Accept", value: "application/json" },
            { key: "Content-Type", value: "application/json" }
        ],
        url: {
            raw: `${baseUrl}${apiPrefix}${path}`,
            host: [baseUrl],
            path: ["api", "v1", ...path.split('/').filter(p => p)]
        }
    };
    
    if (!auth) {
        request.auth = { type: "noauth" };
    }
    
    if (body) {
        request.body = {
            mode: "raw",
            raw: JSON.stringify(body, null, 4)
        };
    }
    
    if (description) {
        request.description = description;
    }
    
    return request;
}

// Create item (endpoint)
function createItem(name, method, path, body = null, description = "", auth = true) {
    return {
        name: name,
        request: createRequest(method, path, body, description, auth)
    };
}

// Create folder
function createFolder(name, description, items) {
    return {
        name: name,
        description: description,
        item: items
    };
}

// ==================== Define All API Endpoints ====================

const collection = {
    info: {
        _postman_id: "erp-api-complete-collection",
        name: "ERP System - Complete API Collection",
        description: `# ERP System API Documentation

## Overview
Complete REST API for the ERP System covering HRM, CRM, Accounting, Projects, and more.

## Authentication
This API uses **Laravel Sanctum** for authentication.

### Getting Started
1. Call the **Login** endpoint with your credentials
2. Copy the \`token\` from the response
3. The token is automatically saved to the collection variable
4. All subsequent requests will use this token

## Base URL
\`${baseUrl}/api/v1\`

## Response Format
\`\`\`json
{
    "is_success": true,
    "message": "Success message",
    "data": { ... }
}
\`\`\`

## HTTP Status Codes
- **200**: Success
- **201**: Created
- **400**: Bad Request
- **401**: Unauthorized
- **403**: Forbidden
- **404**: Not Found
- **422**: Validation Error
- **500**: Server Error`,
        schema: "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    auth: {
        type: "bearer",
        bearer: [{ key: "token", value: "{{token}}", type: "string" }]
    },
    variable: [
        { key: "base_url", value: "http://127.0.0.1:8000", type: "string" },
        { key: "token", value: "", type: "string" }
    ],
    item: []
};

// ==================== 1. Authentication ====================
collection.item.push(createFolder("01. Authentication", "User authentication and profile management", [
    {
        name: "Login",
        event: [{
            listen: "test",
            script: {
                exec: [
                    "var jsonData = pm.response.json();",
                    "if (jsonData.is_success && jsonData.data && jsonData.data.token) {",
                    "    pm.collectionVariables.set('token', jsonData.data.token);",
                    "    console.log('Token saved successfully!');",
                    "}"
                ],
                type: "text/javascript"
            }
        }],
        request: createRequest("POST", "/login", { email: "admin@example.com", password: "password" }, "Authenticate and get access token", false)
    },
    createItem("Register", "POST", "/register", { name: "John Doe", email: "john@example.com", password: "password123" }, "Register new company", false),
    createItem("Forgot Password", "POST", "/forgot-password", { email: "user@example.com" }, "Request password reset link", false),
    createItem("Reset Password", "POST", "/reset-password", { email: "user@example.com", token: "reset_token", password: "newpassword", password_confirmation: "newpassword" }, "Reset password with token", false),
    createItem("Logout", "POST", "/logout", null, "Revoke all tokens"),
    createItem("Get Profile", "GET", "/profile", null, "Get authenticated user profile"),
    createItem("Update Profile", "PUT", "/profile", { name: "Updated Name", email: "updated@example.com", phone: "+1234567890" }, "Update user profile"),
    createItem("Change Password", "POST", "/change-password", { current_password: "oldpass", new_password: "newpass123" }, "Change user password"),
    {
        name: "Upload Avatar",
        request: {
            method: "POST",
            header: [{ key: "Accept", value: "application/json" }],
            body: { mode: "formdata", formdata: [{ key: "avatar", type: "file", src: "" }] },
            url: { raw: `${baseUrl}${apiPrefix}/upload-avatar`, host: [baseUrl], path: ["api", "v1", "upload-avatar"] }
        }
    }
]));

// ==================== 2. Dashboard ====================
collection.item.push(createFolder("02. Dashboard", "Dashboard statistics and analytics", [
    createItem("Main Dashboard", "GET", "/dashboard"),
    createItem("Dashboard Stats", "GET", "/dashboard/stats"),
    createItem("HRM Dashboard", "GET", "/dashboard/hrm"),
    createItem("CRM Dashboard", "GET", "/dashboard/crm"),
    createItem("Project Dashboard", "GET", "/dashboard/project"),
    createItem("Account Dashboard", "GET", "/dashboard/account"),
    createItem("POS Dashboard", "GET", "/dashboard/pos")
]));

// ==================== 3. Employees ====================
collection.item.push(createFolder("03. HRM - Employees", "Employee management", [
    createItem("List Employees", "GET", "/employees"),
    createItem("Create Employee", "POST", "/employees", {
        name: "John Employee", email: "john.emp@example.com", password: "password123",
        dob: "1990-01-15", gender: "male", phone: "+1234567890", address: "123 Street",
        branch_id: 1, department_id: 1, designation_id: 1, company_doj: "2024-01-01"
    }),
    createItem("Get Employee", "GET", "/employees/1"),
    createItem("Update Employee", "PUT", "/employees/1", { name: "Updated Name", phone: "+9876543210" }),
    createItem("Delete Employee", "DELETE", "/employees/1"),
    createItem("Get Employee Documents", "GET", "/employees/1/documents"),
    createItem("Get Employee Salary", "GET", "/employees/1/salary"),
    createItem("Set Employee Salary", "POST", "/employees/1/salary", { salary_type: 1, salary: 5000 }),
    createItem("Get Employee Attendance", "GET", "/employees/1/attendance"),
    createItem("Get Employee Leaves", "GET", "/employees/1/leaves"),
    createItem("Get Employee Payslips", "GET", "/employees/1/payslips"),
    createItem("Import Employees", "POST", "/import/employees"),
    createItem("Export Employees", "GET", "/export/employees")
]));

// ==================== 4. Attendance ====================
collection.item.push(createFolder("04. HRM - Attendance", "Attendance tracking", [
    createItem("List Attendance", "GET", "/attendance"),
    createItem("Mark Attendance", "POST", "/attendance", { employee_id: 1, date: "2024-01-15", status: "Present", clock_in: "09:00:00", clock_out: "18:00:00" }),
    createItem("Bulk Attendance", "POST", "/attendance/bulk", { date: "2024-01-15", attendance: [{ employee_id: 1, status: "Present", clock_in: "09:00", clock_out: "18:00" }] }),
    createItem("Get Attendance", "GET", "/attendance/1"),
    createItem("Update Attendance", "PUT", "/attendance/1", { status: "Present", clock_in: "08:30:00", clock_out: "17:30:00" }),
    createItem("Delete Attendance", "DELETE", "/attendance/1"),
    createItem("Employee Attendance History", "GET", "/attendance/employee/1")
]));

// ==================== 5. Leaves ====================
collection.item.push(createFolder("05. HRM - Leaves", "Leave management", [
    createItem("List Leaves", "GET", "/leaves"),
    createItem("Create Leave", "POST", "/leaves", { employee_id: 1, leave_type_id: 1, start_date: "2024-01-20", end_date: "2024-01-22", leave_reason: "Family emergency" }),
    createItem("Get Leave", "GET", "/leaves/1"),
    createItem("Update Leave", "PUT", "/leaves/1", { leave_reason: "Updated reason" }),
    createItem("Delete Leave", "DELETE", "/leaves/1"),
    createItem("Approve Leave", "POST", "/leaves/1/approve"),
    createItem("Reject Leave", "POST", "/leaves/1/reject"),
    createItem("Get Leave Types", "GET", "/leaves/types"),
    createItem("Create Leave Type", "POST", "/leaves/types", { title: "Sick Leave", days: 10 })
]));

// ==================== 6. Payroll ====================
collection.item.push(createFolder("06. HRM - Payroll", "Payroll management", [
    createItem("List Payslips", "GET", "/payroll/payslips"),
    createItem("Create Payslip", "POST", "/payroll/payslips", { employee_id: 1, salary_month: "2024-01" }),
    createItem("Get Payslip", "GET", "/payroll/payslips/1"),
    createItem("Update Payslip", "PUT", "/payroll/payslips/1"),
    createItem("Delete Payslip", "DELETE", "/payroll/payslips/1"),
    createItem("Download Payslip PDF", "GET", "/payroll/payslips/1/pdf"),
    createItem("Send Payslip", "POST", "/payroll/payslips/1/send"),
    createItem("Get Allowances", "GET", "/payroll/allowances"),
    createItem("Get Deductions", "GET", "/payroll/deductions"),
    createItem("Get Commissions", "GET", "/payroll/commissions"),
    createItem("Get Loans", "GET", "/payroll/loans"),
    createItem("Get Overtime", "GET", "/payroll/overtime")
]));

// ==================== 7. Departments ====================
collection.item.push(createFolder("07. HRM - Departments", "Department management", [
    createItem("List Departments", "GET", "/departments"),
    createItem("Create Department", "POST", "/departments", { name: "Engineering", branch_id: 1 }),
    createItem("Get Department", "GET", "/departments/1"),
    createItem("Update Department", "PUT", "/departments/1", { name: "Updated Department" }),
    createItem("Delete Department", "DELETE", "/departments/1"),
    createItem("Get Department Employees", "GET", "/departments/1/employees")
]));

// ==================== 8. Designations ====================
collection.item.push(createFolder("08. HRM - Designations", "Designation management", [
    createItem("List Designations", "GET", "/designations"),
    createItem("Create Designation", "POST", "/designations", { name: "Software Engineer", department_id: 1 }),
    createItem("Get Designation", "GET", "/designations/1"),
    createItem("Update Designation", "PUT", "/designations/1", { name: "Senior Software Engineer" }),
    createItem("Delete Designation", "DELETE", "/designations/1")
]));

// ==================== 9. Awards ====================
collection.item.push(createFolder("09. HRM - Awards", "Employee awards", [
    createItem("List Awards", "GET", "/awards"),
    createItem("Create Award", "POST", "/awards", { employee_id: 1, award_type: 1, date: "2024-01-15", gift: "Cash Bonus", description: "Employee of the month" }),
    createItem("Get Award", "GET", "/awards/1"),
    createItem("Update Award", "PUT", "/awards/1", { gift: "Updated Gift" }),
    createItem("Delete Award", "DELETE", "/awards/1")
]));

// ==================== 10. Complaints ====================
collection.item.push(createFolder("10. HRM - Complaints", "Employee complaints", [
    createItem("List Complaints", "GET", "/complaints"),
    createItem("Create Complaint", "POST", "/complaints", { complaint_from: 1, complaint_against: 2, title: "Issue", complaint_date: "2024-01-15", description: "Description" }),
    createItem("Get Complaint", "GET", "/complaints/1"),
    createItem("Update Complaint", "PUT", "/complaints/1", { title: "Updated" }),
    createItem("Delete Complaint", "DELETE", "/complaints/1")
]));

// ==================== 11. Warnings ====================
collection.item.push(createFolder("11. HRM - Warnings", "Employee warnings", [
    createItem("List Warnings", "GET", "/warnings"),
    createItem("Create Warning", "POST", "/warnings", { warning_to: 1, warning_by: 2, subject: "Late", warning_date: "2024-01-15", description: "Warning" }),
    createItem("Get Warning", "GET", "/warnings/1"),
    createItem("Update Warning", "PUT", "/warnings/1", { subject: "Updated" }),
    createItem("Delete Warning", "DELETE", "/warnings/1")
]));

// ==================== 12. Terminations ====================
collection.item.push(createFolder("12. HRM - Terminations", "Employee terminations", [
    createItem("List Terminations", "GET", "/terminations"),
    createItem("Create Termination", "POST", "/terminations", { employee_id: 1, termination_type: 1, notice_date: "2024-01-15", termination_date: "2024-02-15", description: "Termination" }),
    createItem("Get Termination", "GET", "/terminations/1"),
    createItem("Update Termination", "PUT", "/terminations/1"),
    createItem("Delete Termination", "DELETE", "/terminations/1")
]));

// ==================== 13. Promotions ====================
collection.item.push(createFolder("13. HRM - Promotions", "Employee promotions", [
    createItem("List Promotions", "GET", "/promotions"),
    createItem("Create Promotion", "POST", "/promotions", { employee_id: 1, designation_id: 2, promotion_title: "Promoted", promotion_date: "2024-01-15" }),
    createItem("Get Promotion", "GET", "/promotions/1"),
    createItem("Update Promotion", "PUT", "/promotions/1"),
    createItem("Delete Promotion", "DELETE", "/promotions/1")
]));

// ==================== 14. Transfers ====================
collection.item.push(createFolder("14. HRM - Transfers", "Employee transfers", [
    createItem("List Transfers", "GET", "/transfers"),
    createItem("Create Transfer", "POST", "/transfers", { employee_id: 1, branch_id: 2, department_id: 2, transfer_date: "2024-01-15", description: "Transfer" }),
    createItem("Get Transfer", "GET", "/transfers/1"),
    createItem("Update Transfer", "PUT", "/transfers/1"),
    createItem("Delete Transfer", "DELETE", "/transfers/1")
]));

// ==================== 15. Resignations ====================
collection.item.push(createFolder("15. HRM - Resignations", "Employee resignations", [
    createItem("List Resignations", "GET", "/resignations"),
    createItem("Create Resignation", "POST", "/resignations", { employee_id: 1, notice_date: "2024-01-15", resignation_date: "2024-02-15", description: "Resignation" }),
    createItem("Get Resignation", "GET", "/resignations/1"),
    createItem("Update Resignation", "PUT", "/resignations/1"),
    createItem("Delete Resignation", "DELETE", "/resignations/1")
]));

// ==================== 16. Travels ====================
collection.item.push(createFolder("16. HRM - Travels", "Employee travels", [
    createItem("List Travels", "GET", "/travels"),
    createItem("Create Travel", "POST", "/travels", { employee_id: 1, start_date: "2024-01-20", end_date: "2024-01-25", purpose_of_visit: "Business", place_of_visit: "New York", description: "Travel" }),
    createItem("Get Travel", "GET", "/travels/1"),
    createItem("Update Travel", "PUT", "/travels/1"),
    createItem("Delete Travel", "DELETE", "/travels/1")
]));

// ==================== 17. Training ====================
collection.item.push(createFolder("17. HRM - Training", "Training management", [
    createItem("List Trainings", "GET", "/trainings"),
    createItem("Create Training", "POST", "/trainings", { branch: 1, trainer_option: "Internal", training_type: 1, trainer: 1, employee: 1, training_cost: 500, start_date: "2024-01-20", end_date: "2024-01-25" }),
    createItem("Get Training", "GET", "/trainings/1"),
    createItem("Update Training", "PUT", "/trainings/1"),
    createItem("Delete Training", "DELETE", "/trainings/1"),
    createItem("Get Training Types", "GET", "/trainings/types"),
    createItem("Get Trainers", "GET", "/trainings/trainers")
]));

// ==================== 18. Performance ====================
collection.item.push(createFolder("18. HRM - Performance", "Performance management", [
    createItem("List Indicators", "GET", "/performance/indicators"),
    createItem("Create Indicator", "POST", "/performance/indicators", { branch: 1, department: 1, designation: 1, rating: 4 }),
    createItem("List Appraisals", "GET", "/performance/appraisals"),
    createItem("Create Appraisal", "POST", "/performance/appraisals", { branch: 1, employee: 1, appraisal_date: "2024-01-15" }),
    createItem("List Goals", "GET", "/performance/goals"),
    createItem("Create Goal", "POST", "/performance/goals", { branch: 1, goal_type: 1, subject: "Goal", target_achievement: "100%", start_date: "2024-01-01", end_date: "2024-12-31" }),
    createItem("List Competencies", "GET", "/performance/competencies")
]));

// ==================== 19. Projects ====================
collection.item.push(createFolder("19. Project Management", "Project management", [
    createItem("List Projects", "GET", "/projects"),
    createItem("Create Project", "POST", "/projects", { project_name: "New Project", start_date: "2024-01-01", end_date: "2024-06-30", budget: 50000, description: "Description", status: "Not Started" }),
    createItem("Get Project", "GET", "/projects/1"),
    createItem("Update Project", "PUT", "/projects/1", { project_name: "Updated Project" }),
    createItem("Delete Project", "DELETE", "/projects/1"),
    createItem("Duplicate Project", "POST", "/projects/1/duplicate"),
    createItem("Get Project Tasks", "GET", "/projects/1/tasks"),
    createItem("Get Project Milestones", "GET", "/projects/1/milestones"),
    createItem("Get Project Bugs", "GET", "/projects/1/bugs"),
    createItem("Get Project Files", "GET", "/projects/1/files"),
    createItem("Get Project Members", "GET", "/projects/1/members"),
    createItem("Add Project Member", "POST", "/projects/1/members", { user_id: 2 }),
    createItem("Remove Project Member", "DELETE", "/projects/1/members/2"),
    createItem("Get Project Activity", "GET", "/projects/1/activity"),
    createItem("Get Project Timesheet", "GET", "/projects/1/timesheet"),
    createItem("Get Project Expenses", "GET", "/projects/1/expenses"),
    createItem("Create Project Expense", "POST", "/projects/1/expenses", { expense_category_id: 1, amount: 500, date: "2024-01-15", description: "Expense" })
]));

// ==================== 20. Tasks ====================
collection.item.push(createFolder("20. Tasks", "Task management", [
    createItem("List Tasks", "GET", "/tasks"),
    createItem("Create Task", "POST", "/tasks", { project_id: 1, title: "New Task", priority: "medium", start_date: "2024-01-15", due_date: "2024-01-20", description: "Task description" }),
    createItem("Get Task", "GET", "/tasks/1"),
    createItem("Update Task", "PUT", "/tasks/1", { title: "Updated Task" }),
    createItem("Delete Task", "DELETE", "/tasks/1"),
    createItem("Complete Task", "POST", "/tasks/1/complete"),
    createItem("Get Task Comments", "GET", "/tasks/1/comments"),
    createItem("Add Task Comment", "POST", "/tasks/1/comments", { comment: "New comment" }),
    createItem("Get Task Files", "GET", "/tasks/1/files"),
    createItem("Add Task Checklist", "POST", "/tasks/1/checklist", { name: "Checklist item" }),
    createItem("Get Task Time Tracker", "GET", "/tasks/1/time-tracker"),
    createItem("Start Time Tracker", "POST", "/tasks/1/time-tracker/start"),
    createItem("Stop Time Tracker", "POST", "/tasks/1/time-tracker/stop")
]));

// ==================== 21. Milestones ====================
collection.item.push(createFolder("21. Milestones", "Milestone management", [
    createItem("List Milestones", "GET", "/milestones"),
    createItem("Create Milestone", "POST", "/milestones", { project_id: 1, title: "Milestone 1", status: "Incomplete", start_date: "2024-01-01", due_date: "2024-03-31" }),
    createItem("Get Milestone", "GET", "/milestones/1"),
    createItem("Update Milestone", "PUT", "/milestones/1", { title: "Updated Milestone" }),
    createItem("Delete Milestone", "DELETE", "/milestones/1")
]));

// ==================== 22. Bugs ====================
collection.item.push(createFolder("22. Bugs", "Bug tracking", [
    createItem("List Bugs", "GET", "/bugs"),
    createItem("Create Bug", "POST", "/bugs", { project_id: 1, title: "Bug Report", priority: "high", status: "unconfirmed", description: "Bug description" }),
    createItem("Get Bug", "GET", "/bugs/1"),
    createItem("Update Bug", "PUT", "/bugs/1", { status: "confirmed" }),
    createItem("Delete Bug", "DELETE", "/bugs/1"),
    createItem("Get Bug Comments", "GET", "/bugs/1/comments"),
    createItem("Add Bug Comment", "POST", "/bugs/1/comments", { comment: "Bug comment" })
]));

// ==================== 23. Customers ====================
collection.item.push(createFolder("23. CRM - Customers", "Customer management", [
    createItem("List Customers", "GET", "/customers"),
    createItem("Create Customer", "POST", "/customers", { name: "Customer Name", email: "customer@example.com", contact: "+1234567890", billing_name: "Billing Name", billing_address: "123 Street", billing_city: "City", billing_country: "Country" }),
    createItem("Get Customer", "GET", "/customers/1"),
    createItem("Update Customer", "PUT", "/customers/1", { name: "Updated Customer" }),
    createItem("Delete Customer", "DELETE", "/customers/1"),
    createItem("Get Customer Invoices", "GET", "/customers/1/invoices"),
    createItem("Get Customer Payments", "GET", "/customers/1/payments"),
    createItem("Get Customer Deals", "GET", "/customers/1/deals"),
    createItem("Import Customers", "POST", "/import/customers"),
    createItem("Export Customers", "GET", "/export/customers")
]));

// ==================== 24. Leads ====================
collection.item.push(createFolder("24. CRM - Leads", "Lead management", [
    createItem("List Leads", "GET", "/leads"),
    createItem("Create Lead", "POST", "/leads", { name: "Lead Name", email: "lead@example.com", phone: "+1234567890", pipeline_id: 1, stage_id: 1, source_id: 1 }),
    createItem("Get Lead", "GET", "/leads/1"),
    createItem("Update Lead", "PUT", "/leads/1", { name: "Updated Lead" }),
    createItem("Delete Lead", "DELETE", "/leads/1"),
    createItem("Convert Lead", "POST", "/leads/1/convert"),
    createItem("Get Lead Stages", "GET", "/leads/stages"),
    createItem("Get Lead Sources", "GET", "/leads/sources")
]));

// ==================== 25. Deals ====================
collection.item.push(createFolder("25. CRM - Deals", "Deal management", [
    createItem("List Deals", "GET", "/deals"),
    createItem("Create Deal", "POST", "/deals", { name: "Deal Name", pipeline_id: 1, stage_id: 1, price: 10000, phone: "+1234567890" }),
    createItem("Get Deal", "GET", "/deals/1"),
    createItem("Update Deal", "PUT", "/deals/1", { name: "Updated Deal", price: 15000 }),
    createItem("Delete Deal", "DELETE", "/deals/1"),
    createItem("Add Deal Product", "POST", "/deals/1/products", { product_id: 1, quantity: 2 }),
    createItem("Get Deal Attachments", "GET", "/deals/1/attachments"),
    createItem("Get Pipelines", "GET", "/deals/pipelines"),
    createItem("Get Deal Stages", "GET", "/deals/stages")
]));

// ==================== 26. Invoices ====================
collection.item.push(createFolder("26. Accounting - Invoices", "Invoice management", [
    createItem("List Invoices", "GET", "/invoices"),
    createItem("Create Invoice", "POST", "/invoices", { customer_id: 1, issue_date: "2024-01-15", due_date: "2024-02-15", category_id: 1, items: [{ item: "Product", quantity: 2, price: 100 }] }),
    createItem("Get Invoice", "GET", "/invoices/1"),
    createItem("Update Invoice", "PUT", "/invoices/1"),
    createItem("Delete Invoice", "DELETE", "/invoices/1"),
    createItem("Download Invoice PDF", "GET", "/invoices/1/pdf"),
    createItem("Send Invoice", "POST", "/invoices/1/send"),
    createItem("Add Invoice Payment", "POST", "/invoices/1/payment", { amount: 500, date: "2024-01-20", account_id: 1 }),
    createItem("Get Invoice Payments", "GET", "/invoices/1/payments"),
    createItem("Duplicate Invoice", "POST", "/invoices/1/duplicate"),
    createItem("Export Invoices", "GET", "/export/invoices")
]));

// ==================== 27. Bills ====================
collection.item.push(createFolder("27. Accounting - Bills", "Bill management", [
    createItem("List Bills", "GET", "/bills"),
    createItem("Create Bill", "POST", "/bills", { vendor_id: 1, bill_date: "2024-01-15", due_date: "2024-02-15", category_id: 1, items: [{ item: "Service", quantity: 1, price: 500 }] }),
    createItem("Get Bill", "GET", "/bills/1"),
    createItem("Update Bill", "PUT", "/bills/1"),
    createItem("Delete Bill", "DELETE", "/bills/1"),
    createItem("Download Bill PDF", "GET", "/bills/1/pdf"),
    createItem("Add Bill Payment", "POST", "/bills/1/payment", { amount: 300, date: "2024-01-20", account_id: 1 }),
    createItem("Export Bills", "GET", "/export/bills")
]));

// ==================== 28. Vendors ====================
collection.item.push(createFolder("28. Accounting - Vendors", "Vendor management", [
    createItem("List Vendors", "GET", "/vendors"),
    createItem("Create Vendor", "POST", "/vendors", { name: "Vendor Name", email: "vendor@example.com", contact: "+1234567890", billing_name: "Billing Name", billing_address: "123 Street" }),
    createItem("Get Vendor", "GET", "/vendors/1"),
    createItem("Update Vendor", "PUT", "/vendors/1", { name: "Updated Vendor" }),
    createItem("Delete Vendor", "DELETE", "/vendors/1"),
    createItem("Get Vendor Bills", "GET", "/vendors/1/bills"),
    createItem("Get Vendor Payments", "GET", "/vendors/1/payments"),
    createItem("Import Vendors", "POST", "/import/vendors"),
    createItem("Export Vendors", "GET", "/export/vendors")
]));

// ==================== 29. Products ====================
collection.item.push(createFolder("29. Accounting - Products", "Product/Service management", [
    createItem("List Products", "GET", "/products"),
    createItem("Create Product", "POST", "/products", { name: "Product Name", sku: "SKU001", sale_price: 100, purchase_price: 80, category_id: 1, unit_id: 1, type: "product" }),
    createItem("Get Product", "GET", "/products/1"),
    createItem("Update Product", "PUT", "/products/1", { name: "Updated Product", sale_price: 120 }),
    createItem("Delete Product", "DELETE", "/products/1"),
    createItem("Get Product Categories", "GET", "/products/categories"),
    createItem("Get Product Units", "GET", "/products/units"),
    createItem("Get Product Stock", "GET", "/products/1/stock"),
    createItem("Import Products", "POST", "/import/products"),
    createItem("Export Products", "GET", "/export/products")
]));

// ==================== 30. Expenses ====================
collection.item.push(createFolder("30. Accounting - Expenses", "Expense management", [
    createItem("List Expenses", "GET", "/expenses"),
    createItem("Create Expense", "POST", "/expenses", { account_id: 1, amount: 500, date: "2024-01-15", category_id: 1, description: "Office supplies" }),
    createItem("Get Expense", "GET", "/expenses/1"),
    createItem("Update Expense", "PUT", "/expenses/1", { amount: 600 }),
    createItem("Delete Expense", "DELETE", "/expenses/1")
]));

// ==================== 31. Revenue ====================
collection.item.push(createFolder("31. Accounting - Revenue", "Revenue management", [
    createItem("List Revenue", "GET", "/revenue"),
    createItem("Create Revenue", "POST", "/revenue", { account_id: 1, amount: 1000, date: "2024-01-15", category_id: 1, description: "Sales revenue" }),
    createItem("Get Revenue", "GET", "/revenue/1"),
    createItem("Update Revenue", "PUT", "/revenue/1", { amount: 1200 }),
    createItem("Delete Revenue", "DELETE", "/revenue/1")
]));

// ==================== 32. Budget ====================
collection.item.push(createFolder("32. Accounting - Budget", "Budget management", [
    createItem("List Budgets", "GET", "/budgets"),
    createItem("Create Budget", "POST", "/budgets", { name: "Q1 Budget", from: "2024-01-01", to: "2024-03-31" }),
    createItem("Get Budget", "GET", "/budgets/1"),
    createItem("Update Budget", "PUT", "/budgets/1", { name: "Updated Budget" }),
    createItem("Delete Budget", "DELETE", "/budgets/1")
]));

// ==================== 33. Chart of Accounts ====================
collection.item.push(createFolder("33. Accounting - Chart of Accounts", "Chart of accounts management", [
    createItem("List Chart of Accounts", "GET", "/chart-of-accounts"),
    createItem("Create Account", "POST", "/chart-of-accounts", { name: "Cash", code: "1001", type: 1, sub_type: 1, is_enabled: 1 }),
    createItem("Get Account", "GET", "/chart-of-accounts/1"),
    createItem("Update Account", "PUT", "/chart-of-accounts/1", { name: "Updated Cash" }),
    createItem("Delete Account", "DELETE", "/chart-of-accounts/1")
]));

// ==================== 34. Journal Entries ====================
collection.item.push(createFolder("34. Accounting - Journal Entries", "Journal entry management", [
    createItem("List Journal Entries", "GET", "/journal-entries"),
    createItem("Create Journal Entry", "POST", "/journal-entries", { date: "2024-01-15", description: "Manual entry", accounts: [{ account_id: 1, debit: 1000, credit: 0 }, { account_id: 2, debit: 0, credit: 1000 }] }),
    createItem("Get Journal Entry", "GET", "/journal-entries/1"),
    createItem("Update Journal Entry", "PUT", "/journal-entries/1"),
    createItem("Delete Journal Entry", "DELETE", "/journal-entries/1")
]));

// ==================== 35. Bank Accounts ====================
collection.item.push(createFolder("35. Accounting - Bank Accounts", "Bank account management", [
    createItem("List Bank Accounts", "GET", "/bank-accounts"),
    createItem("Create Bank Account", "POST", "/bank-accounts", { holder_name: "Company Name", bank_name: "Bank Name", account_number: "123456789", opening_balance: 10000 }),
    createItem("Get Bank Account", "GET", "/bank-accounts/1"),
    createItem("Update Bank Account", "PUT", "/bank-accounts/1", { holder_name: "Updated Name" }),
    createItem("Delete Bank Account", "DELETE", "/bank-accounts/1")
]));

// ==================== 36. Transactions ====================
collection.item.push(createFolder("36. Accounting - Transactions", "Transaction management", [
    createItem("List Transactions", "GET", "/transactions"),
    createItem("Create Transaction", "POST", "/transactions", { account_id: 1, type: "income", amount: 500, date: "2024-01-15", category_id: 1 }),
    createItem("Get Transaction", "GET", "/transactions/1"),
    createItem("Update Transaction", "PUT", "/transactions/1"),
    createItem("Delete Transaction", "DELETE", "/transactions/1")
]));

// ==================== 37. Warehouses ====================
collection.item.push(createFolder("37. Inventory - Warehouses", "Warehouse management", [
    createItem("List Warehouses", "GET", "/warehouses"),
    createItem("Create Warehouse", "POST", "/warehouses", { name: "Main Warehouse", address: "123 Street", city: "City", zip_code: "12345" }),
    createItem("Get Warehouse", "GET", "/warehouses/1"),
    createItem("Update Warehouse", "PUT", "/warehouses/1", { name: "Updated Warehouse" }),
    createItem("Delete Warehouse", "DELETE", "/warehouses/1"),
    createItem("Get Warehouse Products", "GET", "/warehouses/1/products"),
    createItem("Create Warehouse Transfer", "POST", "/warehouses/transfers", { from_warehouse: 1, to_warehouse: 2, product_id: 1, quantity: 10 })
]));

// ==================== 38. Purchases ====================
collection.item.push(createFolder("38. Inventory - Purchases", "Purchase management", [
    createItem("List Purchases", "GET", "/purchases"),
    createItem("Create Purchase", "POST", "/purchases", { vendor_id: 1, warehouse_id: 1, purchase_date: "2024-01-15", items: [{ product_id: 1, quantity: 10, price: 50 }] }),
    createItem("Get Purchase", "GET", "/purchases/1"),
    createItem("Update Purchase", "PUT", "/purchases/1"),
    createItem("Delete Purchase", "DELETE", "/purchases/1")
]));

// ==================== 39. POS ====================
collection.item.push(createFolder("39. POS", "Point of Sale", [
    createItem("Get POS Products", "GET", "/pos/products"),
    createItem("Add to Cart", "POST", "/pos/cart/add", { product_id: 1, quantity: 2 }),
    createItem("Remove from Cart", "POST", "/pos/cart/remove", { product_id: 1 }),
    createItem("Get Cart", "GET", "/pos/cart"),
    createItem("Checkout", "POST", "/pos/checkout", { customer_id: 1, payment_type: "cash" }),
    createItem("Get POS Sales", "GET", "/pos/sales")
]));

// ==================== 40. Proposals ====================
collection.item.push(createFolder("40. Proposals", "Proposal management", [
    createItem("List Proposals", "GET", "/proposals"),
    createItem("Create Proposal", "POST", "/proposals", { customer_id: 1, issue_date: "2024-01-15", category_id: 1, items: [{ item: "Service", quantity: 1, price: 1000 }] }),
    createItem("Get Proposal", "GET", "/proposals/1"),
    createItem("Update Proposal", "PUT", "/proposals/1"),
    createItem("Delete Proposal", "DELETE", "/proposals/1"),
    createItem("Download Proposal PDF", "GET", "/proposals/1/pdf"),
    createItem("Send Proposal", "POST", "/proposals/1/send")
]));

// ==================== 41. Contracts ====================
collection.item.push(createFolder("41. Contracts", "Contract management", [
    createItem("List Contracts", "GET", "/contracts"),
    createItem("Create Contract", "POST", "/contracts", { customer_id: 1, subject: "Service Contract", value: 5000, type: 1, start_date: "2024-01-01", end_date: "2024-12-31" }),
    createItem("Get Contract", "GET", "/contracts/1"),
    createItem("Update Contract", "PUT", "/contracts/1", { subject: "Updated Contract" }),
    createItem("Delete Contract", "DELETE", "/contracts/1"),
    createItem("Sign Contract", "POST", "/contracts/1/sign")
]));

// ==================== 42. Jobs ====================
collection.item.push(createFolder("42. Recruitment - Jobs", "Job management", [
    createItem("List Jobs", "GET", "/jobs"),
    createItem("Create Job", "POST", "/jobs", { title: "Software Developer", branch: 1, category: 1, skill: "PHP, Laravel", position: 2, status: "active", start_date: "2024-01-01", end_date: "2024-03-31" }),
    createItem("Get Job", "GET", "/jobs/1"),
    createItem("Update Job", "PUT", "/jobs/1", { title: "Senior Developer" }),
    createItem("Delete Job", "DELETE", "/jobs/1"),
    createItem("Get Job Applications", "GET", "/jobs/1/applications"),
    createItem("Get Job Categories", "GET", "/jobs/categories"),
    createItem("Public Jobs", "GET", "/jobs/public"),
    createItem("Public Job Detail", "GET", "/jobs/1/public"),
    createItem("Apply for Job", "POST", "/jobs/1/apply")
]));

// ==================== 43. Job Applications ====================
collection.item.push(createFolder("43. Recruitment - Applications", "Job application management", [
    createItem("List Applications", "GET", "/job-applications"),
    createItem("Get Application", "GET", "/job-applications/1"),
    createItem("Update Application", "PUT", "/job-applications/1", { rating: 4 }),
    createItem("Delete Application", "DELETE", "/job-applications/1"),
    createItem("Move Application Stage", "POST", "/job-applications/1/move-stage", { stage_id: 2 })
]));

// ==================== 44. Interviews ====================
collection.item.push(createFolder("44. Recruitment - Interviews", "Interview management", [
    createItem("List Interviews", "GET", "/interviews"),
    createItem("Schedule Interview", "POST", "/interviews", { candidate: 1, employee: 1, date: "2024-01-20", time: "10:00", comment: "Technical interview" }),
    createItem("Get Interview", "GET", "/interviews/1"),
    createItem("Update Interview", "PUT", "/interviews/1"),
    createItem("Delete Interview", "DELETE", "/interviews/1")
]));

// ==================== 45. Tickets ====================
collection.item.push(createFolder("45. Support - Tickets", "Support ticket management", [
    createItem("List Tickets", "GET", "/tickets"),
    createItem("Create Ticket", "POST", "/tickets", { subject: "Support Request", category: 1, priority: "medium", description: "Issue description" }),
    createItem("Get Ticket", "GET", "/tickets/1"),
    createItem("Update Ticket", "PUT", "/tickets/1", { status: "in_progress" }),
    createItem("Delete Ticket", "DELETE", "/tickets/1"),
    createItem("Add Ticket Reply", "POST", "/tickets/1/reply", { description: "Reply message" }),
    createItem("Get Ticket Replies", "GET", "/tickets/1/replies"),
    createItem("Close Ticket", "POST", "/tickets/1/close")
]));

// ==================== 46. Support Requests ====================
collection.item.push(createFolder("46. Support Requests", "Support request management", [
    createItem("List Support Requests", "GET", "/support-requests"),
    createItem("Create Support Request", "POST", "/support-requests", { subject: "Request", priority: "medium", description: "Description" }),
    createItem("Get Support Request", "GET", "/support-requests/1"),
    createItem("Update Support Request", "PUT", "/support-requests/1"),
    createItem("Update Support Request Status", "POST", "/support-requests/1/update-status", { status: "resolved" })
]));

// ==================== 47. Assets ====================
collection.item.push(createFolder("47. Assets", "Asset management", [
    createItem("List Assets", "GET", "/assets"),
    createItem("Create Asset", "POST", "/assets", { name: "Laptop", purchase_date: "2024-01-15", supported_date: "2025-01-15", amount: 1500, description: "Company laptop" }),
    createItem("Get Asset", "GET", "/assets/1"),
    createItem("Update Asset", "PUT", "/assets/1", { name: "Updated Laptop" }),
    createItem("Delete Asset", "DELETE", "/assets/1")
]));

// ==================== 48. Documents ====================
collection.item.push(createFolder("48. Documents", "Document management", [
    createItem("List Documents", "GET", "/documents"),
    createItem("Get Document", "GET", "/documents/1"),
    createItem("Delete Document", "DELETE", "/documents/1"),
    createItem("Download Document", "GET", "/documents/1/download")
]));

// ==================== 49. Announcements ====================
collection.item.push(createFolder("49. Announcements", "Announcement management", [
    createItem("List Announcements", "GET", "/announcements"),
    createItem("Create Announcement", "POST", "/announcements", { title: "Company Update", start_date: "2024-01-15", end_date: "2024-01-31", branch_id: [1], department_id: [1], description: "Announcement details" }),
    createItem("Get Announcement", "GET", "/announcements/1"),
    createItem("Update Announcement", "PUT", "/announcements/1", { title: "Updated Announcement" }),
    createItem("Delete Announcement", "DELETE", "/announcements/1")
]));

// ==================== 50. Meetings ====================
collection.item.push(createFolder("50. Meetings", "Meeting management", [
    createItem("List Meetings", "GET", "/meetings"),
    createItem("Create Meeting", "POST", "/meetings", { branch_id: 1, department_id: [1], employee_id: [1], title: "Team Meeting", date: "2024-01-20", time: "10:00", note: "Weekly standup" }),
    createItem("Get Meeting", "GET", "/meetings/1"),
    createItem("Update Meeting", "PUT", "/meetings/1", { title: "Updated Meeting" }),
    createItem("Delete Meeting", "DELETE", "/meetings/1"),
    createItem("Create Zoom Meeting", "POST", "/meetings/zoom", { title: "Zoom Call", start_date: "2024-01-20 10:00" })
]));

// ==================== 51. Events ====================
collection.item.push(createFolder("51. Events", "Event management", [
    createItem("List Events", "GET", "/events"),
    createItem("Create Event", "POST", "/events", { branch_id: 1, department_id: [1], employee_id: [1], title: "Team Building", start_date: "2024-02-01", end_date: "2024-02-01", color: "#3788d8", description: "Annual team event" }),
    createItem("Get Event", "GET", "/events/1"),
    createItem("Update Event", "PUT", "/events/1", { title: "Updated Event" }),
    createItem("Delete Event", "DELETE", "/events/1")
]));

// ==================== 52. Holidays ====================
collection.item.push(createFolder("52. Holidays", "Holiday management", [
    createItem("List Holidays", "GET", "/holidays"),
    createItem("Create Holiday", "POST", "/holidays", { occasion: "New Year", date: "2024-01-01" }),
    createItem("Get Holiday", "GET", "/holidays/1"),
    createItem("Update Holiday", "PUT", "/holidays/1", { occasion: "Updated Holiday" }),
    createItem("Delete Holiday", "DELETE", "/holidays/1")
]));

// ==================== 53. Company Policies ====================
collection.item.push(createFolder("53. Company Policies", "Policy management", [
    createItem("List Policies", "GET", "/policies"),
    createItem("Create Policy", "POST", "/policies", { branch: 1, title: "Remote Work Policy", description: "Policy details" }),
    createItem("Get Policy", "GET", "/policies/1"),
    createItem("Update Policy", "PUT", "/policies/1", { title: "Updated Policy" }),
    createItem("Delete Policy", "DELETE", "/policies/1")
]));

// ==================== 54. Timesheet ====================
collection.item.push(createFolder("54. Timesheet", "Timesheet management", [
    createItem("List Timesheets", "GET", "/timesheet"),
    createItem("Create Timesheet", "POST", "/timesheet", { employee_id: 1, project_id: 1, date: "2024-01-15", hours: 8, description: "Development work" }),
    createItem("Get Timesheet", "GET", "/timesheet/1"),
    createItem("Update Timesheet", "PUT", "/timesheet/1", { hours: 9 }),
    createItem("Delete Timesheet", "DELETE", "/timesheet/1")
]));

// ==================== 55. Time Tracker ====================
collection.item.push(createFolder("55. Time Tracker", "Time tracking", [
    createItem("Start Tracker", "POST", "/time-tracker/start", { task_id: 1 }),
    createItem("Stop Tracker", "POST", "/time-tracker/stop"),
    createItem("Get Current Tracker", "GET", "/time-tracker/current"),
    createItem("Get Tracker History", "GET", "/time-tracker/history")
]));

// ==================== 56. Reports ====================
collection.item.push(createFolder("56. Reports", "Various reports", [
    createItem("Income Summary", "GET", "/reports/income-summary"),
    createItem("Expense Summary", "GET", "/reports/expense-summary"),
    createItem("Income vs Expense", "GET", "/reports/income-vs-expense"),
    createItem("Tax Summary", "GET", "/reports/tax-summary"),
    createItem("Profit & Loss", "GET", "/reports/profit-loss"),
    createItem("Balance Sheet", "GET", "/reports/balance-sheet"),
    createItem("Trial Balance", "GET", "/reports/trial-balance"),
    createItem("Invoice Summary", "GET", "/reports/invoice-summary"),
    createItem("Bill Summary", "GET", "/reports/bill-summary"),
    createItem("Product Stock", "GET", "/reports/product-stock"),
    createItem("Attendance Report", "GET", "/reports/attendance"),
    createItem("Leave Report", "GET", "/reports/leave"),
    createItem("Payroll Report", "GET", "/reports/payroll"),
    createItem("Project Report", "GET", "/reports/project"),
    createItem("Task Report", "GET", "/reports/task"),
    createItem("Timesheet Report", "GET", "/reports/timesheet"),
    createItem("Lead Report", "GET", "/reports/lead"),
    createItem("Deal Report", "GET", "/reports/deal")
]));

// ==================== 57. Branches ====================
collection.item.push(createFolder("57. Branches", "Branch management", [
    createItem("List Branches", "GET", "/branches"),
    createItem("Create Branch", "POST", "/branches", { name: "Main Branch" }),
    createItem("Get Branch", "GET", "/branches/1"),
    createItem("Update Branch", "PUT", "/branches/1", { name: "Updated Branch" }),
    createItem("Delete Branch", "DELETE", "/branches/1")
]));

// ==================== 58. Users ====================
collection.item.push(createFolder("58. Users", "User management", [
    createItem("List Users", "GET", "/users"),
    createItem("Create User", "POST", "/users", { name: "New User", email: "newuser@example.com", password: "password123", type: "employee" }),
    createItem("Get User", "GET", "/users/1"),
    createItem("Update User", "PUT", "/users/1", { name: "Updated User" }),
    createItem("Delete User", "DELETE", "/users/1"),
    createItem("Reset User Password", "POST", "/users/1/reset-password", { password: "newpassword" })
]));

// ==================== 59. Roles ====================
collection.item.push(createFolder("59. Roles & Permissions", "Role and permission management", [
    createItem("List Roles", "GET", "/roles"),
    createItem("Create Role", "POST", "/roles", { name: "Manager", permissions: ["view-employee", "create-employee"] }),
    createItem("Get Role", "GET", "/roles/1"),
    createItem("Update Role", "PUT", "/roles/1", { name: "Updated Role" }),
    createItem("Delete Role", "DELETE", "/roles/1"),
    createItem("Get All Permissions", "GET", "/permissions")
]));

// ==================== 60. Settings ====================
collection.item.push(createFolder("60. Settings", "System settings", [
    createItem("Get Settings", "GET", "/settings"),
    createItem("Update Settings", "PUT", "/settings", { setting_key: "value" }),
    createItem("Get Company Settings", "GET", "/settings/company"),
    createItem("Update Company Settings", "PUT", "/settings/company", { company_name: "Company Name", company_email: "company@example.com" }),
    createItem("Get Email Settings", "GET", "/settings/email"),
    createItem("Update Email Settings", "PUT", "/settings/email", { mail_driver: "smtp", mail_host: "smtp.example.com" }),
    createItem("Get Payment Settings", "GET", "/settings/payment"),
    createItem("Update Payment Settings", "PUT", "/settings/payment")
]));

// ==================== 61. Notifications ====================
collection.item.push(createFolder("61. Notifications", "Notification management", [
    createItem("List Notifications", "GET", "/notifications"),
    createItem("Mark as Read", "POST", "/notifications/1/read"),
    createItem("Mark All as Read", "POST", "/notifications/read-all"),
    createItem("Delete Notification", "DELETE", "/notifications/1")
]));

// ==================== 62. Search ====================
collection.item.push(createFolder("62. Search", "Global search", [
    createItem("Global Search", "GET", "/search?query=keyword")
]));

// Write to file
const outputPath = './ERP_FULL_API_COLLECTION.json';
fs.writeFileSync(outputPath, JSON.stringify(collection, null, 2));
console.log(`✅ Collection generated successfully!`);
console.log(`📁 Output: ${outputPath}`);
console.log(`📊 Total folders: ${collection.item.length}`);

let totalEndpoints = 0;
collection.item.forEach(folder => {
    totalEndpoints += folder.item.length;
});
console.log(`🔗 Total endpoints: ${totalEndpoints}`);
