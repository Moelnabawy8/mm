import json
import sys

# Helper function to create request
def req(name, method, path, body=[], desc="", auto_save_token=False, no_auth=False):
    headers = [{"key": "Accept", "value": "application/json", "type": "text"}]
    
    request = {
        "name": name,
        "request": {
            "method": method,
            "header": headers,
            "url": {
                "raw": "{{base_url}}" + path,
                "host": ["{{base_url}}"],
                "path": path.strip("/").split("/")
            },
            "description": desc
        },
        "response": []
    }
    
    if not no_auth:
        request["request"]["auth"] = {
            "type": "bearer",
            "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}]
        }
    
    if body and method in ["POST", "PUT"]:
        request["request"]["body"] = {
            "mode": "formdata",
            "formdata": [{"key": k, "value": v, "type": "text"} for k, v in body]
        }
    
    if auto_save_token:
        request["event"] = [{
            "listen": "test",
            "script": {
                "exec": [
                    "if (pm.response.code === 200) {",
                    "    var jsonData = pm.response.json();",
                    "    if (jsonData.data && jsonData.data.token) {",
                    "        pm.collectionVariables.set('token', jsonData.data.token);",
                    "    }",
                    "}"
                ],
                "type": "text/javascript"
            }
        }]
    
    return request

# Create collection
collection = {
    "info": {
        "_postman_id": "erp-complete-api-v2",
        "name": "ERP System - Complete API Collection v2.0",
        "description": "Professional API collection for complete ERP System",
        "schema": "https://schema.getpostman.com/json/collection/v2.1.0/collection.json"
    },
    "auth": {"type": "bearer", "bearer": [{"key": "token", "value": "{{token}}", "type": "string"}]},
    "variable": [
        {"key": "base_url", "value": "http://localhost"},
        {"key": "token", "value": ""}
    ],
    "item": []
}

