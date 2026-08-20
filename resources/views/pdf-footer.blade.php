<footer style="width: 100%; border-top: 1px solid #ddd; padding-top: 4px; font-size: 9px; font-family: sans-serif; color: #555;">
    <table style="width: 100%;">
        <tr>
            <td style="text-align: left;">
                @if(!($hide_print_date ?? false))
                    Print Date: {{ now()->format('Y-m-d H:i:s') }}
                @endif
                @if(!($hide_print_by ?? false) && isset($authUserInfo))
                    | User: {{ $authUserInfo->name ?? 'N/A' }}
                @endif
            </td>
            <td style="text-align: right;">
                @if(!($hide_page_number ?? false))
                    Page {PAGENO} of {nbpg}
                @endif
            </td>
        </tr>
    </table>
    @if(!empty($additional_footer ?? ''))
        <div style="margin-top: 2px;">{!! $additional_footer !!}</div>
    @endif
</footer>
