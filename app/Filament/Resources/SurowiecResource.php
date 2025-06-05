<?php
namespace App\Filament\Resources;
use App\Filament\Resources\SurowiecResource\Pages;
use App\Models\Surowiec;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\SurowiecResource\Form\SurowiecForm;
use App\Filament\Resources\SurowiecResource\Table\SurowiecTable;

class SurowiecResource extends Resource
{
    protected static ?string $model = Surowiec::class;

    protected static ?string $navigationIcon = 'heroicon-o-beaker';
    
    protected static ?string $navigationLabel = 'Surowce';
    
    protected static ?string $modelLabel = 'Surowiec';
    
    protected static ?string $pluralModelLabel = 'Surowce';

    // public static function form(Form $form): Form
    // {
    //     return $form
    //         ->schema([
    //             Forms\Components\TextInput::make('nazwa')
    //                 ->required()
    //                 ->maxLength(255),
    //             Forms\Components\TextInput::make('kod')
    //                 ->required()
    //                 ->unique(ignorable: fn ($record) => $record)
    //                 ->maxLength(255),
    //             Forms\Components\Textarea::make('opis')
    //                 ->maxLength(65535)
    //                 ->columnSpanFull(),
    //             Forms\Components\TextInput::make('cena_jednostkowa')
    //                 ->required()
    //                 ->numeric()
    //                 ->prefix('PLN')
    //                 ->label('Cena 1 kg')
    //                 ->default(1),
    //             Forms\Components\Select::make('jednostka_miary')
    //                 ->options([
    //                     'g' => 'Gram',
    //                     'kg' => 'Kilogram',
    //                     'ml' => 'Mililitr',
    //                     'l' => 'Litr',
    //                     'szt' => 'Sztuka',
    //                 ])
    //                 ->default('g') // Zmiana domyślnej wartości na gramy
    //                 ->required(),
    //         ]);
    // }

    // public static function table(Table $table): Table
    // {
    //     return $table
    //         ->columns([
    //             Tables\Columns\TextColumn::make('nazwa')
    //                 ->searchable(),
    //             Tables\Columns\TextColumn::make('kod')
    //                 ->searchable(),
    //             Tables\Columns\TextColumn::make('cena_jednostkowa')
    //                 ->money('pln')
    //                 ->sortable()
    //                 ->label('Cena 1 kg'),
    //             Tables\Columns\TextColumn::make('jednostka_miary')
    //                 ->searchable(),
    //             Tables\Columns\TextColumn::make('created_at')
    //                 ->dateTime()
    //                 ->sortable()
    //                 ->toggleable(isToggledHiddenByDefault: true),
    //             Tables\Columns\TextColumn::make('updated_at')
    //                 ->dateTime()
    //                 ->sortable()
    //                 ->toggleable(isToggledHiddenByDefault: true),
    //         ])
    //         ->filters([
    //             //
    //         ])
    //         ->actions([
    //             Tables\Actions\EditAction::make()
    //                 ->label('Edytuj')
    //                 ->icon('heroicon-o-pencil'),
    //             Tables\Actions\DeleteAction::make()
    //                 ->label('Usuń')
    //                 ->icon('heroicon-o-trash')
    //                 ->requiresConfirmation()
    //                 ->modalHeading('Usuń surowiec')
    //                 ->modalDescription('Czy na pewno chcesz usunąć ten surowiec? Ta akcja jest nieodwracalna.')
    //                 ->modalSubmitActionLabel('Usuń')
    //                 ->modalCancelActionLabel('Anuluj'),
    //         ]);
    // }

    public static function form(Form $form): Form
    {
        return $form->schema(SurowiecForm::make());
    }

    public static function table(Table $table): Table
    {
        return SurowiecTable::make($table);
    }
    public static function getGloballySearchableAttributes(): array
    {
        return ['nazwa', 'kod'];
    }
    
    public static function getNavigationGroup(): ?string
    {
        return 'Produkcja';
    }
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurowiecs::route('/'),
            'create' => Pages\CreateSurowiec::route('/create'),
            'edit' => Pages\EditSurowiec::route('/{record}/edit'),
        ];
    }
}