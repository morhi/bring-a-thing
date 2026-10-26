<?php

namespace App\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The in-app notification triggers of F-23.
 *
 * Each kind renders through the interface key "notif.{camelCase kind}", so
 * the panel follows the viewer's current language rather than the one the
 * notification was created in (F-24).
 */
#[TypeScript]
enum NotificationKind: string
{
    case Invited = 'invited';
    case NewPoll = 'new_poll';
    case NewEvent = 'new_event';
    case Fixed = 'fixed';
    case Moved = 'moved';
    case Comment = 'comment';
    case ClaimedFor = 'claimed_for';
    case Released = 'released';

    /**
     * The interface translation key that phrases this kind, e.g. "notif.claimedFor".
     */
    public function translationKey(): string
    {
        return 'notif.'.lcfirst(str_replace('_', '', ucwords($this->value, '_')));
    }
}
