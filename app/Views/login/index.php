<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Login</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <link rel="stylesheet" href="/build/assets/app.css">
  <script src="/build/assets/flowbite.min.js"></script>

  <!-- Alpine + Axios -->
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

  <script>
    function loginForm() {
      return {
        usuario: '',
        password: '',
        message: '',
        type: '',
        loading: false,

        login() {
          if (this.loading) return;

          this.message = '';
          this.type = '';

          if (!this.usuario || !this.password) {
            this.message = 'Usuario y contraseña son obligatorios';
            this.type = 'error';
            return;
          }

          this.loading = true;

          axios.post('/login', {
            usuario: this.usuario,
            password: this.password
          })
          .then(res => {
            this.message = res.data.message;
            this.type = res.data.type;

            if (this.type === 'success') {
              setTimeout(() => {
                window.location.href = '/home';
              }, 800);
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

<body class="min-h-screen bg-gray-100 flex items-center justify-center">

  <div class="w-full max-w-md bg-white rounded-lg shadow-md p-6">

    <h2 class="text-2xl font-bold text-center text-gray-800">
      Iniciar Sesión
    </h2>

    <p class="text-center text-sm text-gray-500 mb-6">
      Accede a tu cuenta
    </p>

    <form x-data="loginForm()" @submit.prevent="login" class="space-y-4">

      <!-- Usuario -->
      <div>
        <label class="block mb-1 text-sm font-medium text-gray-700">
          Usuario
        </label>
        <input
          type="text"
          x-model="usuario"
          :disabled="loading"
          required
          class="bg-gray-50 border border-gray-300 text-gray-900 text-sm
                 rounded-lg focus:ring-blue-500 focus:border-blue-500
                 block w-full p-2.5"
          placeholder="Usuario"
        >
      </div>

      <!-- Contraseña -->
      <div>
        <label class="block mb-1 text-sm font-medium text-gray-700">
          Contraseña
        </label>
        <input
          type="password"
          x-model="password"
          :disabled="loading"
          required
          class="bg-gray-50 border border-gray-300 text-gray-900 text-sm
                 rounded-lg focus:ring-blue-500 focus:border-blue-500
                 block w-full p-2.5"
          placeholder="••••••••"
        >
      </div>

      <!-- Botón -->
      <button
        type="submit"
        :disabled="loading"
        class="w-full text-white bg-blue-700 hover:bg-blue-800
               focus:ring-4 focus:ring-blue-300 font-medium rounded-lg
               text-sm px-5 py-2.5 text-center
               disabled:opacity-60 disabled:cursor-not-allowed"
      >
        <span x-show="!loading">Entrar</span>

        <span x-show="loading" class="flex items-center justify-center gap-2">
          <svg class="w-4 h-4 animate-spin text-white"
               xmlns="http://www.w3.org/2000/svg"
               fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10"
                    stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 018-8v8z"></path>
          </svg>
          Validando...
        </span>
      </button>

      <!-- Alerta -->
      <div
        x-show="message"
        x-transition
        class="flex items-center p-4 rounded-lg text-sm mt-4"
        :class="type === 'success'
          ? 'text-green-800 bg-green-50'
          : 'text-red-800 bg-red-50'"
        role="alert"
      >
        <svg class="w-5 h-5 mr-2 shrink-0"
             xmlns="http://www.w3.org/2000/svg"
             fill="currentColor" viewBox="0 0 20 20">
          <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11H9v5h2V7zm0 6H9v2h2v-2z"/>
        </svg>

        <span x-text="message"></span>
      </div>

    </form>

  </div>

</body>
</html>
