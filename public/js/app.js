/* Funeraria Cristo Rey — interacciones ligeras sin dependencias */
(function () {
    'use strict';

    // Menú lateral en móvil
    document.querySelectorAll('[data-toggle-menu]').forEach(function (b) {
        b.addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('abierto');
        });
    });

    // Confirmación antes de acciones sensibles
    document.querySelectorAll('form[data-confirmar]').forEach(function (f) {
        f.addEventListener('submit', function (e) {
            if (!window.confirm(f.getAttribute('data-confirmar'))) { e.preventDefault(); }
        });
    });

    // Formularios con motivo obligatorio (observar / rechazar / anular)
    document.querySelectorAll('[data-motivo]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var form = btn.closest('form');
            var campo = form.querySelector('input[name="' + btn.getAttribute('data-motivo') + '"]');
            var texto = window.prompt(btn.getAttribute('data-pregunta') || 'Indique el motivo:');
            if (!texto) { e.preventDefault(); return; }
            campo.value = texto;
        });
    });

    // Filtro en tiempo real de listas (buscador de familias)
    document.querySelectorAll('[data-filtro-lista]').forEach(function (input) {
        var lista = document.querySelector(input.getAttribute('data-filtro-lista'));
        if (!lista) return;
        input.addEventListener('input', function () {
            var t = input.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
            lista.querySelectorAll('[data-buscar]').forEach(function (item) {
                var txt = item.getAttribute('data-buscar').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
                item.style.display = txt.indexOf(t) !== -1 ? '' : 'none';
            });
        });
    });

    // Validación de RUT chileno (módulo 11) en el navegador
    function rutValido(rut) {
        var r = (rut || '').replace(/[^0-9kK]/g, '').toUpperCase();
        if (r.length < 2) return false;
        var cuerpo = r.slice(0, -1), dv = r.slice(-1), suma = 0, mult = 2;
        for (var i = cuerpo.length - 1; i >= 0; i--) { suma += parseInt(cuerpo[i], 10) * mult; mult = mult === 7 ? 2 : mult + 1; }
        var res = 11 - (suma % 11);
        var esperado = res === 11 ? '0' : res === 10 ? 'K' : String(res);
        return esperado === dv;
    }
    function formatearRut(rut) {
        var r = (rut || '').replace(/[^0-9kK]/g, '').toUpperCase();
        if (r.length < 2) return rut;
        var cuerpo = r.slice(0, -1).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return cuerpo + '-' + r.slice(-1);
    }
    document.querySelectorAll('input[data-rut]').forEach(function (input) {
        var msg = document.createElement('div');
        msg.className = 'error-msg hidden';
        msg.textContent = 'RUT inválido: revise el dígito verificador.';
        input.insertAdjacentElement('afterend', msg);
        input.addEventListener('blur', function () {
            if (!input.value) { msg.classList.add('hidden'); input.classList.remove('invalido'); return; }
            input.value = formatearRut(input.value);
            var ok = rutValido(input.value);
            msg.classList.toggle('hidden', ok);
            input.classList.toggle('invalido', !ok);
        });
    });

    // Asistente por pasos (nueva solicitud)
    document.querySelectorAll('[data-wizard]').forEach(function (wz) {
        var paneles = wz.querySelectorAll('.panel-paso');
        var pasos = wz.querySelectorAll('.paso');
        var actual = 0;
        function mostrar(i) {
            actual = Math.max(0, Math.min(i, paneles.length - 1));
            paneles.forEach(function (p, j) { p.classList.toggle('visible', j === actual); });
            pasos.forEach(function (p, j) {
                p.classList.toggle('activo', j === actual);
                p.classList.toggle('hecho', j < actual);
            });
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
        wz.querySelectorAll('[data-siguiente]').forEach(function (b) {
            b.addEventListener('click', function () {
                var panel = paneles[actual];
                var invalidos = Array.prototype.filter.call(panel.querySelectorAll('input,select,textarea'), function (el) { return !el.checkValidity(); });
                if (invalidos.length) { invalidos[0].reportValidity(); return; }
                mostrar(actual + 1);
                actualizarResumen(wz);
            });
        });
        wz.querySelectorAll('[data-anterior]').forEach(function (b) {
            b.addEventListener('click', function () { mostrar(actual - 1); });
        });
        // Si hay errores del servidor, abrir el paso que los contiene
        var conError = -1;
        paneles.forEach(function (p, j) { if (conError < 0 && p.querySelector('.error')) conError = j; });
        mostrar(conError >= 0 ? conError : 0);
    });

    function actualizarResumen(wz) {
        wz.querySelectorAll('[data-resumen]').forEach(function (el) {
            var campo = wz.querySelector('[name="' + el.getAttribute('data-resumen') + '"]');
            if (!campo) return;
            el.textContent = campo.tagName === 'SELECT' ? (campo.options[campo.selectedIndex] || {}).text || '—' : (campo.value || '—');
        });
    }

    // Verificación de disponibilidad (solapamiento) en vivo
    document.querySelectorAll('[data-disponibilidad]').forEach(function (box) {
        var url = box.getAttribute('data-disponibilidad');
        var form = box.closest('form');
        var fecha = form.querySelector('[name="fecha"]');
        var hora = form.querySelector('[name="hora"]');
        var excepto = box.getAttribute('data-excepto') || '';
        function consultar() {
            if (!fecha.value || !hora.value) return;
            fetch(url + '?fecha=' + encodeURIComponent(fecha.value) + '&hora=' + encodeURIComponent(hora.value) + '&excepto=' + excepto, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    box.className = 'aviso ' + (d.disponible ? 'aviso-ok' : 'aviso-warn');
                    box.textContent = d.mensaje + ' (' + d.servicios_del_dia + ' servicio(s) ese día)';
                }).catch(function () {});
        }
        fecha.addEventListener('change', consultar);
        hora.addEventListener('change', consultar);
        consultar();
    });

    // Cálculo en vivo del total y abono mínimo (aprobación)
    document.querySelectorAll('[data-calculo]').forEach(function (form) {
        var urna = parseInt(form.getAttribute('data-urna') || '0', 10);
        var pct = parseInt(form.getAttribute('data-pct') || '50', 10);
        var servicio = form.querySelector('[name="monto_servicio"]');
        var descuento = form.querySelector('[name="descuento"]');
        var convenio = form.querySelector('[name="convenio_id"]');
        var abono = form.querySelector('[name="abono_inicial"]');
        var fmt = function (n) { return '$' + Math.round(n).toLocaleString('es-CL'); };
        var selUnidad = form.querySelector('[name="urna_unidad_id"]');
        function precioUrna() {
            if (selUnidad) {
                var o = selUnidad.options[selUnidad.selectedIndex];
                if (o && o.getAttribute('data-precio')) return parseInt(o.getAttribute('data-precio'), 10) || 0;
            }
            return urna;
        }
        if (selUnidad) selUnidad.addEventListener('change', function () { calcular(); });
        function calcular() {
            var sub = (parseInt(servicio.value || '0', 10) || 0) + precioUrna();
            var opt = convenio && convenio.options[convenio.selectedIndex];
            var pctConv = opt ? parseInt(opt.getAttribute('data-pct') || '0', 10) : 0;
            if (descuento && descuento.getAttribute('data-auto') !== 'no') {
                descuento.value = Math.round(sub * pctConv / 100);
            }
            var desc = parseInt((descuento && descuento.value) || '0', 10) || 0;
            var total = Math.max(0, sub - desc);
            var min = Math.round(total * pct / 100);
            form.querySelector('[data-out="subtotal"]').textContent = fmt(sub);
            form.querySelector('[data-out="total"]').textContent = fmt(total);
            form.querySelector('[data-out="minimo"]').textContent = fmt(min);
            if (abono && !abono.getAttribute('data-tocado')) abono.value = min;
        }
        if (descuento) descuento.addEventListener('input', function () { descuento.setAttribute('data-auto', 'no'); calcular(); });
        if (abono) abono.addEventListener('input', function () { abono.setAttribute('data-tocado', '1'); });
        [servicio, convenio].forEach(function (el) { if (el) el.addEventListener('change', calcular); });
        if (servicio) servicio.addEventListener('input', calcular);
        calcular();
    });

    // Filas dinámicas (personal asignado / hoja de ruta)
    document.querySelectorAll('[data-agregar-fila]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var tbody = document.querySelector(btn.getAttribute('data-agregar-fila'));
            var plantilla = tbody.querySelector('tr:last-child');
            var nueva = plantilla.cloneNode(true);
            var idx = tbody.querySelectorAll('tr').length;
            nueva.querySelectorAll('input,select').forEach(function (el) {
                el.name = el.name.replace(/\[\d+\]/, '[' + idx + ']');
                el.value = '';
            });
            tbody.appendChild(nueva);
        });
    });

    // Copiar texto (enlace del servicio / hoja de ruta)
    document.querySelectorAll('[data-copiar]').forEach(function (b) {
        b.addEventListener('click', function () {
            var txt = b.getAttribute('data-copiar');
            if (navigator.clipboard) navigator.clipboard.writeText(txt);
            var original = b.textContent; b.textContent = 'Copiado ✓';
            setTimeout(function () { b.textContent = original; }, 1600);
        });
    });
})();

