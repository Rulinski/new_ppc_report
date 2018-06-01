<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    @stack('stylesheet')
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/custom.css') }}" rel="stylesheet">
</head>
<body>
<div id="app">
    <nav class="navbar navbar-default navbar-static-top">
        <div class="container">
            <a class="navbar-brand" href="https://docs.google.com/spreadsheets/d/1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I/edit#gid=1311329247">
                Main Report
            </a>
            <a class="navbar-brand" href="https://docs.google.com/spreadsheets/d/1Q4j81zbUXfi2trsiZORF0fGgx_cSFKN5uokJIZOwP0I/edit#gid=1774056017">
                Clone Report
            </a>
            <div id="form-data">
            <form action="" method="POST">
                {{ csrf_field() }}
                <div class="navbar-brand">
                    <select size="1" name="type-report">
                        <option value="0">Main</option>
                        <option value="1">Clone</option>
                    </select>
                </div>
                <div class="navbar-brand">From: <input type="date" name="date-from"></div>
                <div class="navbar-brand">To: <input type="date" name="date-to"></div>
                <div class="navbar-brand"><button type="submit" name="action" value="save">Processing</button></div>
            </form>

            <div >
        </div>
    </nav>
    <div>
        {{ $message }}
    </div>

    @yield('content')
</div>

<!-- Scripts -->
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')

</body>
</html>