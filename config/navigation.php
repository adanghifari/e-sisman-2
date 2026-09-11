<?php

return [
    'Utama' => [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'permission' => 'dashboard.view'],
    ],

    'Manajemen Dokumen' => [
        ['label' => 'Butuh Diproses', 'route' => 'documents.inbox', 'icon' => 'inbox', 'permission' => 'documents.inbox.view', 'badge' => 'needs_process'],
        ['label' => 'Tambah Dokumen', 'route' => 'documents.create', 'icon' => 'document-plus', 'permission' => 'documents.create.view'],
        ['label' => 'Dokumen Master', 'route' => 'documents.master', 'icon' => 'document-duplicate', 'permission' => 'documents.master.view'],
        ['label' => 'Dokumen Obsolete', 'route' => 'documents.obsolete', 'icon' => 'archive-box-x-mark', 'permission' => 'documents.obsolete.view'],
        ['label' => 'Template Dokumen', 'route' => 'document-templates.index', 'icon' => 'document-text', 'permission' => 'document-templates.view'],
    ],

    'Reporting' => [
        ['label' => 'Overview', 'route' => 'reports.index', 'icon' => 'chart-bar', 'permission' => 'reports.view'],
    ],

    'Administrasi' => [
        [
            'label' => 'Manajemen User',
            'icon' => 'users',
            'children' => [
                ['label' => 'User', 'route' => 'users.index', 'icon' => 'user', 'permission' => 'users.view'],
                ['label' => 'Group Akses', 'route' => 'access-groups.index', 'icon' => 'shield-check', 'permission' => 'access-groups.view'],
                ['label' => 'Menu Akses', 'route' => 'access-menus.index', 'icon' => 'list-bullet', 'permission' => 'access-menus.view'],
            ],
        ],
        ['label' => 'Approval Flow', 'route' => 'approval-flows.index', 'icon' => 'adjustments-horizontal', 'permission' => 'approval-flows.view'],
    ],

    'Master Data' => [
        ['label' => 'Proses / Fungsi', 'route' => 'master-data.process-functions', 'icon' => 'rectangle-stack', 'permission' => 'master-data.process-functions.view'],
        ['label' => 'Proses Bisnis', 'route' => 'master-data.business-processes', 'icon' => 'briefcase', 'permission' => 'master-data.business-processes.view'],
        ['label' => 'Department', 'route' => 'master-data.departments', 'icon' => 'building-office-2', 'permission' => 'master-data.departments.view'],
        ['label' => 'Jenis Dokumen', 'route' => 'master-data.document-types', 'icon' => 'tag', 'permission' => 'master-data.document-types.view'],
    ],

    'Log' => [
        ['label' => 'Catatan Aktivitas', 'route' => 'activity-log.index', 'icon' => 'clipboard-document-list', 'permission' => 'activity-log.view'],
    ],
];
