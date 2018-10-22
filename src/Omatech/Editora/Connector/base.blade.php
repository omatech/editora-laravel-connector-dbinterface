<!doctype html>
<html lang="{{APP::getLocale()}}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">


    {{--<link rel="stylesheet" href="{{ mix('/css/styles.css') }}" media="screen">--}}

    <title>Title</title>

    @if (App::environment(['local', 'staging', 'dev', 'development', 'test']))
        <meta name="robots" content="noindex, nofollow" />
    @endif
    @yield('extra-css')
    <script type="text/javascript">
        if(top.location != self.location) {
            parent.location = self.location;
        }
    </script>
</head>

<body>
@if (!App::environment(['local', 'staging', 'dev', 'development']))
    <script type="text/javascript">
        var _gaq = _gaq || [];
        _gaq.push(['_setAccount', 'UA-XXXX-1']);
        _gaq.push(['_setDomainName', 'YYYYYYYYY']);
        _gaq.push(['_trackPageview']);
        (function() {
            var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
            ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
            var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
        })();
    </script>
@endif


@yield('content')



<footer id="footer">

</footer>


@section('extra-js')
@show


@editora_scripts
</body>
</html>
