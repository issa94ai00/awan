<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Set;
use Illuminate\Support\Str;
use Filament\Schemas\Schema;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('category_id')
                    ->relationship('category', 'name_ar')
                    ->default(null),
                TextInput::make('name_ar')
                 ->live() // Makes the field reactive
                ->afterStateUpdated(fn (Set $set, ?string $state) => $set('slug', Str::slug($state)))
                    ->required(),
                TextInput::make('name_en')
                    ->default(null),
                TextInput::make('slug')
                    ->default(null),
                Textarea::make('description_ar')
                    ->default(null)
                    ->columnSpanFull(),
                Textarea::make('description_en')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('price')
                    ->numeric()
                    ->default(null)
                    ->prefix('$'),
                FileUpload::make('image_main')
                    ->image(),
                Textarea::make('image_gallery')
                    ->default(null)
                    ->columnSpanFull(),
                TextInput::make('brand')
                    ->default(null),
                TextInput::make('model')
                    ->default(null),
                Toggle::make('in_stock'),
                Toggle::make('is_featured'),
                TextInput::make('views_count')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
