<?php

namespace App\Filament\Resources\FaceTemplates\Pages;

use App\Filament\Resources\FaceTemplates\FaceTemplateResource;

use Filament\Resources\Pages\ManageRecords;

class ManageFaceTemplates extends ManageRecords
{
    protected static string $resource = FaceTemplateResource::class;

   protected function getHeaderActions(): array
{
    return [];
}
    
}
