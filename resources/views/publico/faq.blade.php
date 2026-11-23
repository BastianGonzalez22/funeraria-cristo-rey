@extends('layouts.publico')
@section('titulo', 'Preguntas frecuentes')
@section('contenido')
<section class="seccion-pub faq" style="max-width:860px">
    <h2 class="mb-2">Preguntas frecuentes</h2>
    <details open><summary>¿Qué hago primero cuando fallece un familiar?</summary>
        <p class="small">Llámenos al {{ config('funeraria.telefono') }} (24 horas). Solicite el certificado de defunción en el hospital o al médico tratante. Nosotros coordinamos el retiro, la preparación y el velatorio.</p></details>
    <details><summary>¿Qué documentos necesito?</summary>
        <p class="small">Certificado de defunción, carné de identidad del fallecido y carné de la persona que contrata el servicio.</p></details>
    <details><summary>¿Qué incluye el servicio?</summary>
        <p class="small">En todos los servicios entregamos cruz, tarjetas de agradecimiento, foto marcada y flores. El valor cambia según la urna elegida. El servicio dura normalmente 48 horas y puede extenderse.</p></details>
    <details><summary>¿Cómo se paga?</summary>
        <p class="small">Para aceptar la solicitud se requiere un abono del {{ config('funeraria.abono_minimo_pct') }}% del valor; el saldo puede pagarse en cuotas. Aceptamos efectivo, transferencia, tarjetas y la cuota mortuoria de AFP o IPS. Cada pago tiene su comprobante digital.</p></details>
    <details><summary>¿Qué es la cuota mortuoria?</summary>
        <p class="small">Es un beneficio de la AFP o del IPS para cubrir gastos funerarios ({{ config('funeraria.cuota_mortuoria_uf') }} UF para pensionados). Por ejemplo, si el servicio vale $1.000.000 y la cuota cubre $600.000, la familia paga la diferencia. Los menores de 18 años no tienen este beneficio.</p></details>
    <details><summary>¿Atienden todas las religiones?</summary>
        <p class="small">Sí, realizamos servicios para todas las religiones y ceremonias laicas.</p></details>
    <details><summary>¿Pueden trasladar a otra comuna o región?</summary>
        <p class="small">Sí. Gestionamos la resolución de traslado y el permiso sanitario ante la SEREMI de Salud.</p></details>
    <details><summary>¿Puedo cambiar el horario o el recorrido?</summary>
        <p class="small">Sí, avísenos con la mayor anticipación posible. Los cambios quedan registrados en su solicitud.</p></details>
    <details><summary>¿Puedo compartir la información del velatorio con mis familiares?</summary>
        <p class="small">Sí. Le enviamos un enlace con el lugar del velatorio, la misa y el traslado, además de un libro de condolencias digital.</p></details>
    <p class="mt-3">¿Otra consulta? <a href="{{ route('publico.contacto') }}">Escríbanos</a> o use el botón de WhatsApp.</p>
</section>
@endsection
