<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error 405 | Método no permitido</title>

    <link rel="stylesheet" href="/build/assets/app.css">
    <script src="/build/assets/flowbite.min.js"></script>

</head>
<body class="bg-gray-50 dark:bg-gray-900">

<div class="flex flex-col items-center justify-center min-h-screen px-4 text-center">

    <!-- Icono -->
    <svg class="w-20 h-20 text-yellow-500 dark:text-yellow-400 mb-6"
         fill="none" stroke="currentColor" stroke-width="1.5"
         viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M12 9v3.75m0 3.75h.007M10.5 3.75h3a1.5 1.5 0 011.5 1.5v1.5h3.75a1.5 1.5 0 011.5 1.5v10.5a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5V6.75a3 3 0 013-3z"/>
    </svg>

    <!-- Código -->
    <h1 class="text-5xl font-extrabold text-gray-900 dark:text-white mb-4">
        405
    </h1>

    <!-- Mensaje -->
    <p class="text-lg text-gray-600 dark:text-gray-400 mb-2">
        Método no permitido
    </p>

    <p class="text-sm text-gray-500 dark:text-gray-400 mb-8">
        El método HTTP utilizado no está permitido para esta ruta.
    </p>

    <!-- Botones -->
    <div class="flex flex-col sm:flex-row gap-4">
        <a href="/"
           class="inline-flex items-center justify-center px-5 py-2.5
                  text-sm font-medium text-white bg-blue-600 rounded-lg
                  hover:bg-blue-700 focus:ring-4 focus:ring-blue-300
                  dark:bg-blue-500 dark:hover:bg-blue-600 dark:focus:ring-blue-800">
            Ir al inicio
        </a>

        <button onclick="history.back()"
                class="inline-flex items-center justify-center px-5 py-2.5
                       text-sm font-medium text-gray-900 bg-white border border-gray-300
                       rounded-lg hover:bg-gray-100 focus:ring-4 focus:ring-gray-200
                       dark:bg-gray-800 dark:text-white dark:border-gray-700
                       dark:hover:bg-gray-700 dark:focus:ring-gray-700">
            Volver
        </button>
    </div>

</div>

</body>
</html>
