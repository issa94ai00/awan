<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('setting_key')
                    ->required(),
                Textarea::make('setting_value')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('setting_type')
                    ->options(['text' => 'Text', 'textarea' => 'Textarea', 'image' => 'Image', 'number' => 'Number'])
                    ->default('text'),
            ]);
    }
}
