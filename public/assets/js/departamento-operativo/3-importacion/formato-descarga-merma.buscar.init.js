$(document).ready(function () {
    if (typeof ModuleStationSelector !== 'undefined') {
        ModuleStationSelector.init('formato-descarga-merma', {
            customReload: function () {
                window.location.reload();
            }
        });
    }
});
