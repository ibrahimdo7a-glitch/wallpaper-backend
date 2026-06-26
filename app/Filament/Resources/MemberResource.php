<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MemberResource\Pages;
use App\Models\Member;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MemberResource extends Resource
{
    use \App\Filament\Concerns\HiddenFromCreatives;

    protected static ?string $model = Member::class;
    protected static ?string $slug = 'members';
    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'الأعضاء';
    protected static ?int    $navigationSort   = 1;

    public static function getNavigationLabel(): string  { return 'الأعضاء'; }
    public static function getModelLabel(): string       { return 'عضو'; }
    public static function getPluralModelLabel(): string { return 'الأعضاء'; }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('name')->label('الاسم')->maxLength(120),
                    Forms\Components\TextInput::make('telegram_username')->label('يوزر تلجرام')->disabled(),
                ]),
                Forms\Components\Grid::make(3)->schema([
                    Forms\Components\Select::make('status')->label('الحالة')
                        ->options(['active' => 'مفعّل', 'banned' => 'محظور'])->required(),
                    Forms\Components\Select::make('tier')->label('الباقة')
                        ->options(['none' => 'عادي', 'premium' => 'مميّز', 'merchant' => 'تاجر'])->default('none'),
                    Forms\Components\Toggle::make('is_premium')->label('مميّز ⭐')->inline(false),
                ]),
                Forms\Components\TextInput::make('phone')->label('الهاتف')->disabled(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('الاسم')->searchable(),
                Tables\Columns\TextColumn::make('telegram_username')->label('يوزر تلجرام')->prefix('@')->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('listings_count')->label('إعلاناته')->counts('listings'),
                Tables\Columns\TextColumn::make('tier')->label('الباقة')
                    ->formatStateUsing(fn ($state) => match ($state) { 'premium' => '⭐ مميّز', 'merchant' => '🏪 تاجر', default => 'عادي' }),
                Tables\Columns\TextColumn::make('status')->label('الحالة')->badge()
                    ->color(fn ($state) => $state === 'banned' ? 'danger' : 'success')
                    ->formatStateUsing(fn ($state) => $state === 'banned' ? 'محظور' : 'مفعّل'),
                Tables\Columns\TextColumn::make('last_login_at')->label('آخر دخول')->dateTime('d/m/Y')->placeholder('—')->sortable(),
                Tables\Columns\TextColumn::make('created_at')->label('انضمّ')->dateTime('d/m/Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')->label('الحالة')
                    ->options(['active' => 'مفعّل', 'banned' => 'محظور']),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_ban')
                    ->label(fn (Member $record) => $record->status === 'banned' ? 'تفعيل' : 'حظر')
                    ->icon('heroicon-o-no-symbol')
                    ->requiresConfirmation()
                    ->action(fn (Member $record) => $record->update(['status' => $record->status === 'banned' ? 'active' : 'banned'])),
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'edit'  => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
