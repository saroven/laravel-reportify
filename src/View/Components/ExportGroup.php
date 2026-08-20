<?php

declare(strict_types=1);

namespace Saroven\Reportify\View\Components;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ExportGroup extends Component
{
    public function __construct(
        public readonly bool $hideOthers = false,
        public readonly bool $disableOthers = false,
        public readonly bool $hidePdf = false,
        public readonly ?string $exportOtherFormatTitle = null,
        public readonly ?array $excel = null,
        public readonly ?array $csv = null,
        public readonly ?array $txt = null,
        public readonly ?array $pdf = null,
        public readonly ?array $pdfStream = null,
        public readonly ?string $target = null,
        public readonly ?string $vIf = null
    ) {}

    public function render(): View
    {
        return view('reportify::components.export-group');
    }
}
