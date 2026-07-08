<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Clipku Pay') ?> - Clipku Pay</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/style.css">
    <style>
        @media (max-width: 640px) {
            table th, table td { padding: 8px 10px !important; font-size: 12px; }
            .overflow-x-auto { -webkit-overflow-scrolling: touch; }
        }
        html { overflow-x: hidden; max-width: 100vw; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                }
            }
        }
    </script>
</head>
<body class="h-full font-sans bg-slate-50 text-slate-900 antialiased overflow-x-hidden">
