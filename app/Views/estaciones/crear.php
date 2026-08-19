<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="/build/assets/app.css">
    <script src="/build/assets/flowbite.min.js"></script>

    <!-- Alpine.js -->
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Axios -->
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

    <script>
        function createForm() {
            return {
                nombre: '',
                permisocre: '',
                razonsocial: '',
                rfc: '',
                direccioncompleta: '',
                di_estado: '',
                di_municipio: '',
                apoderado_legal: '',
                fecha_autorizacion: '',
                distmax: '',
                message: '',
                type: '',
                loading: false,



                create() {
                    if (this.loading) return;

                    this.message = '';
                    this.type = '';

                    if (!this.razonsocial) {
                        this.message = 'Ingese la Razón Social';
                        this.type = 'error';
                        return;
                    }

                    this.loading = true;

                    axios.post('/estaciones/create-estacion', {
                            nombre: this.nombre,
                            permisocre: this.permisocre,
                            razonsocial: this.razonsocial,
                            rfc: this.rfc,
                            direccioncompleta: this.direccioncompleta,
                            di_estado: this.di_estado,
                            di_municipio: this.di_municipio,
                            apoderado_legal: this.apoderado_legal,
                            fecha_autorizacion: this.fecha_autorizacion,
                            distmax: this.distmax
                        })
                        .then(res => {
                            this.message = res.data.message;
                            this.type = res.data.type;

                            if (this.type === 'success') {
                                setTimeout(() => {
                                    window.history.back();
                                }, 200);
                            }
                        })
                        .catch(() => {
                            this.message = 'Error de servidor';
                            this.type = 'error';
                        })
                        .finally(() => {
                            this.loading = false;
                        });
                }
            }
        }
    </script>

</head>

<body>

    <nav class="fixed top-0 z-50 w-full bg-neutral-primary-soft border-b border-default">
        <div class="px-3 py-3 lg:px-5 lg:pl-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center justify-start rtl:justify-end">

                    <a href="https://flowbite.com" class="flex ms-2 md:me-24">
                        <img src="https://flowbite.com/docs/images/logo.svg" class="h-6 me-3" alt="FlowBite Logo" />
                        <span class="self-center text-lg font-semibold whitespace-nowrap dark:text-white">Flowbite</span>
                    </a>
                </div>
                <div class="flex items-center">
                    <div class="flex items-center ms-3">
                        <div>
                            <button type="button" class="flex text-sm bg-gray-800 rounded-full focus:ring-4 focus:ring-gray-300 dark:focus:ring-gray-600" aria-expanded="false" data-dropdown-toggle="dropdown-user">
                                <span class="sr-only">Open user menu</span>
                                <img class="w-8 h-8 rounded-full" src="https://flowbite.com/docs/images/people/profile-picture-5.jpg" alt="user photo">
                            </button>
                        </div>
                        <div class="z-50 hidden bg-neutral-primary-medium border border-default-medium rounded-base shadow-lg w-44" id="dropdown-user">
                            <div class="px-4 py-3 border-b border-default-medium" role="none">
                                <p class="text-sm font-medium text-heading" role="none">
                                    <?= $user->nombre ?>
                                </p>
                                <p class="text-sm text-body truncate" role="none">
                                    neil.sims@flowbite.com
                                </p>
                            </div>
                            <ul class="p-2 text-sm text-body font-medium" role="none">
                                <li>
                                    <a class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Dashboard</a>
                                </li>
                                <li>
                                    <a class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Settings</a>
                                </li>
                                <li>
                                    <a class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Earnings</a>
                                </li>
                                <li>
                                    <a href="/logout" class="inline-flex items-center w-full p-2 hover:bg-neutral-tertiary-medium hover:text-heading rounded" role="menuitem">Sign out</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <section>
        <div class="mx-auto max-w-2xl mt-15 p-2">

            <nav class="flex" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-2 rtl:space-x-reverse">
                    <li class="inline-flex items-center">
                        <a class="inline-flex items-center text-sm font-medium text-body hover:text-fg-brand">
                            <svg class="w-4 h-4 me-1.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                            </svg>
                            Home
                        </a>
                    </li>
                    <li>
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 rtl:rotate-180 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                            </svg>
                            <a class="inline-flex items-center text-sm font-medium text-body hover:text-fg-brand">Estaciones</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center space-x-1.5">
                            <svg class="w-3.5 h-3.5 rtl:rotate-180 text-body" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m9 5 7 7-7 7" />
                            </svg>
                            <span class="inline-flex items-center text-sm font-medium text-body-subtle">Crear</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <h3 class="text-3xl font-bold text-heading">Crear Estacion</h3>


            <form x-data="createForm()" @submit.prevent="create">
                <div class="grid gap-4 sm:grid-cols-2">

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Nombre de la estación</label>
                        <input type="text" x-model="nombre" :disabled="loading" placeholder="Nombre de la estación" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Permiso CRE</label>
                        <input type="text" x-model="permisocre" :disabled="loading" placeholder="Permiso CRE" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Razón social</label>
                        <input type="text" x-model="razonsocial" :disabled="loading" placeholder="Razón social" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">RFC</label>
                        <input type="text" x-model="rfc" :disabled="loading" placeholder="RFC" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Dirección completa</label>
                        <input type="text" x-model="direccioncompleta" :disabled="loading" placeholder="Dirección completa" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Estado</label>
                        <input type="text" x-model="di_estado" :disabled="loading" placeholder="Estado" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Municipio</label>
                        <input type="text" x-model="di_municipio" :disabled="loading" placeholder="Municipio" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Apoderado Legal</label>
                        <input type="text" x-model="apoderado_legal" :disabled="loading" placeholder="Apoderado Legal" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Fecha de autorización</label>
                        <input type="date" x-model="fecha_autorizacion" :disabled="loading" placeholder="Fecha de autorización" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>

                    <div class="sm:col-span-1">
                        <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Distancia máxima</label>
                        <input type="number" step="1" x-model="distmax" :disabled="loading" placeholder="Distancia máxima" required
                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-primary-500 dark:focus:border-primary-500">
                    </div>


                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        :disabled="loading"
                        class="text-white bg-blue-700 hover:bg-blue-800
               focus:ring-4 focus:ring-blue-300 font-medium rounded-lg
               text-sm px-5 py-2.5 text-center
               disabled:opacity-60 disabled:cursor-not-allowed">
                        <span x-show="!loading" x-cloak>Guardar</span>

                        <span x-show="loading" class="flex items-center justify-center gap-2" x-cloak>
                            <svg class="w-4 h-4 animate-spin text-white"
                                xmlns="http://www.w3.org/2000/svg"
                                fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10"
                                    stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8v8z"></path>
                            </svg>
                            Cargando...
                        </span>
                    </button>
                </div>

                <!-- Alerta -->
                <div
                    x-show="message"
                    x-transition
                    class="flex items-center p-4 rounded-lg text-sm mt-4"
                    :class="type === 'success'
          ? 'text-green-800 bg-green-50'
          : 'text-red-800 bg-red-50'"
                    role="alert">
                    <svg class="w-5 h-5 mr-2 shrink-0"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="currentColor" viewBox="0 0 20 20">
                        <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11H9v5h2V7zm0 6H9v2h2v-2z" />
                    </svg>

                    <span x-text="message"></span>
                </div>

            </form>
        </div>

    </section>

</body>

</html>