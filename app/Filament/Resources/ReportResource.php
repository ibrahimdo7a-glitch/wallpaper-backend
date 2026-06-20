<?php

namespace App\Filament\Resources;

use App\Models\Report;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationGroup = 'الإدارة';

    protected static ?int $navigationSort = 3;

    public static function getNavigationLabel(): string { return 'البلاغات'; }

    public static function getModelLabel(): string { return 'بلاغ'; }

    public static function getPluralModelLabel(): string { return 'البلاغات'; }

    public static function getNavigationBadge(): ?string
    {
        return (string) Report::where('status', 'new')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): string
    {
        return 'danger';
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_manage_reports');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('status')
                ->label('الحالة')
                ->options([
                    'new' => 'جديد',
                    'reviewed' => 'تمت المراجعة',
                    'dismissed' => 'تم الرفض',
                ])
                ->required(),

            Forms\Components\Textarea::make('admin_note')
                ->label('ملاحظة الأدمن')
                ->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('wallpaper.title_ar')->label('الخلفية')->limit(30),
                Tables\Columns\BadgeColumn::make('reason')
                    ->label('السبب')
                    ->formatStateUsing(fn($state) => match ($state) {
                        'copyright' => 'حقوق ملكية',
                        'inappropriate' => 'غير لائق',
                        'spam' => 'سبام',
                        'offensive' => 'مسيء',
                        'other' => 'أخرى',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('message')->label('الرسالة')->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->colors([
                        'danger' => 'new',
                        'success' => 'reviewed',
                        'secondary' => 'dismissed',
                    ])
                    ->formatStateUsing(fn($state) => match ($state) {
                        'new' => 'جديد',
                        'reviewed' => 'مراجعة',
                        'dismissed' => 'مرفوض',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->since()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['new' => 'جديد', 'reviewed' => 'مراجعة', 'dismissed' => 'مرفوض']),
                Tables\Filters\SelectFilter::make('reason')->label('السبب')
                    ->options([
                        'copyright' => 'حقوق ملكية',
                        'inappropriate' => 'غير لائق',
                        'spam' => 'سبام',
                        'offensive' => 'مسيء',
                        'other' => 'أخرى',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('review')
                    ->label('مراجعة')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn(Report $r) => $r->status === 'new')
                    ->form([
                        Forms\Components\Textarea::make('admin_note')->label('ملاحظة')->rows(2),
                        Forms\Components\Select::make('action_status')->label('الإجراء')
                            ->options(['reviewed' => 'تمت المراجعة', 'dismissed' => 'رفض البلاغ'])
                            ->required(),
                    ])
                    ->action(function (Report $report, array $data) {
                        $report->update([
                            'status' => $data['action_status'],
                            'admin_note' => $data['admin_note'],
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                    }),

                Tables\Actions\Action::make('view_wallpaper')
                    ->label('الخلفية')
                    ->icon('heroicon-o-eye')
                    ->url(fn(Report $r) => route('filament.admin.resources.wallpapers.edit', $r->wallpaper_id))
                    ->openUrlInNewTab(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\ReportResource\Pages\ListReports::route('/'),
        ];
    }
}
