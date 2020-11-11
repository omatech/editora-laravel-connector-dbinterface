@extends('layouts.app')

@section('body')
<main id="main">
    <div class="container">
        <ul class="breadcrumbs" type="nav">
            <li><a href="/">Home</a></li>
            <li>Error 404</li>
        </ul>
        <div class="title-row">
            <a href="/" class="btn-square btn-gray"><i class="mdi mdi-home"></i></a>
            <h2 class="page-title">Pàgina no trobada</h2>
        </div>
        <section class="main-content">
            <header class="news-title-row">
                <h4 class="epi">Pàgina no trobada</h4>
            </header>
        </section>
    </div>
</main>
@stop