/* Galería de fotos: flechas, puntos, miniaturas, deslizamiento táctil y avance automático */
(function () {
    'use strict';
    document.querySelectorAll('[data-galeria]').forEach(function (g) {
        var pista = g.querySelector('.pista');
        var slides = g.querySelectorAll('.slide');
        var puntos = g.querySelectorAll('.puntos button');
        var minis = document.querySelectorAll('[data-galeria-mini="' + g.id + '"] button');
        var i = 0, n = slides.length, timer = null;
        if (n < 2) return;
        function ir(k) {
            i = (k + n) % n;
            pista.style.transform = 'translateX(' + (-100 * i) + '%)';
            puntos.forEach(function (p, j) { p.classList.toggle('activo', j === i); });
            minis.forEach(function (p, j) { p.classList.toggle('activo', j === i); });
        }
        function auto() { clearInterval(timer); timer = setInterval(function () { ir(i + 1); }, 4500); }
        g.querySelector('.prev').addEventListener('click', function () { ir(i - 1); auto(); });
        g.querySelector('.next').addEventListener('click', function () { ir(i + 1); auto(); });
        puntos.forEach(function (p, j) { p.addEventListener('click', function () { ir(j); auto(); }); });
        minis.forEach(function (p, j) { p.addEventListener('click', function () { ir(j); auto(); }); });
        var x0 = null;
        g.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; }, { passive: true });
        g.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0;
            if (Math.abs(dx) > 40) { ir(dx < 0 ? i + 1 : i - 1); auto(); }
            x0 = null;
        });
        g.addEventListener('mouseenter', function () { clearInterval(timer); });
        g.addEventListener('mouseleave', auto);
        ir(0); auto();
    });

    // Miniatura de foto al elegir archivos en formularios
    document.querySelectorAll('input[type=file][data-vista-previa]').forEach(function (input) {
        var cont = document.querySelector(input.getAttribute('data-vista-previa'));
        input.addEventListener('change', function () {
            cont.innerHTML = '';
            Array.prototype.forEach.call(input.files, function (f) {
                var url = URL.createObjectURL(f);
                var d = document.createElement('div');
                d.style.cssText = 'width:72px;height:50px;border-radius:6px;background:center/cover url(' + url + ')';
                cont.appendChild(d);
            });
        });
    });
})();