# Add all folders
folders = [
    {
        "name": "01. Authentication",
        "item": [
            req("Login", "POST", "/api/v1/login", [("email", "admin@example.com"), ("password", "password")], "Login", True, True),
            req("Register", "POST", "/api/v1/register", [("name", "John"), ("email", "john@example.com"), ("password", "pass")], "Register", False, True),
            req("Logout", "POST", "/api/v1/logout", [], "Logout"),
            req("Get Profile", "GET", "/api/v1/profile", [], "Get profile"),
            req("Update Profile", "PUT", "/api/v1/profile", [("name", "Updated")], "Update profile"),
            req("Change Password", "POST", "/api/v1/change-password", [("current_password", "old"), ("password", "new"), ("password_confirmation", "new")], "Change password"),
        ]
    },
    {
        "name": "02. Dashboard",
        "item": [
            req("Main Dashboard", "GET", "/api/v1/dashboard"),
            req("HRM Dashboard", "GET", "/api/v1/dashboard/hrm"),
            req("CRM Dashboard", "GET", "/api/v1/dashboard/crm"),
            req("Project Dashboard", "GET", "/api/v1/dashboard/project"),
        ]
    },
    {
        "name": "03. Employees",
        "item": [
            req("Get Employees", "GET", "/api/v1/employees"),
            req("Create Employee", "POST", "/api/v1/employees", [("name", "John"), ("email", "emp@test.com"), ("password", "pass")]),
            req("Get Employee", "GET", "/api/v1/employees/1"),
            req("Update Employee", "PUT", "/api/v1/employees/1", [("name", "Updated")]),
            req("Delete Employee", "DELETE", "/api/v1/employees/1"),
        ]
    },
    {
        "name": "04. Attendance",
        "item": [
            req("Get Attendance", "GET", "/api/v1/attendance"),
            req("Mark Attendance", "POST", "/api/v1/attendance", [("employee_id", "1"), ("date", "2024-01-01"), ("status", "Present")]),
            req("Update Attendance", "PUT", "/api/v1/attendance/1", [("clock_out", "18:00")]),
        ]
    },
    {
        "name": "05. Leaves",
        "item": [
            req("Get Leaves", "GET", "/api/v1/leaves"),
            req("Create Leave", "POST", "/api/v1/leaves", [("employee_id", "1"), ("start_date", "2024-02-01"), ("end_date", "2024-02-05")]),
            req("Approve Leave", "POST", "/api/v1/leaves/1/approve"),
            req("Reject Leave", "POST", "/api/v1/leaves/1/reject"),
        ]
    },
    {
        "name": "06. Departments",
        "item": [
            req("Get Departments", "GET", "/api/v1/departments"),
            req("Create Department", "POST", "/api/v1/departments", [("name", "IT")]),
        ]
    },
    {
        "name": "07. Payroll",
        "item": [
            req("Get Payslips", "GET", "/api/v1/payroll/payslips"),
            req("Create Payslip", "POST", "/api/v1/payroll/payslips", [("employee_id", "1"), ("month", "2024-01")]),
        ]
    },
    {
        "name": "08. Projects",
        "item": [
            req("Get Projects", "GET", "/api/v1/projects"),
            req("Create Project", "POST", "/api/v1/projects", [("name", "New Project")]),
            req("Get Project", "GET", "/api/v1/projects/1"),
        ]
    },
    {
        "name": "09. Tasks",
        "item": [
            req("Get Tasks", "GET", "/api/v1/tasks"),
            req("Create Task", "POST", "/api/v1/tasks", [("project_id", "1"), ("title", "Task")]),
        ]
    },
    {
        "name": "10. Customers",
        "item": [
            req("Get Customers", "GET", "/api/v1/customers"),
            req("Create Customer", "POST", "/api/v1/customers", [("name", "Customer"), ("email", "customer@test.com")]),
        ]
    },
    {
        "name": "11. Leads",
        "item": [
            req("Get Leads", "GET", "/api/v1/leads"),
            req("Create Lead", "POST", "/api/v1/leads", [("name", "Lead"), ("email", "lead@test.com")]),
            req("Convert Lead", "POST", "/api/v1/leads/1/convert"),
        ]
    },
    {
        "name": "12. Deals",
        "item": [
            req("Get Deals", "GET", "/api/v1/deals"),
            req("Create Deal", "POST", "/api/v1/deals", [("name", "Deal"), ("amount", "10000")]),
        ]
    },
    {
        "name": "13. Invoices",
        "item": [
            req("Get Invoices", "GET", "/api/v1/invoices"),
            req("Create Invoice", "POST", "/api/v1/invoices", [("customer_id", "1")]),
            req("Send Invoice", "POST", "/api/v1/invoices/1/send"),
        ]
    },
    {
        "name": "14. Bills",
        "item": [
            req("Get Bills", "GET", "/api/v1/bills"),
            req("Create Bill", "POST", "/api/v1/bills", [("vendor_id", "1")]),
        ]
    },
    {
        "name": "15. Vendors",
        "item": [
            req("Get Vendors", "GET", "/api/v1/vendors"),
            req("Create Vendor", "POST", "/api/v1/vendors", [("name", "Vendor")]),
        ]
    },
    {
        "name": "16. Products",
        "item": [
            req("Get Products", "GET", "/api/v1/products"),
            req("Create Product", "POST", "/api/v1/products", [("name", "Product"), ("price", "100")]),
        ]
    },
    {
        "name": "17. Expenses",
        "item": [
            req("Get Expenses", "GET", "/api/v1/expenses"),
            req("Create Expense", "POST", "/api/v1/expenses", [("amount", "500")]),
        ]
    },
    {
        "name": "18. Warehouses",
        "item": [
            req("Get Warehouses", "GET", "/api/v1/warehouses"),
            req("Create Warehouse", "POST", "/api/v1/warehouses", [("name", "Main Warehouse")]),
        ]
    },
    {
        "name": "19. POS",
        "item": [
            req("Get POS Products", "GET", "/api/v1/pos/products"),
            req("Add to Cart", "POST", "/api/v1/pos/cart/add", [("product_id", "1"), ("quantity", "2")]),
            req("Checkout", "POST", "/api/v1/pos/checkout", [("customer_id", "1")]),
        ]
    },
    {
        "name": "20. Reports",
        "item": [
            req("Income Report", "GET", "/api/v1/reports/income-summary"),
            req("Expense Report", "GET", "/api/v1/reports/expense-summary"),
            req("Profit & Loss", "GET", "/api/v1/reports/profit-loss"),
            req("Attendance Report", "GET", "/api/v1/reports/attendance"),
        ]
    },
    {
        "name": "21. Users & Roles",
        "item": [
            req("Get Users", "GET", "/api/v1/users"),
            req("Create User", "POST", "/api/v1/users", [("name", "User"), ("email", "user@test.com"), ("password", "pass")]),
            req("Get Roles", "GET", "/api/v1/roles"),
            req("Get Permissions", "GET", "/api/v1/permissions"),
        ]
    },
    {
        "name": "22. Settings",
        "item": [
            req("Get Settings", "GET", "/api/v1/settings"),
            req("Update Settings", "PUT", "/api/v1/settings", [("site_name", "My ERP")]),
        ]
    }
]

collection["item"] = folders

# Write to file
with open("ERP_API_COLLECTION_COMPLETE.json", "w", encoding="utf-8") as f:
    json.dump(collection, f, indent=2, ensure_ascii=False)

print("✅ Postman collection generated successfully!")
print("📁 File: ERP_API_COLLECTION_COMPLETE.json")
print(f"📊 Total folders: {len(folders)}")
print(f"📋 Total requests: {sum(len(f['item']) for f in folders)}")
