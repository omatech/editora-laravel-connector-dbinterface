<!doctype html>
<html lang="{{APP::getLocale()}}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <link rel="apple-touch-icon" sizes="57x57" href="/favicon/apple-icon-57x57.png">
    <link rel="apple-touch-icon" sizes="60x60" href="/favicon/apple-icon-60x60.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/favicon/apple-icon-72x72.png">
    <link rel="apple-touch-icon" sizes="76x76" href="/favicon/apple-icon-76x76.png">
    <link rel="apple-touch-icon" sizes="114x114" href="/favicon/apple-icon-114x114.png">
    <link rel="apple-touch-icon" sizes="120x120" href="/favicon/apple-icon-120x120.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/favicon/apple-icon-144x144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/favicon/apple-icon-152x152.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/favicon/apple-icon-180x180.png">
    <link rel="icon" type="image/png" sizes="192x192"  href="/favicon/android-icon-192x192.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/favicon/favicon-96x96.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon/favicon-16x16.png">
    <link rel="manifest" href="/favicon/manifest.json">
    <meta name="msapplication-TileColor" content="#ffffff">
    <meta name="msapplication-TileImage" content="/favicon/ms-icon-144x144.png">
    <meta name="theme-color" content="#ffffff">

    <link rel="stylesheet" href="{{ mix('/css/styles.css') }}" media="screen">

    <title>{{$global['title']}}</title>

    @if (App::environment(['local', 'staging', 'dev', 'development']))
        <meta name="robots" content="noindex, nofollow" />
    @endif
    @yield('extra-css')
    <script type="text/javascript">
        if(top.location != self.location) {
            parent.location = self.location;
        }
    </script>
</head>

<body class="hide-menu @if(isset($body_class)) {{$body_class}} @endif">
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

<header id="header">
    <div class="container">
        <div class="header-wrapper">
            <div class="left-side">
                <div class="phone dropdown">
                    <button type="button" class="toggle-phone" id="dropdownPhone" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="icon-phone-full"></i>
                    </button>
                    <span class="number dropdown-menu" aria-labelledby="dropdownPhone">{{_existAttribute($global, 'title_header')}}</span>
                </div>

                @generate_edit_link($global)

                <div class="logo-wrapper">
                    <a href="/" title="" class="logo"></a>
                    <span class="logo-text">This is a <br> CLAIM!</span>
                </div>
            </div>
            <div class="right-side">
                <nav id="nav" class="nav">
                    <div class="nav-wrapper">


                        {{--<ul class="tool-links">
                            <li><a href="/">FAQs</a></li>
                            <li><a href="/">News</a></li>
                        </ul>--}}

{{--                        @include('editora.templates.language_selector')--}}

                        {{--<a href="/" class="btn btn-primary btn-contact">Contact</a>--}}

                        <button type="button" class="toggle-menu" id="toggle-menu">
                            <i class="icon-burguer open-menu"></i>
                            <i class="icon-close close-menu"></i>
                            <span class="sr-only">Menú</span>
                        </button>

                        @if(_existRelationName($global, 'main_menu'))
                        <nav id="menu" class="menu">
                            <ul class="menu-list">
                                @foreach($global['relations']['main_menu']['instances'] as $main_menu)
                                    <li><a {{_destinationpages($main_menu)}}>{{_existName($main_menu,'title')}}</a></li>
                                @endforeach
                            </ul>
                        </nav>
                        @endif

                    </div>
                    <!-- WRAPPER -->
                </nav>
                <!-- /end NAVIGATION -->

                <!-- TOGGLE NAVEGACIO (tablet/mobil) -->
                <button type="button" class="toggle-menu" id="toggle-nav">
                    <i class="icon-burguer open-menu"></i>
                    <i class="icon-close close-menu"></i>
                    <span class="sr-only">Menú</span>
                </button>
                <!-- /end TOGGLE NAVEGACIO (tablet/mobil) -->

            </div>
            <!-- /end RIGHT SIDE -->

        </div>
        <!-- /end WRAPPER -->


    </div>
</header>
<!-- /end HEADER -->


@yield('body')


<!-- FOOTER -->
<footer id="footer">
    <div class="container">

        <div class="top-bar">

            <!-- LOGO -->
            <a href="/" title="Àptima Centre Clínic Mútua Terrarra" class="logo"><span class="sr-only">Name of the website</span></a>
            <!-- /end LOGO -->

            <!-- SOCIAL -->
            <ul class="social">
                <li><a href="/" title="Twitter"><i class="icon-twitter"></i><span class="sr-only">Twitter</span></a></li>
                <li><a href="/" title="Facebook"><i class="icon-facebook"></i><span class="sr-only">Facebook</span></a></li>
                <li><a href="/" title="Youtube"><i class="icon-youtube"></i><span class="sr-only">Youtube</span></a></li>
            </ul>
            <!-- /end SOCIAL -->

            <!-- MENU FOOTER -->
            <ul class="menu">
                <li><a href="/" title="">Menu item 1</a></li>
                <li><a href="/" title="">Menu item 2</a></li>
                <li><a href="/" title="">Menu item 3</a></li>
                <li><a href="/" title="">Menu item 4</a></li>
                <li><a href="/" title="">Menu item 5</a></li>
            </ul>
            <!-- /end MENU FOOTER -->

        </div>

        <div class="bottom-bar">

            <!-- LEGAL -->
            <ul class="legal">
                <li><a href="/" title="">Footer menu 1</a></li>
                <li><a href="/" title="">Footer menu 2</a></li>
                <li><a href="/" title="">Footer menu 3</a></li>
            </ul>
            <!--/end LEGAL -->

            <p class="copy">{{_statictext('text_copyright_footer')}}</p>

        </div>

    </div>
</footer>

<script src="{{asset('js/jquery.min.js')}}" type="text/javascript"></script>
<script src="{{asset('js/bootstrap.min.js')}}" type="text/javascript"></script>
<script src="{{asset('js/jquery.sticky.js')}}" type="text/javascript"></script>

<script src="{{asset('js/jquery.selectric.js')}}" type="text/javascript"></script>
<script src="{{asset('js/slick.min.js')}}" type="text/javascript"></script>

<script type="text/javascript">

    // sticky
    $(window).load(function(){
        $("#header").sticky({ topSpacing: 0 });
    });

    // menus
    $(document).ready( function(){
        $("#toggle-menu").click(function(e) {
            $("body").toggleClass("show-menu");
            $("body").toggleClass("hide-menu");
            $("body").removeClass("show-search");
            $("body").addClass("hide-search");
            e.preventDefault();
        });
        $("#toggle-nav").click(function(e) {
            $("body").toggleClass("show-menu");
            $("body").toggleClass("hide-menu");
            $("body").removeClass("show-search");
            $("body").addClass("hide-search");
            e.preventDefault();
        });
        $("#toggle-search").click(function(e) {
            $("body").toggleClass("show-search");
            $("body").toggleClass("hide-search");
            $("body").removeClass("show-menu");
            $("body").addClass("hide-menu");
            e.preventDefault();
        });
        $(".overlay").click(function() {
            $("body").addClass("hide-menu");
            $("body").removeClass("show-menu");
        });
    });
</script>

@section('extra-js')
@show

@section('extra-js-template')
@show

@editora_scripts
</body>
</html>
