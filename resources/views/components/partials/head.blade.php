<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="{{ asset('favicon.svg') }}">

<title>{{ config('app.name', 'Investbrain') }}</title>

@vite(['resources/css/app.css', 'resources/js/app.js'])

@livewireStyles