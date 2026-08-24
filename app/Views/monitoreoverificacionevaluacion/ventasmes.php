<div id="container" class="pb-4"
x-data="ventasMes()">

    <div class="row mb-3 mt-3">
        <div class="col-md-12 d-flex justify-content-end">
            <select
                class="form-select "
                style="width: 200px;"
                x-model.number="year"
                @change="buscar()">
                <?php for($i = date('Y'); $i >= 2019; $i--): ?>
                    <option value="<?= $i ?>">
                        <?= $i ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>


<div class="table-responsive overflow-x-auto overflow-y-hidden">

    <table class="table table-striped table-bordered">
         

    <thead>
        <tr>
            <th>Mes</th>
            <th>Ventas</th>
            <th>Mes anterior</th>
            <th>Ventas</th>
            <th>Resultado</th>
        </tr>
    </thead>

    <tbody>

        <template x-for="item in ventas" :key="item.mes">

            <tr>

                <td x-text="item.mes"></td>

                <td class="text-end fw-bolder table-primary">
                    <span x-text="item.ventas_actual"></span>
                </td>

                <td x-text="item.mes_anterior"></td>

                <td class="text-end fw-bolder table-warning">
                    <span x-text="item.ventas_anterior"></span>
                </td>

                <td class="text-end fw-bolder">

                    <span
                        class="d-inline-flex align-items-center gap-1"
                        :class="{
                            'text-success': item.tendencia === 'ALZA',
                            'text-danger': item.tendencia === 'BAJA',
                            'text-secondary': item.tendencia === 'IGUAL'
                        }">

                        <!-- ALZA -->
                        <i
                            x-show="item.tendencia === 'ALZA'"
                            class="ti ti-trending-up">
                        </i>

                        <!-- BAJA -->
                        <i
                            x-show="item.tendencia === 'BAJA'"
                            class="ti ti-trending-down">
                        </i>

                        <!-- IGUAL -->
                        <i
                            x-show="item.tendencia === 'IGUAL'"
                            class="ti ti-minus">
                        </i>

                        <span x-text="item.resultado + '%'"></span>

                    </span>

                </td>

            </tr>

        </template>

    </tbody>

</table>

</div>

</div>