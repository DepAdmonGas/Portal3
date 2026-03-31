document.addEventListener('alpine:init', () => {
    Alpine.data('politicaForm', () => ({

        init() {
            window.politicaInstance = this;
             this.loadFromHTML();
        },

        politica: '',
        mision: '',
        vision: '',
        loading: false,

        loadFromHTML() {

            const politicaEl = document.getElementById('politica_text');
            const misionEl   = document.getElementById('mision_text');
            const visionEl   = document.getElementById('vision_text');

            this.politica = politicaEl?.dataset.politica || '';
            this.mision   = misionEl?.dataset.mision || '';
            this.vision   = visionEl?.dataset.vision || '';

        },

        async submit() {
          
            try {

                const res = await this.createAction({
                url: '/sasisopa/politica/update',
                data: {
                    politica: this.politica,
                    mision: this.mision,
                    vision: this.vision
                },
                table: ''
                });
                          
                if (res && res.success) {

                    this.updateHTML();

                    const modal = bootstrap.Modal.getInstance(document.getElementById('editar'));
                    modal.hide();

                }

            
            } catch (error) {
            this.notify('error', 'Error al guardar');
            }

        },
        updateHTML() {

            const politicaEl = document.getElementById('politica_text');
            const misionEl   = document.getElementById('mision_text');
            const visionEl   = document.getElementById('vision_text');

            if (politicaEl) {
                politicaEl.innerText = this.politica;
                politicaEl.dataset.politica = this.politica;
            }

            if (misionEl) {
                misionEl.innerText = this.mision;
                misionEl.dataset.mision = this.mision;
            }

            if (visionEl) {
                visionEl.innerText = this.vision;
                visionEl.dataset.vision = this.vision;
            }

        }

    
    }));

});