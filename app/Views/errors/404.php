<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error 404 | Página no encontrada</title>


   <link rel="stylesheet" href="/build/assets/app.css">
    <script src="/build/assets/flowbite.min.js"></script>
</head>
<body class="bg-gray-50 dark:bg-gray-900">

<div class="flex flex-col items-center justify-center min-h-screen px-4 text-center">
    
    <!-- Icono -->
    <svg class="w-20 h-20 text-blue-600 dark:text-blue-500 mb-6"
         fill="none" stroke="currentColor" stroke-width="1.5"
         viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v3.75m0 3.75h.007M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>

    <!-- Título -->
    <h1 class="text-5xl font-extrabold text-gray-900 dark:text-white mb-4">
        404
    </h1>

    <!-- Mensaje -->
    <p class="text-lg text-gray-600 dark:text-gray-400 mb-8">
        Lo sentimos, la página que buscas no existe o fue movida.
    </p>

    <!-- Botones -->
    <div class="flex flex-col sm:flex-row gap-4">
        <a href="/"
           class="inline-flex items-center justify-center px-5 py-2.5
                  text-sm font-medium text-white bg-blue-600 rounded-lg
                  hover:bg-blue-700 focus:ring-4 focus:ring-blue-300
                  dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-800">
            Volver al inicio
        </a>

        <button onclick="history.back()"
                class="inline-flex items-center justify-center px-5 py-2.5
                       text-sm font-medium text-gray-900 bg-white border border-gray-300
                       rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-gray-200
                       dark:bg-gray-800 dark:text-white dark:border-gray-700
                       dark:hover:bg-gray-700 dark:focus:ring-gray-700">
            Regresar
        </button>
    </div>

</div>

<!-- Flowbite JS -->
<script src="/node_modules/flowbite/dist/flowbite.min.js"></script>
</body>
</html>
