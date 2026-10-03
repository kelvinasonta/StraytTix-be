<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Shared\app\Contracts\ImageUploadServiceInterface;
use Modules\Shared\app\DTOs\ImageUploadOptions;
use Modules\Shared\app\Exceptions\ImageUploadException;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('s3');
});

test('uploads a valid image and persists to public storage as clean webp', function () {
    $service = app(ImageUploadServiceInterface::class);
    $file = UploadedFile::fake()->image('test_banner.jpg', 800, 600);

    $dto = $service->upload($file, ImageUploadOptions::forPreset('event_banner', [
        'disk' => 'public',
    ]));

    expect($dto->disk)->toBe('public')
        ->and($dto->mimeType)->toBe('image/webp')
        ->and($dto->extension)->toBe('webp')
        ->and($dto->width)->toBe(800)
        ->and($dto->height)->toBe(600)
        ->and(Storage::disk('public')->exists($dto->path))->toBeTrue();
});

test('supports persisting to s3 storage disk', function () {
    $service = app(ImageUploadServiceInterface::class);
    $file = UploadedFile::fake()->image('avatar.png', 300, 300);

    $dto = $service->upload($file, ImageUploadOptions::forPreset('avatar', [
        'disk' => 's3',
    ]));

    expect($dto->disk)->toBe('s3')
        ->and($dto->mimeType)->toBe('image/webp')
        ->and(Storage::disk('s3')->exists($dto->path))->toBeTrue();
});

test('rejects spoofed file with fake extension and php content', function () {
    $service = app(ImageUploadServiceInterface::class);

    // Create a text file with PHP payload renamed as .jpg
    $spoofedFile = UploadedFile::fake()->createWithContent('exploit.jpg', "<?php system(\$_GET['cmd']); ?>");

    expect(fn () => $service->upload($spoofedFile))
        ->toThrow(ImageUploadException::class);
});

test('rejects polyglot image containing embedded web shell signatures', function () {
    $service = app(ImageUploadServiceInterface::class);

    // Generate a real binary image with a malicious PHP payload appended
    $tempImage = tempnam(sys_get_temp_dir(), 'poly_');
    $img = imagecreatetruecolor(100, 100);
    imagejpeg($img, $tempImage);
    imagedestroy($img);

    // Append malicious script signature
    file_put_contents($tempImage, "<?php eval(\$_POST['c']); ?>", FILE_APPEND);

    $file = new UploadedFile($tempImage, 'innocent.jpg', 'image/jpeg', null, true);

    expect(fn () => $service->upload($file))
        ->toThrow(ImageUploadException::class, 'Potential malicious payload or script signature detected');

    @unlink($tempImage);
});

test('rejects image exceeding preset file size limit', function () {
    $service = app(ImageUploadServiceInterface::class);

    // Avatar preset has 2MB limit (2048 KB)
    // Create an image exceeding 2.5MB
    $file = UploadedFile::fake()->image('giant.jpg', 500, 500)->size(2600);

    expect(fn () => $service->upload($file, ImageUploadOptions::forPreset('avatar')))
        ->toThrow(ImageUploadException::class, 'exceeds the maximum allowed limit');
});

test('rejects image exceeding maximum dimensions (decompression bomb guard)', function () {
    $service = app(ImageUploadServiceInterface::class);

    // Max avatar width/height is 1024
    $file = UploadedFile::fake()->image('bomb.jpg', 2000, 2000);

    expect(fn () => $service->upload($file, ImageUploadOptions::forPreset('avatar')))
        ->toThrow(ImageUploadException::class, 'Image dimensions');
});

test('allows deleting uploaded images and checking existence', function () {
    $service = app(ImageUploadServiceInterface::class);
    $file = UploadedFile::fake()->image('profile.jpg', 200, 200);

    $dto = $service->upload($file, ImageUploadOptions::forPreset('avatar', ['disk' => 'public']));

    expect($service->exists($dto->path, 'public'))->toBeTrue();

    $deleted = $service->delete($dto->path, 'public');
    expect($deleted)->toBeTrue()
        ->and($service->exists($dto->path, 'public'))->toBeFalse();
});

test('strictly rejects SVG files to prevent XSS and XXE attacks', function () {
    $service = app(ImageUploadServiceInterface::class);

    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert("XSS")</script></svg>';
    $svgFile = UploadedFile::fake()->createWithContent('vector.svg', $svgContent);

    expect(fn () => $service->upload($svgFile))
        ->toThrow(ImageUploadException::class);
});

test('supports custom filename prefix and custom format conversion to jpg', function () {
    $service = app(ImageUploadServiceInterface::class);
    $file = UploadedFile::fake()->image('logo.png', 400, 400);

    $dto = $service->upload($file, new ImageUploadOptions(
        disk: 'public',
        directory: 'custom_dir',
        convertTo: 'jpg',
        filenamePrefix: 'org_123'
    ));

    expect($dto->filename)->toStartWith('org_123_')
        ->and($dto->extension)->toBe('jpg')
        ->and($dto->mimeType)->toBe('image/jpeg')
        ->and(Storage::disk('public')->exists($dto->path))->toBeTrue();
});
