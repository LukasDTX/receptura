<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OpakowanieResource\Pages;
use App\Models\Opakowanie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use App\Filament\Resources\OpakowanieResource\Form\OpakowanieForm;
use App\Filament\Resources\OpakowanieResource\Table\OpakowanieTable;
class OpakowanieResource extends Resource
{
    protected static ?string $model = Opakowanie::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    
    protected static ?string $navigationLabel = 'Opakowania';
    
    protected static ?string $modelLabel = 'Opakowanie';
    
    protected static ?string $pluralModelLabel = 'Opakowania';
    
    protected static ?string $navigationGroup = 'Produkcja';
    
    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
    public static function form(Form $form): Form
    {
        return $form->schema(OpakowanieForm::make());
    }
    public static function table(Table $table): Table
    {
        return OpakowanieTable::make($table);
    }



    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOpakowania::route('/'),
            'create' => Pages\CreateOpakowanie::route('/create'),
            'edit' => Pages\EditOpakowanie::route('/{record}/edit'),
        ];
    }
}