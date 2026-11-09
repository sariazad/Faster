<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('مين الأسبق')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->font('Tajawal')
            ->navigationItems([
                NavigationItem::make('ابدأ لعبة')
                    ->url('/play/new', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-play')
                    ->sort(100),
                NavigationItem::make('افتح آخر القائمة')
                    ->url('/akher', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-list-bullet')
                    ->group('آخر القائمة')
                    ->sort(100),
                NavigationItem::make('افتح ألف ياء')
                    ->url('/alefya', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-language')
                    ->group('ألف ياء')
                    ->sort(100),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->renderHook(
                \Filament\View\PanelsRenderHook::FOOTER,
                fn (): string => view('components.copyright', ['attributes' => new \Illuminate\View\ComponentAttributeBag(['class' => 'py-4 text-gray-400'])])->render(),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
