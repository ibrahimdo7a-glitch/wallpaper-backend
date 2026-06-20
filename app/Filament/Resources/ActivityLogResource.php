<?php

namespace App\Filament\Resources;

use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Spatie\Activitylog\Models\Activity;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'التحليلات';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string { return 'سجل النشاطات'; }

    public static function getModelLabel(): string { return 'نشاط'; }

    public static function getPluralModelLabel(): string { return 'سجل النشاطات'; }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo('can_view_activity_logs');
    }

    public static function canCreate(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('المستخدم')
                    ->searchable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('النوع')
                    ->badge(),

                Tables\Columns\TextColumn::make('description')
                    ->label('الوصف')
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('العنصر')
                    ->formatStateUsing(fn($state) => class_basename($state ?? '')),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('النوع')
                    ->options([
                        'user' => 'مستخدم',
                        'wallpaper' => 'خلفية',
                        'default' => 'عام',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\ActivityLogResource\Pages\ListActivityLogs::route('/'),
        ];
    }
}
