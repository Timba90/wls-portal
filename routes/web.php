<?php

use App\Livewire\Dashboard\DashboardPage;
use App\Livewire\Profile\ProfilePage;
use App\Livewire\Profile\SecurityPage;
use App\Livewire\Users\UserList;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function (): void {
    Route::livewire('/dashboard', DashboardPage::class)->name('dashboard');

    Route::livewire('/profil', ProfilePage::class)->name('profile.show');
    Route::livewire('/profil/sicherheit', SecurityPage::class)->name('profile.security');

    Route::livewire('/benutzer', UserList::class)->name('users.index');
});
