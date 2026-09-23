<?php

use Saroven\Reportify\PDF\PdfEngine;

// ─── setPaper guard ───────────────────────────────────────────────────────────

it('throws LogicException when setPaper is called after loadView', function () {
    $engine = new PdfEngine();

    // loadView before setPaper — then setPaper must throw
    // We need a minimal real view; use the package's bundled empty-pdf view
    // so we call loadBodyHtml directly to simulate loadView having been called
    $engine->loadBodyHtml('<p>test</p>');

    expect(fn () => $engine->setPaper('A4', 'P'))
        ->toThrow(LogicException::class, 'setPaper() must be called before loadView()');
});

it('does not throw when setPaper is called before loadView', function () {
    expect(fn () => (new PdfEngine())->setPaper('A4', 'P'))
        ->not->toThrow(LogicException::class);
});

it('does not throw when setPaper is called on a fresh instance with no body html', function () {
    expect(fn () => (new PdfEngine())->setPaper('A3', 'L'))
        ->not->toThrow(LogicException::class);
});

// ─── setPageMargins: nullable params ─────────────────────────────────────────

it('sets all four margins equally when only one argument is passed', function () {
    $engine = new PdfEngine();
    $engine->setPageMargins(10);

    $mpdf = $engine->getInstance();

    expect($mpdf->tMargin)->toBe(10);
    expect($mpdf->bMargin)->toBe(10);
    expect($mpdf->DeflMargin)->toBe(10);
    expect($mpdf->DefrMargin)->toBe(10);
});

it('sets top and bottom equal to right when two arguments are passed', function () {
    $engine = new PdfEngine();
    $engine->setPageMargins(5, 15);

    $mpdf = $engine->getInstance();

    expect($mpdf->DeflMargin)->toBe(5);
    expect($mpdf->DefrMargin)->toBe(15);
    expect($mpdf->tMargin)->toBe(15);
    expect($mpdf->bMargin)->toBe(15);
});

it('sets bottom equal to top when three arguments are passed', function () {
    $engine = new PdfEngine();
    $engine->setPageMargins(5, 5, 20);

    $mpdf = $engine->getInstance();

    expect($mpdf->tMargin)->toBe(20);
    expect($mpdf->bMargin)->toBe(20);
});

it('sets all four margins independently when all four arguments are passed', function () {
    $engine = new PdfEngine();
    $engine->setPageMargins(5, 10, 15, 20);

    $mpdf = $engine->getInstance();

    expect($mpdf->DeflMargin)->toBe(5);
    expect($mpdf->DefrMargin)->toBe(10);
    expect($mpdf->tMargin)->toBe(15);
    expect($mpdf->bMargin)->toBe(20);
});

it('correctly handles zero as a valid margin value', function () {
    $engine = new PdfEngine();
    $engine->setPageMargins(0, 0, 0, 0);

    $mpdf = $engine->getInstance();

    expect($mpdf->tMargin)->toBe(0);
    expect($mpdf->bMargin)->toBe(0);
});