/* Vista previa inmediata de una imagen como fondo (Configuración del sitio) */
document.querySelectorAll('input[type=file][data-previa-fondo]').forEach(function (input) {
    var caja = document.querySelector(input.getAttribute('data-previa-fondo'));
    input.addEventListener('change', function () {
        if (input.files && input.files[0]) caja.style.backgroundImage = 'url(' + URL.createObjectURL(input.files[0]) + ')';
    });
});

// Flota: volver a cargar en el formulario los datos que se ingresaron en la solicitud
(function () {
    var btn = document.querySelector('[data-cargar-servicio]');
    if (!btn) return;
    btn.addEventListener('click', function () {
        var d = JSON.parse(btn.getAttribute('data-cargar-servicio') || '{}');
        ['inicio', 'fin', 'origen', 'destino'].forEach(function (k) {
            var el = document.querySelector('[data-campo-asig="' + k + '"]');
            if (el) el.value = d[k] || '';
        });
        var tbody = document.getElementById('filas-ruta');
        if (!tbody) return;
        var plantilla = tbody.querySelector('tr');
        var paradas = (d.paradas && d.paradas.length) ? d.paradas : [{}];
        tbody.innerHTML = '';
        paradas.forEach(function (p, i) {
            var fila = plantilla.cloneNode(true);
            fila.querySelectorAll('input').forEach(function (el) {
                el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                var campo = (el.name.match(/\[(\w+)\]$/) || [])[1];
                el.value = p[campo] || '';
            });
            tbody.appendChild(fila);
        });
        btn.textContent = 'Datos cargados ✓';
    });
})();

// Cementerio o crematorio: la lista se filtra según el destino final (sepultura / cremación)
(function () {
    document.querySelectorAll('[data-lugar-destino]').forEach(function (sel) {
        var form = sel.closest('form');
        var texto = sel.parentNode.querySelector('[name="cementerio_crematorio"]');
        var destino = form ? form.querySelector('[name="destino_final"]') : null;
        var comuna = form ? form.querySelector('[name="comuna_destino"]') : null;
        var ayuda = sel.parentNode.querySelector('[data-lugar-ayuda]');
        var porDestino = { sepultura: 'cementerio', cremacion: 'crematorio' };

        function filtrar() {
            var tipo = destino ? porDestino[destino.value] : null;
            var visibles = 0;
            sel.querySelectorAll('optgroup').forEach(function (g) {
                var hay = false;
                g.querySelectorAll('option').forEach(function (o) {
                    var ok = !tipo || o.getAttribute('data-tipo') === tipo;
                    o.hidden = !ok; o.disabled = !ok;
                    if (ok) { hay = true; visibles++; }
                });
                g.hidden = !hay;
            });
            var elegida = sel.options[sel.selectedIndex];
            if (elegida && elegida.disabled) sel.value = '';
            if (ayuda) {
                ayuda.textContent = visibles
                    ? 'Se muestran primero los ' + (tipo === 'crematorio' ? 'crematorios' : 'cementerios') + ' más cercanos a Temuco.'
                    : 'No hay ' + (tipo === 'crematorio' ? 'crematorios' : 'cementerios') + ' registrados en la lista: escríbalo abajo.';
            }
        }

        sel.addEventListener('change', function () {
            if (sel.value === '__otro') { texto.value = ''; texto.focus(); return; }
            if (!sel.value) return;
            texto.value = sel.value;
            var o = sel.options[sel.selectedIndex];
            if (comuna && o.getAttribute('data-comuna')) comuna.value = o.getAttribute('data-comuna');
        });
        if (destino) destino.addEventListener('change', filtrar);
        filtrar();
    });
})();
