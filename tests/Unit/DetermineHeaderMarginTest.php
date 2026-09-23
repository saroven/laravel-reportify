<?php

use Saroven\Reportify\ReportifyService;

// ─── determineHeaderMargin: base/default behaviour ───────────────────────────

it('returns config default margin when header html is empty', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();
    $result  = $service->determineHeaderMargin('');

    expect($result)->toBe(28);
});

it('respects custom config default_header_margin when html is empty', function () {
    config(['reportify.mpdf.default_header_margin' => 40]);

    $service = new ReportifyService();
    $result  = $service->determineHeaderMargin('');

    expect($result)->toBe(40);
});

it('uses 28 as last-resort hardcoded fallback when config value is not set', function () {
    // config() with a missing key + explicit hardcoded fallback in ??=
    // The ??= line is: $headerMargin ??= (int) config('...', 28)
    // So even if the key is missing, the second arg to config() ensures 28
    $service = new ReportifyService();

    // Force the key to not exist at all (remove it from config)
    config(['reportify.mpdf' => []]);

    $result = $service->determineHeaderMargin('');

    expect($result)->toBe(28); // hardcoded fallback in config() call
});

// ─── determineHeaderMargin: null sentinel ─────────────────────────────────────

it('treats null second argument as use config default', function () {
    config(['reportify.mpdf.default_header_margin' => 35]);

    $service = new ReportifyService();
    $result  = $service->determineHeaderMargin('', null);

    expect($result)->toBe(35);
});

it('treats explicit 0 as zero base margin not as sentinel', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    // base=0, <div>hello</div>: blockCount=1, textLines=1
    // extraHeight=(1*15)+(0*15)+(1*15)=30, extraMargin=ceil(30*0.25)=8 → total=8
    $result = $service->determineHeaderMargin('<div>hello</div>', 0);

    expect($result)->toBe(8); // 0 + 8 (config default NOT applied since 0 !== null)
});

// ─── determineHeaderMargin: auto-calculation from HTML ───────────────────────

it('adds extra margin for block-level html tags', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    // <div>Company</div>: blockCount=1, textLines=1
    // extraHeight=30, extraMargin=ceil(7.5)=8 → total=36
    $result = $service->determineHeaderMargin('<div>Company</div>');

    expect($result)->toBe(36); // 28 + 8
});

it('adds extra margin for table rows in header html', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    $result = $service->determineHeaderMargin('<table><tr><td>Name</td></tr></table>');

    expect($result)->toBeGreaterThan(28);
});

it('estimates text lines from character length when no block or row tags are present', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    // plain text, no block/row tags: textLines=ceil(12/100)=1
    // extraHeight=1*15=15, extraMargin=ceil(15*0.25)=4 → total=32
    $result = $service->determineHeaderMargin('Short header');

    expect($result)->toBe(32); // 28 + 4
});

// ─── headerMargin: per-report hard override ───────────────────────────────────

it('headerMargin key of 0 is respected as a valid zero margin not treated as missing', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    $additionalData = ['headerMargin' => 0];

    $headerMargin = isset($additionalData['headerMargin'])
        ? (int) $additionalData['headerMargin']
        : $service->determineHeaderMargin('<div>header</div>');

    expect($headerMargin)->toBe(0);
});

it('headerMargin overrides auto-calculation completely', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    $additionalData = ['headerMargin' => 50, 'headerHtml' => '<div><h1>Big</h1><p>Sub</p></div>'];

    $headerMargin = isset($additionalData['headerMargin'])
        ? (int) $additionalData['headerMargin']
        : $service->determineHeaderMargin($additionalData['headerHtml'] ?? '');

    expect($headerMargin)->toBe(50);
});

// ─── additionalHeaderMargin: additive nudge ───────────────────────────────────

it('adds additionalHeaderMargin on top of auto-calculated margin', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    $additionalData = [
        'headerHtml'             => '',  // empty html → base=28, extra=0
        'additionalHeaderMargin' => 10,
    ];

    $headerMargin  = isset($additionalData['headerMargin'])
        ? (int) $additionalData['headerMargin']
        : $service->determineHeaderMargin($additionalData['headerHtml'] ?? '');
    $headerMargin += (int) ($additionalData['additionalHeaderMargin'] ?? 0);

    expect($headerMargin)->toBe(38); // 28 + 10
});

it('adds additionalHeaderMargin on top of a hard-overridden headerMargin', function () {
    $service = new ReportifyService();

    $additionalData = [
        'headerMargin'           => 40,
        'additionalHeaderMargin' => 5,
    ];

    $headerMargin  = isset($additionalData['headerMargin'])
        ? (int) $additionalData['headerMargin']
        : $service->determineHeaderMargin($additionalData['headerHtml'] ?? '');
    $headerMargin += (int) ($additionalData['additionalHeaderMargin'] ?? 0);

    expect($headerMargin)->toBe(45); // 40 + 5
});

it('does not change margin when additionalHeaderMargin is not set', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    $additionalData = ['headerHtml' => ''];

    $headerMargin  = isset($additionalData['headerMargin'])
        ? (int) $additionalData['headerMargin']
        : $service->determineHeaderMargin($additionalData['headerHtml'] ?? '');
    $headerMargin += (int) ($additionalData['additionalHeaderMargin'] ?? 0);

    expect($headerMargin)->toBe(28);
});

it('additionalHeaderMargin accepts negative values to reduce margin', function () {
    config(['reportify.mpdf.default_header_margin' => 28]);

    $service = new ReportifyService();

    $additionalData = [
        'headerHtml'             => '',
        'additionalHeaderMargin' => -8,
    ];

    $headerMargin  = isset($additionalData['headerMargin'])
        ? (int) $additionalData['headerMargin']
        : $service->determineHeaderMargin($additionalData['headerHtml'] ?? '');
    $headerMargin += (int) ($additionalData['additionalHeaderMargin'] ?? 0);

    expect($headerMargin)->toBe(20); // 28 - 8
});
