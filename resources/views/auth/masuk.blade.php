@extends('layouts.app')

@section('content')
    @php($aktorRaw = old('aktor', $aktor ?? 'pembeli'))
    @php($aktorTerpilih = $aktorRaw === 'admin_toko' ? 'admin_toko' : 'pembeli')
    <div class="auth-page">
        <section class="auth-brand" aria-labelledby="auth-brand-title">
            <div>
                <p class="auth-eyebrow">{{ config('store.name') }}{{ config('store.location') ? ' • '.config('store.location') : '' }}</p>
                <h2 id="auth-brand-title" class="auth-brand-title">{{ config('store.auth_headline') }}</h2>
                <p class="auth-brand-text">{{ config('store.auth_description') }}</p>
            </div>
            <div class="auth-brand-art" aria-hidden="true">
                <div class="auth-brand-tile">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                </div>
            </div>
            <p class="auth-brand-foot">{{ config('store.name') }} &bull; {{ config('store.tagline') }}</p>
        </section>

        <div class="auth-card">
            <p class="auth-eyebrow auth-eyebrow-primary">Masuk akun</p>
            <h1 class="auth-title">Selamat datang kembali</h1>
            <p class="auth-subtitle">Pilih peran akun, lalu masuk menggunakan email dan kata sandi Anda.</p>

            <form method="POST" action="{{ route('masuk') }}" class="auth-form">
                @csrf
                <fieldset class="auth-fieldset">
                    <legend class="auth-label">Pilih aktor</legend>
                    <div class="segmented" @error('aktor') aria-describedby="aktor-error" @enderror>
                        <label class="segmented-option">
                            <input type="radio" name="aktor" value="pembeli" {{ $aktorTerpilih === 'pembeli' ? 'checked' : '' }} required>
                            <span>Pembeli</span>
                        </label>
                        <label class="segmented-option">
                            <input type="radio" name="aktor" value="admin_toko" {{ $aktorTerpilih === 'admin_toko' ? 'checked' : '' }}>
                            <span>Admin Toko</span>
                        </label>
                    </div>
                    @error('aktor')<p class="field-error" id="aktor-error">{{ $message }}</p>@enderror
                </fieldset>

                <div class="auth-field">
                    <label for="email" class="auth-label">Email</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
                        <input class="input" id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required
                               @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    </div>
                    @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
                </div>

                <div class="auth-field">
                    <label for="password" class="auth-label">Kata sandi</label>
                    <div class="input-icon">
                        <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                        <input class="input input-has-toggle" id="password" type="password" name="password" autocomplete="current-password" required
                               @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                        <button type="button" class="input-toggle" data-password-toggle="password" aria-controls="password" aria-pressed="false" aria-label="Tampilkan kata sandi" hidden>
                            <svg data-icon-show aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg data-icon-hide aria-hidden="true" hidden xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/><path d="m2 2 20 20"/></svg>
                        </button>
                    </div>
                    @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
                </div>

                <button class="btn-primary btn-block" type="submit">
                    <span>Masuk</span>
                    <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </button>
            </form>

            <p class="auth-foot">Belum punya akun pembeli? <a href="{{ route('daftar') }}">Daftar pembeli</a></p>
            <p class="auth-note">Akun Admin Toko dibuat melalui provisioning internal dan tidak dapat daftar sendiri.</p>
        </div>
    </div>
@endsection
