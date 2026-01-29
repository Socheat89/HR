<?php
/**
 * Simple per-user/per-role sidebar permissions configuration.
 *
 * How to use:
 * - For a new employee, add their username under 'users' with an array of allowed menu keys.
 * - If a user is not listed, they'll inherit the defaults for their role from 'defaults'.
 * - Use '*' to allow everything.
 *
 * Menu keys available:
 *  dashboard, checklist, reports, request_reports,
 *  payroll, manage_deductions, employee_data, payroll_calculation, payroll_approval, payroll_payslip, payroll_payment,
 *  post_announcements, inactive_users, post_meeting, post_lesson, print_content, store_print_pdf,
 *  pending_requests, view_processed_requests, lessons, post_lesson_documents, settings_control,
 *  add_employee
 */
return [
    'defaults' => [
        // Full access
        'admin' => ['*'],

        // Suggestive defaults (adjust to your needs)
        'HR' => [
            'dashboard', 'checklist',
            'request_reports', 'pending_requests', 'view_processed_requests',
            'lessons', 'post_lesson_documents'
        ],
        'Accounting' => [
            'dashboard', 'checklist',
            'payroll', 'manage_deductions', 'payroll_calculation', 'payroll_approval', 'payroll_payslip', 'payroll_payment',
            'print_content'
        ],
        // Fallback for any other role
        'employee' => ['dashboard', 'checklist', 'lessons']
    ],

    // Per-user overrides (example)
    'users' => [
        // 'sok' => ['dashboard', 'checklist', 'lessons'],
        // 'dara' => ['*'],
    ],
];
