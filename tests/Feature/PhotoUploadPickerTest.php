<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Citizen\PickupRequestForm;
use App\Livewire\Officer\CustomerIdentification;
use App\Livewire\Officer\DepositForm;
use App\Livewire\Officer\GroceryTasks;
use App\Livewire\Officer\PickupTask;
use App\Livewire\Treasurer\WithdrawalPayments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

final class PhotoUploadPickerTest extends TestCase
{
    public function test_media_picker_compresses_images_before_direct_livewire_upload_and_confirms_server_state(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        self::assertStringContainsString('preparedFiles.push(await preparePhotoPickerFile(file, picker));', $script);
        self::assertStringContainsString("setPhotoPickerStatus(\n                picker,", $script);
        self::assertStringContainsString('await uploadPhotoPickerFiles(picker, property, preparedFiles, input.multiple);', $script);
        self::assertSame(1, substr_count($script, 'await uploadPhotoPickerFiles(picker, property, preparedFiles, input.multiple);'));
        self::assertStringContainsString('wire.$uploadMultiple(property, files, onFinished, onError, onProgress);', $script);
        self::assertStringContainsString('const confirmedFiles = await wire.$call(confirmMethod);', $script);
        self::assertStringContainsString('PHOTO_PICKER_CONFIRM_ATTEMPTS', $script);
        self::assertStringContainsString("file.type.startsWith('image/')", $script);
        self::assertStringContainsString('return window.Livewire.find(componentId);', $script);
        self::assertStringContainsString('wire.$upload(', $script);
        self::assertStringContainsString('wire.$call(removeMethod, index)', $script);
        self::assertStringContainsString('trigger instanceof HTMLLabelElement', $script);
        self::assertStringContainsString("trigger.addEventListener('click'", $script);
        self::assertStringContainsString("input.addEventListener('change'", $script);
        self::assertStringContainsString('setPhotoPickerStatus(picker, `Mengunggah ${noun}… ${Math.round(progress)}%`', $script);
        self::assertStringContainsString("The label's native activation is", $script);
        self::assertStringContainsString("window.addEventListener('click', handlePublicNavigationClick);", $script);
        self::assertStringNotContainsString("const trigger = target?.closest('[data-public-navigation-trigger]');", $script);
        self::assertStringNotContainsString('DataTransfer', $script);
        self::assertStringNotContainsString('photoPickerEventRoot', $script);
        self::assertStringNotContainsString('syncPhotoPickerInput', $script);
        self::assertStringNotContainsString('photoPickerSyncing', $script);
    }

