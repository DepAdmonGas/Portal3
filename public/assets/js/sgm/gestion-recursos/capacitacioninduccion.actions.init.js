document.addEventListener('alpine:init', () => {
    Alpine.data('capacitacion', () => ({

     

      init(){

          window.capacitacion = this;

          if (!document.getElementById('sgm-content')) {
              return;
          }

      },

      
      
   }));
});