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

axios.post('/login/acceso', {
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
.catch((e) => {
this.message = e;
this.type = 'error';
this.loading = false;
});
}
}
}
