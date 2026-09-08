<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        /* Optional: Smooth transition for content if sidebar ever expands/collapses */
        main {
            transition: margin-left 0.3s ease;
        }
    </style>
</head>
<body class="bg-light">
    <div class="d-flex">
        @include('layouts.sidebar')
        
        <main class="flex-grow-1 p-4" style="margin-left: 280px; width: calc(100% - 280px);">
           @if(session('notification_error'))
               <div class="alert alert-warning alert-dismissible fade show" role="alert">
                   <strong>Access restricted.</strong> {{ session('notification_error') }}
                   <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
               </div>
           @endif
           @yield('content')
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
     @stack('scripts')
</body>
</html>
