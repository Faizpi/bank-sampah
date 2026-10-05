<?php

use App\Actions\Auth\LogoutUser;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Http\Controllers\GroceryProofController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OperationalHealthController;
use App\Http\Controllers\PickupMediaController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\WithdrawalProofController;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Auth\RegisterCitizenForm;
use App\Livewire\Citizen\CustomerCard;
use App\Livewire\Citizen\Dashboard as CitizenDashboard;
use App\Livewire\Citizen\DepositHistory;
use App\Livewire\Citizen\DepositReceipt;
use App\Livewire\Citizen\EstimateForm;
use App\Livewire\Citizen\GroceryHistory;
use App\Livewire\Citizen\GroceryReceipt;
use App\Livewire\Citizen\GroceryRequestForm;
use App\Livewire\Citizen\GroceryShow;
use App\Livewire\Citizen\PickupRequestForm;
use App\Livewire\Citizen\PickupShow;
use App\Livewire\Citizen\WithdrawalHistory;
use App\Livewire\Citizen\WithdrawalReceipt;
use App\Livewire\Citizen\WithdrawalRequestForm;
use App\Livewire\Citizen\WithdrawalShow;
use App\Livewire\Notifications\NotificationCenter;
use App\Livewire\Officer\CustomerIdentification;
use App\Livewire\Officer\Dashboard as OfficerDashboard;
use App\Livewire\Officer\DepositForm;
use App\Livewire\Officer\GroceryTasks;
use App\Livewire\Officer\MobileServiceTasks;
use App\Livewire\Officer\PickupTask;
use App\Livewire\Profile\Password as ProfilePassword;
use App\Livewire\PublicSite\Announcements;
use App\Livewire\PublicSite\DepositVerification;
use App\Livewire\PublicSite\MobileSchedule;
use App\Livewire\PublicSite\PublicPrograms;
use App\Livewire\PublicSite\WasteCatalog;
use App\Livewire\PublicSite\WastePrices;
use App\Livewire\Statistics\InternalDashboard;
use App\Livewire\Treasurer\Dashboard as TreasurerDashboard;
use App\Livewire\Treasurer\Reports;
use App\Livewire\Treasurer\WithdrawalPayments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class)->name('health');
Route::get('/operations/health', OperationalHealthController::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:system.maintenance'])
    ->name('operations.health');

Route::view('/', 'welcome')->name('home');
Route::view('/ketentuan-dan-privasi', 'terms-and-privacy')->name('terms-and-privacy');
Route::livewire('/katalog-sampah', WasteCatalog::class)->name('public.catalog');
Route::livewire('/harga-sampah', WastePrices::class)->middleware('throttle:public-data')->name('public.prices');
Route::livewire('/pengumuman', Announcements::class)->name('public.announcements');
Route::livewire('/jadwal-keliling', MobileSchedule::class)->name('public.mobile-schedule');
Route::livewire('/target-dan-statistik', PublicPrograms::class)->middleware('throttle:public-data')->name('public.programs');
Route::livewire('/verifikasi/setoran/{token}', DepositVerification::class)->middleware('throttle:public-qr')->where('token', '[A-Za-z0-9_-]{43}')->name('public.deposit-verification');
Route::livewire('/daftar', RegisterCitizenForm::class)->middleware(['guest', 'throttle:registration'])->name('register');

Route::livewire('/login', LoginForm::class)->middleware('guest')->name('login');
Route::post('/logout', function (Request $request, LogoutUser $logoutUser): RedirectResponse {
    $logoutUser->handle($request);

    return to_route('login')->with('pwa_logout_confirmed', true);
})->middleware('auth')->name('logout');

Route::livewire('/dashboard/warga', CitizenDashboard::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:profile.view'])
    ->name('citizen.dashboard');

Route::livewire('/dashboard/petugas', OfficerDashboard::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:user.view'])
    ->name('officer.dashboard');

Route::livewire('/statistik/internal', InternalDashboard::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:statistics.internal.view'])
    ->name('statistics.internal');

Route::livewire('/petugas/pindai', CustomerIdentification::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:customer.view'])
    ->name('officer.customer-identification');

Route::livewire('/petugas/setoran/{customerId}', DepositForm::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:deposit.create', 'throttle:financial'])
    ->whereNumber('customerId')
    ->name('officer.deposit-form');

Route::livewire('/warga/estimasi', EstimateForm::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:waste.view'])
    ->name('citizen.estimate');

Route::livewire('/warga/riwayat-setoran', DepositHistory::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:deposit.view'])
    ->name('citizen.deposit-history');

Route::livewire('/warga/setoran/{deposit}', DepositReceipt::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:deposit.view'])
    ->name('citizen.deposit-receipt');

Route::livewire('/warga/penjemputan/ajukan', PickupRequestForm::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:pickup.request'])
    ->name('citizen.pickup.create');

Route::livewire('/warga/penjemputan/{pickup}', PickupShow::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:pickup.view'])
    ->whereNumber('pickup')
    ->name('citizen.pickup.show');

Route::livewire('/warga/riwayat-pencairan', WithdrawalHistory::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:withdrawal.view'])
    ->name('citizen.withdrawal-history');

Route::livewire('/warga/pencairan/ajukan', WithdrawalRequestForm::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:withdrawal.request', 'throttle:financial'])
    ->name('citizen.withdrawal.create');

Route::livewire('/warga/pencairan/{withdrawal}', WithdrawalShow::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:withdrawal.view'])
    ->whereNumber('withdrawal')
    ->name('citizen.withdrawal.show');

Route::livewire('/warga/pencairan/{withdrawal}/bukti', WithdrawalReceipt::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:withdrawal.view'])
    ->whereNumber('withdrawal')
    ->name('citizen.withdrawal.receipt');

Route::livewire('/petugas/penjemputan/{pickup}', PickupTask::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:pickup.execute', 'throttle:financial'])
    ->whereNumber('pickup')
    ->name('officer.pickup.task');

Route::livewire('/warga/riwayat-sembako', GroceryHistory::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:grocery.view'])
    ->name('citizen.grocery-history');

Route::livewire('/warga/sembako/ajukan', GroceryRequestForm::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:grocery.request', 'throttle:financial'])
    ->name('citizen.grocery.create');

Route::livewire('/warga/sembako/{redemption}', GroceryShow::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:grocery.view'])
    ->whereNumber('redemption')
    ->name('citizen.grocery.show');

Route::livewire('/warga/sembako/{redemption}/bukti', GroceryReceipt::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:grocery.view'])
    ->whereNumber('redemption')
    ->name('citizen.grocery.receipt');

Route::livewire('/petugas/sembako', GroceryTasks::class)
    ->middleware(['auth', 'session.fresh:30', 'throttle:financial'])
    ->name('officer.grocery.tasks');

Route::livewire('/petugas/layanan-keliling', MobileServiceTasks::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:mobile-service.operate'])
    ->name('officer.mobile-services');

Route::get('/media/pickups/{media}', PickupMediaController::class)
    ->middleware(['auth', 'session.fresh:30'])
    ->whereNumber('media')
    ->name('pickup.media');

Route::get('/media/withdrawals/{media}', WithdrawalProofController::class)
    ->middleware(['auth', 'session.fresh:30'])
    ->whereNumber('media')
    ->name('withdrawal.proof');

Route::get('/media/groceries/{media}', GroceryProofController::class)
    ->middleware(['auth', 'session.fresh:30'])
    ->whereNumber('media')
    ->name('grocery.proof');

Route::get('/laporan/ekspor/{export}', ReportExportController::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:report.export', 'throttle:exports'])
    ->whereNumber('export')
    ->name('reports.export.download');

Route::livewire('/warga/kartu-nasabah', CustomerCard::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:customer.view'])
    ->name('citizen.customer-card');

Route::livewire('/dashboard/bendahara', TreasurerDashboard::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:withdrawal.view'])
    ->name('treasurer.dashboard');

Route::livewire('/bendahara/pencairan', WithdrawalPayments::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:withdrawal.pay', 'throttle:financial'])
    ->name('treasurer.withdrawal.payments');

Route::livewire('/bendahara/laporan', Reports::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:report.view', 'throttle:exports'])
    ->name('treasurer.reports');

Route::livewire('/profil/kata-sandi', ProfilePassword::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:profile.view'])
    ->name('profile.password');

Route::livewire('/notifikasi', NotificationCenter::class)
    ->middleware(['auth', 'session.fresh:30', 'permission:notification.view'])
    ->name('notifications.index');

if (app()->environment('testing')) {
    Route::get('/_test/visible-users/{user}', function (Request $request, VisibleUsers $visibleUsers, int $user): JsonResponse {
        $visibleUser = $visibleUsers->queryFor($request->user())->findOrFail($user);

        return response()->json(['id' => $visibleUser->id]);
    })->middleware(['auth', 'permission:user.view'])->name('test.visible-users.show');
}
