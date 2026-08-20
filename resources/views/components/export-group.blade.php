<div class="action-buttons-container {{ $attributes->get('class') }}">
    {{ $additionalContentBefore ?? '' }}

    @if(!isset($hideOthers) || !$hideOthers)
    <div class="btn-group" id="exportInOtherFormat" @isset($vIf)v-if="{{ $vIf }}"@endisset>
        <button type="button" class="btn btn-info dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="true" @if(isset($disableOthers) && $disableOthers) disabled @endif>
            <i class="fas fa-file-export"></i> {{ $exportOtherFormatTitle ?? (is_mobile() ? 'Export' : 'Export In Other Format') }} <i class="mdi mdi-chevron-down"></i>
        </button>
        <div class="dropdown-menu dropdownmenu-primary"
            style="position: absolute; inset: auto auto 0 0; margin: 0; transform: translate(0px, -40px);"
            data-popper-placement="top-start"
        >
            @if(isset($excel))<a class="dropdown-item {{ isset($excel['disabled']) ? 'disabled-link' : '' }}" @if(isset($target)) target="{{ $target }}" @endif   href="{{ $excel['url'] ?? '#' }}" @isset($excel['onClick'])onclick="{{ $excel['onClick'] }}"@endisset><i class="fas fa-file-excel"></i> Excel</a>@endif
            @if(isset($csv))<a class="dropdown-item {{ isset($csv['disabled']) ? 'disabled-link' : '' }}" @if(isset($target)) target="{{ $target }}" @endif  href="{{ $csv['url'] ?? '#' }}" @isset($csv['onClick'])onclick="{{ $csv['onClick'] }}"@endisset><i class="fas fa-file-csv"></i> CSV</a>@endif
            @if(isset($txt))<a class="dropdown-item {{ isset($txt['disabled']) ? 'disabled-link' : '' }}" @if(isset($target)) target="{{ $target }}" @endif  href="{{ $txt['url'] ?? '#' }}" @isset($txt['onClick'])onclick="{{ $txt['onClick'] }}"@endisset><i class="fas fa-file-alt"></i> TXT</a>@endif
        </div>
    </div>
    @endif
    @if((!isset($hidePdf) || !$hidePdf) && isset($pdfStream))
    <a id="printBtn" href="{{ $pdfStream['url'] ?? '#' }}" @if(isset($target)) target="{{ $target }}" @endif class="btn btn-primary w-sm {{ isset($pdfStream['disabled']) && $pdfStream['disabled'] ? 'disabled-link' : '' }}" @isset($pdfStream['onClick'])onclick="{{ $pdfStream['onClick'] }}"@endisset @isset($vIf)v-if="{{ $vIf }}"@endisset><i class="fa fa-print"></i> @mobile Print @else Print @endmobile</a>
    @endif

    @if((!isset($hidePdf) || !$hidePdf) && isset($pdf))
    <a id="pdfExportBtn" href="{{ $pdf['url'] ?? '#' }}" @if(isset($target)) target="{{ $target }}" @endif  class="btn btn-danger w-sm {{ isset($pdf['disabled']) ? 'disabled-link' : '' }}" @isset($pdf['onClick'])onclick="{{ $pdf['onClick'] }}"@endisset @isset($vIf)v-if="{{ $vIf }}"@endisset><i class="fa fa-file-pdf"></i> @mobile PDF @else PDF Export @endmobile</a>
    @endif

    {{ $additionalContentAfter ?? '' }}
</div>
