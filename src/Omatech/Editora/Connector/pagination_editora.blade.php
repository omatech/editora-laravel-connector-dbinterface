<div class="pager-row">
    <div class="container">
        <nav class="pagination">

            @if ($paginator['onFirstPage'])
                <a class="pager-arrow pager-prev"><span class="sr-only">{{_statictext('paginator.previous_page')}}</span></a>
            @else
                <a href="{{ $paginator['elements'][$paginator['previousPage']]['url'] }}" class="pager-arrow pager-prev"><span class="sr-only">{{_statictext('paginator.previous_page')}}</span></a>
            @endif

            <ul class="pager-list">
                @foreach ($paginator['elements'] as $page_num => $element)
                    @if (is_string($element))
                        <li class="disabled"><a href="#">{{ $element }}</a></li>
                    @endif
                    @if (is_array($element))
                        @if ($element['isCurrent'])
                            <li class="active">{{ $page_num }}</li>
                        @else
                            <li><a href="{{ $element['url'] }}">{{ $page_num }}</a></li>
                        @endif
                    @endif
                @endforeach
            </ul>

            @if ($paginator['hasMorePages'])
                <a href="{{ $paginator['elements'][$paginator['nextPage']]['url'] }}" class="pager-arrow pager-next"><span class="sr-only">{{_statictext('paginator.next_page')}}</span></a>
            @else
                <a class="pager-arrow pager-next"><span class="sr-only">{{_statictext('paginator.next_page')}}</span></a>
            @endif
        </nav>
    </div>
</div>