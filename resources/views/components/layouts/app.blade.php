<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 font-sans text-slate-900 antialiased selection:bg-indigo-500 selection:text-white">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#4f46e5">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="DM Field">
    <title>{{ $title ?? 'DM System · Distribution Management' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
        // Installable app (PWA): register the service worker on secure origins.
        if ('serviceWorker' in navigator && window.isSecureContext) {
            window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
        }
    </script>
</head>
<body class="h-full bg-slate-50/70 text-slate-800">
@php
    $ic = fn ($d) => '<svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="'.$d.'"/></svg>';

    $navSections = [
        [
            'title' => 'Main',
            'items' => [
                ['route' => 'dashboard', 'label' => 'Dashboard', 'perm' => 'dashboard.view',
                 'icon' => $ic('M4 5a1 1 0 011-1h4a1 1 0 011 1v5a1 1 0 01-1 1H5a1 1 0 01-1-1V5zm10 0a1 1 0 011-1h4a1 1 0 011 1v2a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zm10-3a1 1 0 011-1h4a1 1 0 011 1v7a1 1 0 01-1 1h-4a1 1 0 01-1-1v-7z')],
                ['route' => 'explorer', 'label' => 'Data Explorer', 'perm' => 'explorer.view',
                 'icon' => $ic('M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z')],
                ['route' => 'imei-search', 'label' => 'IMEI Search', 'perm' => 'reports.view',
                 'icon' => $ic('M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5zM6.75 6.75h.008v.008H6.75V6.75zM6.75 16.5h.008v.008H6.75V16.5zM16.5 6.75h.008v.008H16.5V6.75zM13.5 13.5h3.75m0 0V17.25m0-3.75l3.75 3.75')],
            ]
        ],
        [
            'title' => 'Field Force',
            'items' => [
                ['route' => 'pjp', 'label' => 'Planned Journey Plan (PJP)', 'perm' => 'pjp.access',
                 'icon' => $ic('M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5')],
                ['route' => 'attendance', 'label' => 'Attendance (GPS)', 'perm' => 'attendance.self',
                 'icon' => $ic('M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z')],
                ['route' => 'attendance.report', 'label' => 'Attendance Report', 'perm' => 'attendance.view_all',
                 'icon' => $ic('M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z')],
                ['route' => 'field-sales.leave', 'label' => 'Leave', 'perm' => 'field-sales.leave.access',
                 'icon' => $ic('M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5m-9-6h.008v.008H12v-.008zM12 15h.008v.008H12V15zm0 2.25h.008v.008H12v-.008zM9.75 15h.008v.008H9.75V15zm0 2.25h.008v.008H9.75v-.008zM7.5 15h.008v.008H7.5V15zm0 2.25h.008v.008H7.5v-.008zm6.75-4.5h.008v.008h-.008v-.008zm0 2.25h.008v.008h-.008V15zm0 2.25h.008v.008h-.008v-.008zm2.25-4.5h.008v.008H16.5v-.008zm0 2.25h.008v.008H16.5V15z')],
                ['route' => 'field-sales.map', 'label' => 'Live Staff Map', 'perm' => 'fs.map.view',
                 'icon' => $ic('M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z')],
                ['route' => 'field-sales.attendance', 'label' => 'Monthly Attendance', 'perm' => 'fs.reports.view',
                 'icon' => $ic('M3.375 19.5h17.25m-17.25 0a1.125 1.125 0 01-1.125-1.125M3.375 19.5h7.5c.621 0 1.125-.504 1.125-1.125m-9.75 0V5.625m0 12.75v-1.5c0-.621.504-1.125 1.125-1.125m18.375 2.625V5.625m0 12.75c0 .621-.504 1.125-1.125 1.125m1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125m0 3.75h-7.5A1.125 1.125 0 0112 18.375m9.75-12.75c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125m19.5 0v1.5c0 .621-.504 1.125-1.125 1.125M2.25 5.625v1.5c0 .621.504 1.125 1.125 1.125m0 0h17.25m-17.25 0h7.5c.621 0 1.125.504 1.125 1.125M3.375 8.25c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125m17.25-3.75h-7.5c-.621 0-1.125.504-1.125 1.125m8.625-1.125c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125M12 10.875v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 10.875c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125M13.125 12h7.5m-7.5 0c-.621 0-1.125.504-1.125 1.125M20.625 12c.621 0 1.125.504 1.125 1.125v1.5c0 .621-.504 1.125-1.125 1.125m-17.25 0h7.5M12 14.625v-1.5m0 1.5c0 .621-.504 1.125-1.125 1.125M12 14.625c0 .621.504 1.125 1.125 1.125m-2.25 0c.621 0 1.125.504 1.125 1.125m0 1.5v-1.5m0 0c0-.621.504-1.125 1.125-1.125m0 0h7.5')],
                ['route' => 'retailer-map', 'label' => 'Retailer Map', 'perm' => 'reports.view',
                 'icon' => $ic('M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z')],
                ['route' => 'retailer-location-requests', 'label' => 'Location Requests', 'perm' => 'retailer-location.access',
                 'icon' => $ic('M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z')],
                ['route' => 'promoters', 'label' => 'Promoters (RA)', 'perm' => 'promoters.access',
                 'icon' => $ic('M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z')],
                ['route' => 'returns', 'label' => 'Device Returns', 'perm' => 'returns.access',
                 'icon' => $ic('M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99')],
            ]
        ],
        [
            'title' => 'Analytics & Reports',
            'items' => [
                [
                    'label' => 'Reports Suite',
                    'perm' => 'reports.view',
                    'icon' => $ic('M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z'),
                    'children' => [
                        ['route' => 'reports', 'label' => 'Standard Reports'],
                        ['route' => 'quick-reports', 'label' => 'Quick Reports'],
                        ['route' => 'stock', 'label' => 'Stock Aging Report'],
                        ['route' => 'sellout', 'label' => 'Sellout Performance'],
                        ['route' => 'wod-coverage', 'label' => 'WOD Coverage'],
                        ['route' => 'scheduled-reports', 'label' => 'Scheduled Email Reports', 'perm' => 'scheduled-reports.manage'],
                    ]
                ],
                ['route' => 'exports.index', 'label' => 'Export Center', 'perm' => 'exports.view',
                 'icon' => $ic('M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3')],
            ]
        ],
        [
            'title' => 'Supply & Schemes',
            'items' => [
                ['route' => 'imports.index', 'label' => 'Import Hub', 'perm' => 'imports.access',
                 'icon' => $ic('M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5')],
                ['route' => 'schemes.index', 'label' => 'Schemes & Promotions', 'perm' => 'settings.manage',
                 'icon' => $ic('M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456zM16.894 20.567L16.5 21.75l-.394-1.183a2.25 2.25 0 00-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 001.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 001.423 1.423l1.183.394-1.183.394a2.25 2.25 0 00-1.423 1.423z')],
                ['route' => 'scheme-enrolment', 'label' => 'Scheme Enrolment', 'perm' => 'schemes.enrol',
                 'icon' => $ic('M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z')],
                ['route' => 'annual-contracts', 'label' => 'Annual Contracts', 'perm' => 'contracts.manage',
                 'icon' => $ic('M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z')],
                ['route' => 'model-prices', 'label' => 'Model Price List', 'perm' => 'masterdata.view',
                 'icon' => $ic('M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z')],
            ]
        ],
        [
            'title' => 'Management',
            'items' => [
                ['route' => 'masterdata', 'label' => 'Master Data', 'perm' => 'masterdata.view',
                 'icon' => $ic('M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125')],
                [
                    'label' => 'Field Sales Setup',
                    'perm' => 'fs.setup.manage',
                    'icon' => $ic('M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75'),
                    'children' => [
                        ['route' => 'field-sales.setup.hierarchy', 'label' => 'Regions & Areas'],
                        ['route' => 'field-sales.setup.geofences', 'label' => 'Check-in Points'],
                        ['route' => 'field-sales.setup.policies', 'label' => 'Duty Rules & Holidays'],
                    ]
                ],
                ['route' => 'users.index', 'label' => 'Users & Permissions', 'perm' => 'users.manage',
                 'icon' => $ic('M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z')],
                ['route' => 'settings.index', 'label' => 'System Settings', 'perm' => 'settings.manage',
                 'icon' => $ic('M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281zM15 12a3 3 0 11-6 0 3 3 0 016 0z')],
            ]
        ],
    ];

    $userName = auth()->user()?->name ?? 'User';
    $userEmail = auth()->user()?->email ?? '';
    $userInitial = strtoupper(substr($userName, 0, 1));
    $userRole = auth()->user()?->roles->first()?->name ?? 'User';
@endphp

<div class="min-h-full" x-data="{ mobileNav: false }">
    {{-- Mobile Header --}}
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between border-b border-slate-200/80 bg-white/95 px-4 backdrop-blur-md md:hidden">
        <div class="flex items-center gap-3">
            <button type="button" @click="mobileNav = true"
                    class="rounded-xl p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-none"
                    aria-label="Open menu">
                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <div class="flex items-center gap-2">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 font-bold text-white shadow-xs">
                    DM
                </div>
                <span class="text-base font-bold tracking-tight text-slate-900">DM<span class="text-indigo-600">System</span></span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <span class="flex h-2 w-2 rounded-full bg-emerald-500 ring-4 ring-emerald-100"></span>
            <span class="text-xs font-medium text-slate-500">{{ $userRole }}</span>
        </div>
    </header>

    {{-- Mobile Slide-Over Drawer --}}
    <div x-show="mobileNav" x-cloak class="fixed inset-0 z-50 md:hidden" role="dialog" aria-modal="true">
        <div x-show="mobileNav"
             x-transition:enter="transition-opacity ease-linear duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition-opacity ease-linear duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs"
             @click="mobileNav = false"></div>

        <div class="fixed inset-y-0 left-0 flex max-w-full">
            <aside x-show="mobileNav"
                   x-transition:enter="transition ease-in-out duration-300 transform"
                   x-transition:enter-start="-translate-x-full"
                   x-transition:enter-end="translate-x-0"
                   x-transition:leave="transition ease-in-out duration-300 transform"
                   x-transition:leave-start="translate-x-0"
                   x-transition:leave-end="-translate-x-full"
                   class="relative flex w-72 flex-col bg-white shadow-2xl">
                <div class="flex h-16 items-center justify-between border-b border-slate-100 px-5">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 font-bold text-white shadow-xs">
                            DM
                        </div>
                        <div>
                            <div class="text-sm font-bold tracking-tight text-slate-900">DM<span class="text-indigo-600">System</span></div>
                            <div class="text-[10px] text-slate-400 font-medium">dms.parashojha.com</div>
                        </div>
                    </div>
                    <button type="button" @click="mobileNav = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-3" @click="mobileNav = false">
                    @include('components.layouts.nav-items', ['navSections' => $navSections])
                </nav>

                <div class="border-t border-slate-100 p-4 bg-slate-50/50">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-indigo-100 text-sm font-bold text-indigo-700">
                            {{ $userInitial }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-semibold text-slate-900">{{ $userName }}</div>
                            <div class="truncate text-[11px] text-slate-400">{{ $userRole }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" title="Sign out" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200 hover:text-rose-600 transition">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <div class="flex">
        {{-- Desktop Modern Sidebar --}}
        <aside class="hidden md:fixed md:inset-y-0 md:flex md:w-64 md:flex-col bg-white border-r border-slate-200/80 shadow-xs z-20">
            {{-- Brand header --}}
            <div class="flex h-16 items-center justify-between border-b border-slate-100 px-5">
                <a href="{{ route(\App\Support\Home::route()) }}" class="flex items-center gap-2.5 group">
                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-600 to-violet-500 font-bold text-white shadow-sm transition group-hover:scale-105">
                        DM
                    </div>
                    <div>
                        <div class="text-base font-bold tracking-tight text-slate-900 leading-none">
                            DM<span class="text-indigo-600">System</span>
                        </div>
                        <div class="mt-1 flex items-center gap-1.5">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="text-[10px] font-semibold text-slate-400 uppercase tracking-wider">Enterprise</span>
                        </div>
                    </div>
                </a>
            </div>

            {{-- Nav link tree --}}
            <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
                @include('components.layouts.nav-items', ['navSections' => $navSections])
            </nav>

            {{-- Bottom User Card --}}
            <div class="border-t border-slate-100 p-3 bg-slate-50/60">
                <div class="flex items-center justify-between rounded-xl p-2 bg-white border border-slate-200/70 shadow-2xs">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-gradient-to-tr from-indigo-600 to-violet-500 text-xs font-bold text-white shadow-xs">
                            {{ $userInitial }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-xs font-semibold text-slate-800">{{ $userName }}</div>
                            <div class="flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                <span class="text-[10px] font-medium text-slate-400 truncate">{{ $userRole }}</span>
                            </div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}" class="shrink-0">
                        @csrf
                        <button type="submit" title="Sign out"
                                class="rounded-lg p-1.5 text-slate-400 hover:bg-rose-50 hover:text-rose-600 transition">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9"/></svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        {{-- Main Page Body --}}
        <div class="flex-1 md:pl-64 flex flex-col min-w-0 min-h-screen">
            {{-- Top Navbar for Desktop --}}
            <header class="hidden md:flex h-16 sticky top-0 z-10 items-center justify-between border-b border-slate-200/80 bg-white/80 px-8 backdrop-blur-md shadow-2xs">
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Environment</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        dms.parashojha.com
                    </span>
                </div>
                <div class="flex items-center gap-4 text-xs font-medium text-slate-500">
                    <div class="flex items-center gap-2 rounded-xl bg-slate-100/80 px-3 py-1.5 text-slate-600 border border-slate-200/60">
                        <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ now()->format('D, M d, Y') }}</span>
                    </div>
                </div>
            </header>

            {{-- Main Content Canvas --}}
            <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8 max-w-7xl w-full mx-auto pb-24 md:pb-8">
                @if (session('status'))
                    <div class="mb-6 flex items-center gap-3 rounded-2xl bg-emerald-50/90 p-4 text-sm text-emerald-800 border border-emerald-200/80 shadow-xs" role="alert">
                        <svg class="h-5 w-5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div class="font-medium">{{ session('status') }}</div>
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-6 flex items-center gap-3 rounded-2xl bg-rose-50/90 p-4 text-sm text-rose-800 border border-rose-200/80 shadow-xs" role="alert">
                        <svg class="h-5 w-5 shrink-0 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <div class="font-medium">{{ session('error') }}</div>
                    </div>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Native Mobile Bottom Navigation Bar --}}
    <nav class="fixed bottom-0 inset-x-0 z-40 flex h-16 items-center justify-around border-t border-slate-200/90 bg-white/95 px-2 backdrop-blur-lg shadow-[0_-4px_20px_rgba(0,0,0,0.05)] md:hidden">
        {{-- Home / Dashboard --}}
        {{-- Roles without a dashboard (e.g. TSO) land on their own home page instead of a 403. --}}
        @php($homeRoute = \App\Support\Home::route())
        <a href="{{ route($homeRoute) }}" wire:navigate
           class="flex flex-col items-center justify-center gap-1 py-1 px-2.5 text-[10px] font-semibold transition active:scale-95 {{ request()->routeIs($homeRoute) ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-800' }}">
            <svg class="h-5 w-5 {{ request()->routeIs($homeRoute) ? 'text-indigo-600 stroke-[2.2]' : 'text-slate-400' }}"" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/>
            </svg>
            <span>Home</span>
        </a>

        @can('attendance.self')
            {{-- Attendance / Punch --}}
            <a href="{{ route('attendance') }}" wire:navigate
               class="relative flex flex-col items-center justify-center gap-1 py-1 px-2.5 text-[10px] font-semibold transition active:scale-95 {{ request()->routeIs('attendance') ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-800' }}">
                <div class="relative">
                    <svg class="h-5 w-5 {{ request()->routeIs('attendance') ? 'text-indigo-600 stroke-[2.2]' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    @if (auth()->user()?->todayAttendance && !auth()->user()->todayAttendance->isCheckedOut())
                        <span class="absolute -top-1 -right-1 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white animate-pulse"></span>
                    @endif
                </div>
                <span>Punch</span>
            </a>
        @endcan

        @can('pjp.access')
            {{-- PJP Route --}}
            <a href="{{ route('pjp') }}" wire:navigate
               class="flex flex-col items-center justify-center gap-1 py-1 px-2.5 text-[10px] font-semibold transition active:scale-95 {{ request()->routeIs('pjp*') ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-800' }}">
                <svg class="h-5 w-5 {{ request()->routeIs('pjp*') ? 'text-indigo-600 stroke-[2.2]' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.284a2.25 2.25 0 00-2.012 0L2.616 5.722c-.381.19-.622.58-.622 1.006v11.314c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z"/>
                </svg>
                <span>Route</span>
            </a>
        @endcan

        @can('reports.view')
            {{-- Map / Retailers --}}
            <a href="{{ route('retailer-map') }}" wire:navigate
               class="flex flex-col items-center justify-center gap-1 py-1 px-2.5 text-[10px] font-semibold transition active:scale-95 {{ request()->routeIs('retailer-map*') ? 'text-indigo-600' : 'text-slate-500 hover:text-slate-800' }}">
                <svg class="h-5 w-5 {{ request()->routeIs('retailer-map*') ? 'text-indigo-600 stroke-[2.2]' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                </svg>
                <span>Map</span>
            </a>
        @endcan

        {{-- More / Menu Drawer --}}
        <button type="button" @click="mobileNav = true"
                class="flex flex-col items-center justify-center gap-1 py-1 px-2.5 text-[10px] font-semibold text-slate-500 hover:text-slate-800 transition active:scale-95">
            <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
            </svg>
            <span>Menu</span>
        </button>
    </nav>
</div>
@livewireScripts
</body>
</html>
