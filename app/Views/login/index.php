
  <!-- Preloader -->
  <div class="preloader">
    <img src="/assets/images/logos/favicon.png" alt="loader" class="lds-ripple img-fluid" />
  </div>
  <div id="main-wrapper" class="auth-customizer-none">
    <div class="position-relative overflow-hidden radial-gradient min-vh-100 w-100">
      <div class="position-relative z-index-5">
        <div class="row">

                 
        <div class="col-xl-5 col-xxl-4">


            <div class="authentication-login min-vh-100 bg-body row justify-content-center align-items-center p-4">
            <div class="auth-max-width col-sm-8 col-md-6 col-xl-7 px-4">

                <a class="logo-img d-block py-4 w-100">
                    <img src="/assets/images/logos/dark-logo.svg" class="dark-logo" alt="Logo-Dark" />
                </a>

                <h2 class="mb-4 fs-7 fw-bolder">Bienvenido al Portal3</h2>
            
                <form x-data="loginForm()" @submit.prevent="login">
                  <div class="mb-3">
                    <label for="usuario" class="form-label">Usuario</label>
                    <input type="text" class="form-control" id="usuario" x-model="usuario" :disabled="loading">
                  </div>
                  <div class="mb-4">
                    <label for="password" class="form-label">Contraseña</label>
                    <input type="password" class="form-control" id="password" x-model="password" :disabled="loading">
                  </div>
                  <div class="d-flex align-items-center justify-content-between mb-4">

                    <div class="form-check">
                      <input class="form-check-input primary" type="checkbox" value="" id="flexCheckChecked" checked>
                      <label class="form-check-label text-dark fs-3" for="flexCheckChecked">
                        Recordar este usuario
                      </label>
                    </div>
                
                  </div>
                  
                  <button
                    type="submit"
                    class="btn btn-primary w-100 py-8 mb-4 rounded-2"
                    :disabled="loading"
                    >
                    <template x-if="!loading">
                        <span>Iniciar sesión</span>
                    </template>

                    <template x-if="loading">
                        <span class="align-items-center gap-2">
                        <span class="spinner-border spinner-border-sm"></span>
                        Cargando...
                        </span>
                    </template>
                    </button>

                  <div x-show="message" x-transition
                  :class="type === 'success'? 'alert alert-success text-success': 'alert alert-danger text-danger'" role="alert">
                    <span x-text="message"></span>
                </div>
                 
                </form>
              </div>
            </div>
          </div>

          <div class="col-xl-7 col-xxl-8">
            <div class="d-none d-xl-flex align-items-center justify-content-center h-n80">
              <img src="/assets/images/backgrounds/login-security.svg" alt="modernize-img" class="img-fluid" width="500">
            </div>
          </div>
          
        </div>
      </div>
    </div>

  </div>
