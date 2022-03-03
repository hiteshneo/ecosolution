<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Voxo</title>

    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css?family=Nunito:200,600" rel="stylesheet">

    <!-- Styles -->
    <style>
    html,
    body {
        background-color: #fff;

        font-family: 'Nunito', sans-serif;



    }
    </style>
</head>

<body>
    <div style="text-align:center">
        <h2>{{ $data->title }}</h2>
    </div>
    {!! $data->body !!}

</body>

</html>