    public function test_picker_lifecycle_binds_each_live_input_and_hydrates_when_livewire_initializes(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));

        self::assertStringContainsString("input.dataset.photoPickerInput = 'compressed-upload-v2';", $script);
        self::assertStringContainsString("if (input.dataset.photoPickerInput === 'compressed-upload-v2')", $script);
        self::assertStringContainsString("document.addEventListener('livewire:initialized', () => hydratePhotoPickers());", $script);
        self::assertStringContainsString("document.addEventListener('livewire:navigated', () => hydratePhotoPickers());", $script);
    }

    public function test_picker_change_processing_does_not_block_other_change_listeners(): void
    {
        $script = (string) file_get_contents(resource_path('js/app.js'));
        preg_match('/async function handlePhotoPickerChange\(.*?^}/ms', $script, $matches);

        self::assertArrayHasKey(0, $matches);
        self::assertStringNotContainsString('stopImmediatePropagation', $matches[0]);
    }

    public function test_production_app_chunk_contains_the_current_shared_picker_contract(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true, 512, JSON_THROW_ON_ERROR);
        $asset = $manifest['resources/js/app.js']['file'] ?? null;

        self::assertIsString($asset);
        self::assertStringStartsWith('assets/', $asset);
        self::assertStringNotContainsString('..', $asset);

        $chunk = (string) file_get_contents(public_path('build/'.$asset));
        self::assertStringContainsString('data-photo-picker-input', $chunk);
        self::assertStringContainsString('compressed-upload-v2', $chunk);
        self::assertStringContainsString('Mengompres foto', $chunk);
    }

    public function test_reusable_picker_exposes_camera_gallery_loading_progress_and_authoritative_preview(): void
    {
        $html = Blade::render(<<<'BLADE'
            <x-ui.media-picker
                id="proof"
                property="proof"
                label="Bukti transaksi"
                hint="Foto dikompres sebelum upload."
                :allow-pdf="true"
                remove-method="clearProof"
                confirm-method="confirmProofUpload"
            />
        BLADE);

        self::assertStringContainsString('wire:ignore', $html);
        self::assertStringContainsString('data-photo-picker-trigger="camera"', $html);
        self::assertStringContainsString('data-photo-picker-trigger="gallery"', $html);
        self::assertSame(2, substr_count($html, 'for="proof"'));
        self::assertStringContainsString('role="button"', $html);
        self::assertStringContainsString('data-photo-picker-progress', $html);
        self::assertStringContainsString('data-photo-picker-icon="busy"', $html);
        self::assertStringContainsString('data-photo-picker-preview', $html);
        self::assertStringContainsString('data-photo-picker-property="proof"', $html);
        self::assertStringContainsString('accept="image/*,application/pdf"', $html);
        self::assertStringContainsString('class="sr-only"', $html);
        self::assertStringNotContainsString('wire:model', $html);
    }

    public function test_every_livewire_evidence_upload_uses_the_shared_compressing_picker(): void
    {
        $views = [
            'livewire/citizen/pickup-request-form.blade.php',
            'livewire/officer/pickup-task.blade.php',
            'livewire/treasurer/withdrawal-payments.blade.php',
            'livewire/officer/deposit-form.blade.php',
            'livewire/officer/grocery-tasks.blade.php',
            'livewire/officer/customer-identification.blade.php',
        ];
        $contents = implode("\n", array_map(
            static fn (string $view): string => (string) file_get_contents(resource_path("views/{$view}")),
            $views,
        ));

        self::assertSame(7, substr_count($contents, '<x-ui.media-picker'));
        self::assertStringNotContainsString('wire:model="evidence" type="file"', $contents);
        self::assertStringNotContainsString('wire:model="proof" type="file"', $contents);
        self::assertStringNotContainsString('wire:model="assistedEvidence" type="file"', $contents);
        self::assertStringNotContainsString('wire:model="withdrawalEvidence" type="file"', $contents);
    }

    public function test_removing_a_photo_updates_server_state_and_clears_the_stale_limit_error(): void
    {
        $first = UploadedFile::fake()->image('first.jpg');
        $second = UploadedFile::fake()->image('second.jpg');
        $duplicate = UploadedFile::fake()->image('duplicate.jpg');
        $component = new PickupRequestForm;
        $component->photos = [$first, $second, $duplicate];
        $component->addError('photos', 'Foto sampah tidak boleh berisi lebih dari 2 item.');
        $component->addError('photos.2', 'Foto ketiga tidak valid.');

        $component->removePhoto(2);

        self::assertSame([$first, $second], $component->photos);
        self::assertFalse($component->getErrorBag()->has('photos'));
        self::assertFalse($component->getErrorBag()->has('photos.2'));
    }

    public function test_custom_picker_components_can_remove_temporary_uploads_on_the_server(): void
    {
        $citizenComponent = (string) file_get_contents(app_path('Livewire/Citizen/PickupRequestForm.php'));
        $officerComponent = (string) file_get_contents(app_path('Livewire/Officer/PickupTask.php'));
        $treasurerComponent = (string) file_get_contents(app_path('Livewire/Treasurer/WithdrawalPayments.php'));
        $depositComponent = (string) file_get_contents(app_path('Livewire/Officer/DepositForm.php'));
        $groceryComponent = (string) file_get_contents(app_path('Livewire/Officer/GroceryTasks.php'));
        $identificationComponent = (string) file_get_contents(app_path('Livewire/Officer/CustomerIdentification.php'));

        self::assertStringContainsString('public function removePhoto(int $index): void', $citizenComponent);
        self::assertStringContainsString('public function updatedPhotos(): void', $citizenComponent);
        self::assertStringContainsString('public function confirmPhotoUploads(): array', $citizenComponent);
        self::assertStringContainsString('public function clearEvidence(): void', $officerComponent);
        self::assertStringContainsString('public function confirmEvidenceUpload(): array', $officerComponent);
        self::assertStringContainsString('public function clearProof(): void', $treasurerComponent);
        self::assertStringContainsString('public function confirmProofUpload(): array', $treasurerComponent);
        self::assertStringContainsString('public function clearEvidence(): void', $depositComponent);
        self::assertStringContainsString('public function confirmEvidenceUpload(): array', $depositComponent);
        self::assertStringContainsString('public function clearProof(): void', $groceryComponent);
        self::assertStringContainsString('public function confirmProofUpload(): array', $groceryComponent);
        self::assertStringContainsString('public function clearAssistedEvidence(): void', $identificationComponent);
        self::assertStringContainsString('public function confirmAssistedEvidenceUpload(): array', $identificationComponent);
        self::assertStringContainsString('public function clearWithdrawalEvidence(): void', $identificationComponent);
        self::assertStringContainsString('public function confirmWithdrawalEvidenceUpload(): array', $identificationComponent);
    }

    public function test_each_picker_confirms_the_authoritative_server_upload_before_showing_success(): void
    {
        $citizen = new PickupRequestForm;
        $citizen->photos = [
            UploadedFile::fake()->image('first.jpg'),
            UploadedFile::fake()->image('second.jpg'),
        ];
        $citizen->addError('photos', 'Foto sampah wajib diisi.');

        $citizenFiles = $citizen->confirmPhotoUploads();

        self::assertSame(['first.jpg', 'second.jpg'], array_column($citizenFiles, 'name'));
        self::assertFalse($citizen->getErrorBag()->has('photos'));

        $officer = new PickupTask;
        $officer->evidence = UploadedFile::fake()->image('evidence.jpg');
        $officer->addError('evidence', 'Tambahkan bukti foto.');

        self::assertSame('evidence.jpg', $officer->confirmEvidenceUpload()[0]['name']);
        self::assertFalse($officer->getErrorBag()->has('evidence'));

        $treasurer = new WithdrawalPayments;
        $treasurer->proof = UploadedFile::fake()->image('proof.jpg');
        $treasurer->addError('proof', 'Tambahkan satu foto bukti pembayaran.');

        self::assertSame('proof.jpg', $treasurer->confirmProofUpload()[0]['name']);
        self::assertFalse($treasurer->getErrorBag()->has('proof'));

        $deposit = new DepositForm;
        $deposit->evidence = UploadedFile::fake()->image('deposit.jpg');
        self::assertSame('image/jpeg', $deposit->confirmEvidenceUpload()[0]['mimeType']);

        $grocery = new GroceryTasks;
        $grocery->proof = UploadedFile::fake()->image('handover.jpg');
        self::assertSame('handover.jpg', $grocery->confirmProofUpload()[0]['name']);

        $identification = new CustomerIdentification;
        $identification->assistedEvidence = UploadedFile::fake()->image('assisted.jpg');
        $identification->withdrawalEvidence = UploadedFile::fake()->image('withdrawal.jpg');
        self::assertSame('assisted.jpg', $identification->confirmAssistedEvidenceUpload()[0]['name']);
        self::assertSame('withdrawal.jpg', $identification->confirmWithdrawalEvidenceUpload()[0]['name']);
    }

    public function test_early_confirmation_does_not_add_a_required_error_while_livewire_is_settling_the_upload(): void
    {
        $citizen = new PickupRequestForm;

        self::assertSame([], $citizen->confirmPhotoUploads());
        self::assertFalse($citizen->getErrorBag()->has('photos'));

        $officer = new PickupTask;

        self::assertSame([], $officer->confirmEvidenceUpload());
        self::assertFalse($officer->getErrorBag()->has('evidence'));
    }
}
