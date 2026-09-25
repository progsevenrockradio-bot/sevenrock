<?php

namespace App\Support;

enum ModerationType: string
{
    case SUBMISSION = 'submission';
    case TALENT_REGISTRATION = 'talent_registration';
    case MEDIA = 'media';
    case ALBUM = 'album';
    case PRODUCT = 'product';
    case WALL_POST = 'wall_post';
    case COMMENT = 'comment';
    case CONTACT = 'contact';
    case AFFILIATE = 'affiliate';
    case AGENCY_BAND = 'agency_band';
    case CONTRACT = 'contract';
    case EVENT = 'event';

    public function label(): string
    {
        return match($this) {
            self::SUBMISSION => 'Maqueta / Demo',
            self::TALENT_REGISTRATION => 'Alta Talento',
            self::MEDIA => 'Multimedia',
            self::ALBUM => 'Álbum',
            self::PRODUCT => 'Producto',
            self::WALL_POST => 'Publicación Muro',
            self::COMMENT => 'Comentario',
            self::CONTACT => 'Contacto',
            self::AFFILIATE => 'Alta Fan/Afiliado',
            self::AGENCY_BAND => 'Banda de Agencia',
            self::CONTRACT => 'Firma Contrato',
            self::EVENT => 'Evento',
        };
    }
}
