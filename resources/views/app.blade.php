<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="description" content="QC Admission Application" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title inertia>{{ config('app.name', 'QC Admission') }}</title>
    @vite(['resources/js/src/main.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
