<?php

namespace App\Filament\Resources\Inquiries\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class InquiryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('phone')
                    ->tel()
                    ->default(null),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->default(null),
                Textarea::make('message')
                    ->default(null)
                    ->columnSpanFull(),
                Select::make('product_id')
                    ->relationship('product', 'id')
                    ->default(null),
                Select::make('status')
                    ->options(['new' => 'New', 'read' => 'Read', 'replied' => 'Replied'])
                    ->default('new'),
            ]);
    }
}
