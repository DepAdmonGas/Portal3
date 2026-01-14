<!DOCTYPE html>
<html lang="es" dir="ltr" data-bs-theme="light" data-color-theme="Blue_Theme" data-layout="vertical">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= $title ?? 'Portal3' ?></title>

    <!-- Favicon icon-->
  <link rel="shortcut icon" type="image/png" href="/assets/images/logos/favicon.png" />

  <!-- Core Css -->
  <link rel="stylesheet" href="/assets/css/styles.css" />

    <!-- Alpine + Axios -->
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>

  <style>[x-cloak] { display: none !important; }</style>

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
            } else {
                this.loading = false;
            }
            })
            .catch(() => {
            this.message = 'Error de servidor';
            this.type = 'error';
            this.loading = false;
            });
        }
      }
    }
  </script>
</head>
<body>

    <?= $content ?>

  <!-- Import Js Files -->
  <script src="/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/assets/libs/simplebar/dist/simplebar.min.js"></script>
  <script src="/assets/js/theme/app.init.js"></script>
  <script src="/assets/js/theme/theme.js"></script>
  <script src="/assets/js/theme/app.min.js"></script>

  <!-- solar icons -->
  <script src="https://cdn.jsdelivr.net/npm/iconify-icon@1.0.8/dist/iconify-icon.min.js"></script>

    <!-- Scripts por vista -->
    <?php if (!empty($scripts)): ?>
        <?php foreach ($scripts as $script): ?>
            <script src="<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

</body>
</html>
