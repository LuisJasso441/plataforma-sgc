{{-- Logo de la empresa. Archivo: public/images/logo-empresa.png (ver config/sga.php) --}}
@php
    $logo = config('sga.logo_empresa');
@endphp

@if ($logo && is_file(public_path($logo)))
    <img src="{{ asset($logo) }}" alt="Logo de la empresa" {{ $attributes->merge(['class' => 'object-contain']) }}>
@else
    {{-- LOGO PENDIENTE: guardar el logo en public/images/logo-empresa.png --}}
    <div {{ $attributes->merge(['class' => 'flex items-center justify-center rounded-md border-2 border-dashed border-current/40 text-xs font-semibold tracking-widest opacity-70']) }}>
        LOGO PENDIENTE
    </div>
@endif