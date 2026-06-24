<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsSubscriptionResource\Pages;
use App\Models\NewsSubscription;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsSubscriptionResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;
    protected static ?string $model = NewsSubscription::class;
    protected static ?string $navigationIcon  = 'heroicon-o-envelope';
    protected static ?string $navigationGroup = 'الأخبار';
    protected static ?int    $navigationSort  = 12;

    public static function getNavigationLabel(): string  { return 'المشتركون'; }
    public static function getModelLabel(): string       { return 'مشترك'; }
    public static function getPluralModelLabel(): string { return 'المشتركون في الأخبار'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('email')->label('البريد الإلكتروني')->email()->required(),
                Forms\Components\TextInput::make('name')->label('الاسم')->maxLength(100),
            ]),
            Forms\Components\Grid::make(3)->schema([
                Forms\Components\Select::make('status')->label('الحالة')
                    ->options(['active' => 'مفعّل', 'unsubscribed' => 'ألغى الاشتراك'])->default('active'),
                Forms\Components\Toggle::make('is_verified')->label('موثق')->inline(false),
                Forms\Components\Toggle::make('subscribe_all')->label('مشترك في كل الأخبار')->inline(false),
            ]),
            Forms\Components\Select::make('brands')->label('الماركات المشترك فيها')
                ->multiple()->relationship('brands', 'name_ar')->searchable()->preload(),
            Forms\Components\Select::make('carModels')->label('الموديلات المشترك فيها')
                ->multiple()->relationship('carModels', 'name_ar')->searchable()->preload(),
            Forms\Components\Select::make('newsCategories')->label('التصنيفات المشترك فيها')
                ->multiple()->relationship('newsCategories', 'name_ar')->searchable()->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->label('البريد')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة')
                    ->colors(['success' => 'active', 'danger' => 'unsubscribed'])
                    ->formatStateUsing(fn($state) => $state === 'active' ? 'مفعّل' : 'ألغى الاشتراك'),
                Tables\Columns\IconColumn::make('is_verified')->label('موثق')->boolean(),
                Tables\Columns\IconColumn::make('subscribe_all')->label('كل الأخبار')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('تاريخ الاشتراك')->dateTime('d/m/Y')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['active' => 'مفعّل', 'unsubscribed' => 'ألغى الاشتراك']),
                Tables\Filters\TernaryFilter::make('is_verified')->label('موثق'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([Tables\Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsSubscriptions::route('/'),
        ];
    }
}
