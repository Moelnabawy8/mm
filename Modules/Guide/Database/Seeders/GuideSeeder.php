<?php

namespace Modules\Guide\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Guide\Entities\GuideCategory;
use Modules\Guide\Entities\GuideVideo;
use Modules\Guide\Entities\GuideDocument;

class GuideSeeder extends Seeder
{
    public function run()
    {
        // Create Categories
        $categories = [
            [
                'name' => 'Getting Started',
                'slug' => 'getting-started',
                'description' => 'Learn the basics of the ERP system and how to get started',
                'icon' => 'ti ti-rocket',
                'color' => '#6c5ce7',
                'order' => 1,
            ],
            [
                'name' => 'Dashboard & HRM',
                'slug' => 'dashboard-hrm',
                'description' => 'Human Resource Management system guide',
                'icon' => 'ti ti-users',
                'color' => '#00b894',
                'order' => 2,
            ],
            [
                'name' => 'CRM System',
                'slug' => 'crm-system',
                'description' => 'Customer Relationship Management system guide',
                'icon' => 'ti ti-address-book',
                'color' => '#0984e3',
                'order' => 3,
            ],
            [
                'name' => 'POS System',
                'slug' => 'pos-system',
                'description' => 'Point of Sale system guide',
                'icon' => 'ti ti-cash',
                'color' => '#e17055',
                'order' => 4,
            ],
            [
                'name' => 'Accounting',
                'slug' => 'accounting',
                'description' => 'Accounting and financial management guide',
                'icon' => 'ti ti-calculator',
                'color' => '#fdcb6e',
                'order' => 5,
            ],
            [
                'name' => 'Departments',
                'slug' => 'departments',
                'description' => 'Department management guide',
                'icon' => 'ti ti-building',
                'color' => '#a29bfe',
                'order' => 6,
            ],
            [
                'name' => 'User Management',
                'slug' => 'user-management',
                'description' => 'User and role management guide',
                'icon' => 'ti ti-user-cog',
                'color' => '#74b9ff',
                'order' => 7,
            ],
            [
                'name' => 'System Settings',
                'slug' => 'system-settings',
                'description' => 'System configuration and settings guide',
                'icon' => 'ti ti-settings',
                'color' => '#636e72',
                'order' => 8,
            ],
            [
                'name' => 'Support & Integrations',
                'slug' => 'support-integrations',
                'description' => 'Support system and third-party integrations',
                'icon' => 'ti ti-headset',
                'color' => '#e84393',
                'order' => 9,
            ],
        ];

        foreach ($categories as $cat) {
            GuideCategory::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        // Create Videos
        $videos = [
            [
                'category_slug' => 'getting-started',
                'title' => 'ERP Overview - Complete Introduction',
                'slug' => 'erp-overview',
                'description' => 'A complete overview of the ERP system, its features, and how to navigate through different modules.',
                'video_path' => 'uploads/ERP overview.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'getting-started',
                'title' => 'شرح كامل لكيفية البدء في العمل على نظام ERP',
                'slug' => 'erp-getting-started-arabic',
                'description' => 'دليل شامل باللغة العربية لكيفية البدء في استخدام نظام ERP والتعرف على جميع الوظائف الأساسية.',
                'video_path' => 'uploads/شرح كامل لكيفية البدء في العمل على نظام ERP.mp4',
                'order' => 2,
            ],
            [
                'category_slug' => 'dashboard-hrm',
                'title' => 'Dashboard & HRM System Guide',
                'slug' => 'dashboard-hrm-guide',
                'description' => 'Learn how to use the Dashboard and Human Resource Management system including employee management, attendance, and payroll.',
                'video_path' => 'uploads/Dashboard-HRM system.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'crm-system',
                'title' => 'CRM System Complete Guide',
                'slug' => 'crm-system-guide',
                'description' => 'Complete guide to the Customer Relationship Management system including leads, deals, contacts, and sales pipeline management.',
                'video_path' => 'uploads/CRM  system.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'pos-system',
                'title' => 'POS System Guide',
                'slug' => 'pos-system-guide',
                'description' => 'Learn how to use the Point of Sale system for retail operations, inventory management, and sales processing.',
                'video_path' => 'uploads/POS system.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'pos-system',
                'title' => 'شرح نظام نقاط البيع (POS System)',
                'slug' => 'pos-system-arabic',
                'description' => 'شرح تفصيلي باللغة العربية لنظام نقاط البيع وكيفية إدارة المبيعات والمخزون.',
                'video_path' => 'uploads/شرح نظام نقاط البيع (POS System).mp4',
                'order' => 2,
            ],
            [
                'category_slug' => 'accounting',
                'title' => 'Accounting System Guide',
                'slug' => 'accounting-system-guide',
                'description' => 'Complete guide to the accounting module including invoices, expenses, reports, and financial management.',
                'video_path' => 'uploads/accounting system.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'departments',
                'title' => 'Departments System Guide',
                'slug' => 'departments-system-guide',
                'description' => 'Learn how to manage departments, teams, and organizational structure within the ERP system.',
                'video_path' => 'uploads/Departments system.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'user-management',
                'title' => 'User Management Guide',
                'slug' => 'user-management-guide',
                'description' => 'Complete guide to user management, roles, permissions, and access control.',
                'video_path' => 'uploads/user managment.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'system-settings',
                'title' => 'System Settings Guide',
                'slug' => 'system-settings-guide',
                'description' => 'Learn how to configure system settings, customize the ERP, and manage system preferences.',
                'video_path' => 'uploads/SYSTEM SETTING.mp4',
                'order' => 1,
            ],
            [
                'category_slug' => 'support-integrations',
                'title' => 'Support, Messenger & Google Integration',
                'slug' => 'support-messenger-google',
                'description' => 'Learn how to use the support system, messenger integration, and Google services integration.',
                'video_path' => 'uploads/Support -Messenger-Google.mp4',
                'order' => 1,
            ],
        ];

        foreach ($videos as $video) {
            $category = GuideCategory::where('slug', $video['category_slug'])->first();
            if ($category) {
                unset($video['category_slug']);
                $video['category_id'] = $category->id;
                GuideVideo::updateOrCreate(['slug' => $video['slug']], $video);
            }
        }

        // Create Documentation
        $documents = [
            [
                'category_slug' => 'getting-started',
                'title' => 'ERP System Overview',
                'slug' => 'erp-overview-doc',
                'icon' => 'ti ti-book',
                'order' => 1,
                'content' => '<h2>Welcome to the ERP System</h2>
<p>This Enterprise Resource Planning (ERP) system is a comprehensive solution designed to streamline your business operations. It integrates various modules including:</p>
<ul>
<li><strong>HRM (Human Resource Management)</strong> - Manage employees, attendance, payroll, and more</li>
<li><strong>CRM (Customer Relationship Management)</strong> - Handle leads, deals, contacts, and sales</li>
<li><strong>POS (Point of Sale)</strong> - Retail operations and inventory management</li>
<li><strong>Accounting</strong> - Financial management, invoices, and reports</li>
<li><strong>Project Management</strong> - Tasks, projects, and team collaboration</li>
</ul>
<h3>Getting Started</h3>
<p>To begin using the system:</p>
<ol>
<li>Log in with your credentials</li>
<li>Navigate to the Dashboard for an overview</li>
<li>Explore each module based on your role and permissions</li>
<li>Configure system settings as needed</li>
</ol>',
            ],
            [
                'category_slug' => 'dashboard-hrm',
                'title' => 'HRM Module Documentation',
                'slug' => 'hrm-documentation',
                'icon' => 'ti ti-users',
                'order' => 1,
                'content' => '<h2>Human Resource Management</h2>
<p>The HRM module provides comprehensive tools for managing your workforce:</p>
<h3>Key Features</h3>
<ul>
<li><strong>Employee Management</strong> - Add, edit, and manage employee profiles</li>
<li><strong>Attendance Tracking</strong> - Monitor check-in/check-out times</li>
<li><strong>Leave Management</strong> - Handle leave requests and approvals</li>
<li><strong>Payroll</strong> - Process salaries, deductions, and bonuses</li>
<li><strong>Performance Reviews</strong> - Conduct and track employee evaluations</li>
</ul>
<h3>How to Use</h3>
<ol>
<li>Navigate to HRM from the main menu</li>
<li>Add employees with their complete information</li>
<li>Set up attendance policies and leave types</li>
<li>Configure payroll settings and salary structures</li>
<li>Generate reports as needed</li>
</ol>',
            ],
            [
                'category_slug' => 'crm-system',
                'title' => 'CRM Module Documentation',
                'slug' => 'crm-documentation',
                'icon' => 'ti ti-address-book',
                'order' => 1,
                'content' => '<h2>Customer Relationship Management</h2>
<p>The CRM module helps you manage customer relationships and sales processes:</p>
<h3>Key Features</h3>
<ul>
<li><strong>Lead Management</strong> - Capture and track potential customers</li>
<li><strong>Deal Pipeline</strong> - Manage sales opportunities through stages</li>
<li><strong>Contact Management</strong> - Store and organize customer information</li>
<li><strong>Tasks & Activities</strong> - Schedule follow-ups and track interactions</li>
<li><strong>Reports & Analytics</strong> - Analyze sales performance</li>
</ul>
<h3>Sales Pipeline Stages</h3>
<ol>
<li>Lead - Initial contact or inquiry</li>
<li>Qualified - Verified interest and budget</li>
<li>Proposal - Sent quote or proposal</li>
<li>Negotiation - Discussing terms</li>
<li>Won/Lost - Final outcome</li>
</ol>',
            ],
            [
                'category_slug' => 'pos-system',
                'title' => 'POS System Documentation',
                'slug' => 'pos-documentation',
                'icon' => 'ti ti-cash',
                'order' => 1,
                'content' => '<h2>Point of Sale System</h2>
<p>The POS module streamlines retail operations:</p>
<h3>Key Features</h3>
<ul>
<li><strong>Quick Sales</strong> - Process transactions efficiently</li>
<li><strong>Product Management</strong> - Manage inventory and pricing</li>
<li><strong>Multiple Payment Methods</strong> - Cash, card, and digital payments</li>
<li><strong>Receipt Printing</strong> - Generate and print receipts</li>
<li><strong>Sales Reports</strong> - Track daily, weekly, and monthly sales</li>
</ul>
<h3>Processing a Sale</h3>
<ol>
<li>Search or scan products</li>
<li>Add items to cart</li>
<li>Apply discounts if applicable</li>
<li>Select payment method</li>
<li>Complete transaction and print receipt</li>
</ol>',
            ],
            [
                'category_slug' => 'accounting',
                'title' => 'Accounting Module Documentation',
                'slug' => 'accounting-documentation',
                'icon' => 'ti ti-calculator',
                'order' => 1,
                'content' => '<h2>Accounting System</h2>
<p>Manage your finances with the accounting module:</p>
<h3>Key Features</h3>
<ul>
<li><strong>Invoicing</strong> - Create and manage invoices</li>
<li><strong>Expense Tracking</strong> - Record and categorize expenses</li>
<li><strong>Bank Accounts</strong> - Manage multiple bank accounts</li>
<li><strong>Financial Reports</strong> - Balance sheets, P&L statements</li>
<li><strong>Tax Management</strong> - Configure and apply taxes</li>
</ul>
<h3>Creating an Invoice</h3>
<ol>
<li>Go to Accounting > Invoices</li>
<li>Click "Create Invoice"</li>
<li>Select customer and add items</li>
<li>Set due date and payment terms</li>
<li>Save and send to customer</li>
</ol>',
            ],
            [
                'category_slug' => 'system-settings',
                'title' => 'System Settings Documentation',
                'slug' => 'settings-documentation',
                'icon' => 'ti ti-settings',
                'order' => 1,
                'content' => '<h2>System Settings</h2>
<p>Configure your ERP system to match your business needs:</p>
<h3>General Settings</h3>
<ul>
<li><strong>Company Information</strong> - Name, logo, address</li>
<li><strong>Regional Settings</strong> - Currency, date format, timezone</li>
<li><strong>Email Settings</strong> - SMTP configuration for notifications</li>
<li><strong>Security Settings</strong> - Password policies, 2FA</li>
</ul>
<h3>Module Settings</h3>
<ul>
<li><strong>HRM Settings</strong> - Leave types, attendance rules</li>
<li><strong>CRM Settings</strong> - Pipeline stages, lead sources</li>
<li><strong>Accounting Settings</strong> - Tax rates, payment methods</li>
</ul>',
            ],
        ];

        foreach ($documents as $doc) {
            $category = GuideCategory::where('slug', $doc['category_slug'])->first();
            if ($category) {
                unset($doc['category_slug']);
                $doc['category_id'] = $category->id;
                GuideDocument::updateOrCreate(['slug' => $doc['slug']], $doc);
            }
        }
    }
}
