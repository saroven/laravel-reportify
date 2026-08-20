<?php

it('renders reportify-buttons component blade template', function () {
    $view = $this->blade('<x-reportify-buttons />');

    $view->assertSee('Export');
});

it('renders reportify-scripts component blade template', function () {
    $view = $this->blade('<x-reportify-scripts />');

    $view->assertSee('exportLinkRedirectWithUrlParams');
});
