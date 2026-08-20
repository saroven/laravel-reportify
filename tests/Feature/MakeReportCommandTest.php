<?php

use Illuminate\Support\Facades\File;

it('generates a new Reportable export class using php artisan reportify:make', function () {
    $targetPath = app_path('Exports/TestUserExport.php');
    if (File::exists($targetPath)) {
        File::delete($targetPath);
    }

    $this->artisan('reportify:make', ['name' => 'TestUser'])
        ->assertSuccessful();

    expect(File::exists($targetPath))->toBeTrue();
    
    File::delete($targetPath);
});
