<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Access control') · {{ config('app.name') }}</title>
    <style>
        body { margin: 0; background: #f6f7f9; }
        @media (prefers-color-scheme: dark) { body { background: #111318; } }
    </style>
</head>
<body>
    @yield($pm->section())
</body>
</html>
